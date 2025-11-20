<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductExtractionService
{
    protected OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Extract product information from URL or text
     */
    public function extractProductInfo(string $input): array
    {
        try {
            $isUrl = filter_var($input, FILTER_VALIDATE_URL) !== false;

            if ($isUrl) {
                return $this->extractFromUrl($input);
            } else {
                return $this->extractFromText($input);
            }
        } catch (\Exception $e) {
            Log::error('Product extraction error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to extract product information: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract product information from URL
     */
    protected function extractFromUrl(string $url): array
    {
        try {
            // Fetch the webpage content
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; ProductBot/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch URL content'
                ];
            }

            $htmlContent = $response->body();

            // Extract text content from HTML
            $textContent = $this->extractTextFromHtml($htmlContent);

            // Extract meta information
            $metaInfo = $this->extractMetaFromHtml($htmlContent);

            // Use AI to extract structured product information
            return $this->analyzeContentWithAI($textContent, $metaInfo, $url);

        } catch (\Exception $e) {
            Log::error('URL extraction error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to extract from URL: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract product information from text description
     */
    protected function extractFromText(string $text): array
    {
        try {
            return $this->analyzeContentWithAI($text);
        } catch (\Exception $e) {
            Log::error('Text extraction error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to extract from text: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Analyze content with AI to extract product information
     */
    protected function analyzeContentWithAI(string $content, array $metaInfo = [], string $url = null): array
    {
        $prompt = "Analyze the following content and extract product/service information. Return a JSON object with the following structure:

{
    \"name\": \"Product/Service name\",
    \"type\": \"product\" or \"service\",
    \"description\": \"Detailed description (200-500 words)\",
    \"short_description\": \"Brief description (50-100 words)\",
    \"features\": [\"feature1\", \"feature2\", \"feature3\"],
    \"key_benefits\": [\"benefit1\", \"benefit2\", \"benefit3\"],
    \"use_cases\": [\"use case1\", \"use case2\"],
    \"target_audience\": [\"audience1\", \"audience2\"],
    \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
    \"pricing_info\": \"Pricing information if available\",
    \"meta_description\": \"SEO-friendly meta description\"
}

Focus on extracting accurate and relevant information. If some fields cannot be determined from the content, use reasonable defaults or leave them empty.

Content to analyze:
" . substr($content, 0, 4000) . // Limit content to avoid token limits

($metaInfo ? "\n\nMeta Information:\n" . json_encode($metaInfo, JSON_PRETTY_PRINT) : "");

        try {
            $response = $this->openAIService->generateChatResponse(
                "You are an expert product analyst. Extract structured product information from the given content.",
                $prompt,
                []
            );

            // Try to extract JSON from the response
            $jsonStart = strpos($response, '{');
            $jsonEnd = strrpos($response, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
                $extractedData = json_decode($jsonString, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'success' => true,
                        'data' => array_merge($extractedData, [
                            'primary_url' => $url,
                            'is_active' => true,
                            'is_featured' => false,
                            'sort_order' => 0
                        ])
                    ];
                }
            }

            // Fallback: return basic information
            return [
                'success' => true,
                'data' => [
                    'name' => $metaInfo['title'] ?? 'Extracted Product',
                    'type' => 'product',
                    'description' => substr($content, 0, 500),
                    'short_description' => $metaInfo['description'] ?? substr($content, 0, 150),
                    'primary_url' => $url,
                    'features' => [],
                    'key_benefits' => [],
                    'use_cases' => [],
                    'target_audience' => [],
                    'keywords' => [],
                    'is_active' => true,
                    'is_featured' => false,
                    'sort_order' => 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error('AI analysis error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to analyze content with AI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract text content from HTML
     */
    protected function extractTextFromHtml(string $html): string
    {
        // Remove script and style elements
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

        // Strip HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Extract meta information from HTML
     */
    protected function extractMetaFromHtml(string $html): array
    {
        $metaInfo = [];

        // Extract title
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $metaInfo['title'] = trim($matches[1]);
        }

        // Extract meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $metaInfo['description'] = trim($matches[1]);
        }

        // Extract Open Graph data
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $metaInfo['og_title'] = trim($matches[1]);
        }

        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $metaInfo['og_description'] = trim($matches[1]);
        }

        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $metaInfo['og_image'] = trim($matches[1]);
        }

        return $metaInfo;
    }
}