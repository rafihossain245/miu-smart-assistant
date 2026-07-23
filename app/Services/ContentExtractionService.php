<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Smalot\PdfParser\Parser as PdfParser;

class ContentExtractionService
{
    protected OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function extractFromUrl(string $url): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; ChatbotCrawler/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch URL: ' . $response->status());
            }

            $html = $response->body();

            // Extract title
            preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatches);
            $title = isset($titleMatches[1]) ? strip_tags(trim($titleMatches[1])) : 'Untitled';

            // Remove script and style tags
            $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
            $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);

            // Extract main content (try to find article, main, or content divs)
            $contentPatterns = [
                '/<article[^>]*>(.*?)<\/article>/is',
                '/<main[^>]*>(.*?)<\/main>/is',
                '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is',
                '/<div[^>]*id="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/is',
            ];

            $content = '';
            foreach ($contentPatterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $content = $matches[1];
                    break;
                }
            }

            // If no content pattern found, use body
            if (empty($content)) {
                preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $bodyMatches);
                $content = isset($bodyMatches[1]) ? $bodyMatches[1] : $html;
            }

            // Clean up the content
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            return [
                'title' => $title,
                'content' => $content,
            ];

        } catch (\Exception $e) {
            Log::error('URL extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function extractFromUrlWithAISummarization(string $url): array
    {
        try {
            // First, extract content using the standard method
            $basicExtraction = $this->extractFromUrl($url);

            // If content is too short, return as-is
            if (strlen($basicExtraction['content']) < 100) {
                return $basicExtraction;
            }

            // Use AI to enhance the content for chatbot use
            $enhancedData = $this->enhanceContentWithAI($basicExtraction['content'], $basicExtraction['title'], $url);

            return [
                'title' => $enhancedData['title'],
                'content' => $enhancedData['content'],
                'metadata' => $enhancedData['metadata'] ?? [],
                'ai_enhanced' => true,
            ];

        } catch (\Exception $e) {
            Log::error('AI-enhanced URL extraction error: ' . $e->getMessage());
            // Fallback to basic extraction if AI enhancement fails
            return $this->extractFromUrl($url);
        }
    }

    protected function enhanceContentWithAI(string $content, string $title, string $url): array
    {
        try {
            // Truncate content if too long for AI processing
            $maxContentLength = 4000;
            if (strlen($content) > $maxContentLength) {
                $content = substr($content, 0, $maxContentLength) . '...';
            }

            $systemPrompt = "You are an AI assistant that analyzes web content and optimizes it for chatbot knowledge bases. Your task is to:

1. Identify if this content is about products/services, documentation, or general information
2. Extract key information and structure it for easy chatbot retrieval
3. Create a comprehensive summary that maintains important details
4. Extract metadata like pricing, features, categories, etc.

Format your response as JSON with these fields:
- content_type: 'product', 'service', 'documentation', 'article', or 'general'
- enhanced_title: Improved title for the knowledge base
- summary: A comprehensive summary optimized for chatbot responses (200-500 words)
- key_points: Array of 5-10 key points or features
- metadata: Object with relevant fields like pricing, category, features, etc.
- keywords: Array of relevant keywords for search";

            $userMessage = "URL: {$url}
Title: {$title}

Content:
{$content}

Please analyze this content and provide an enhanced version optimized for chatbot knowledge base use.";

            $response = $this->openAIService->generateChatResponse($systemPrompt, $userMessage);

            // Extract JSON from response if it's wrapped in markdown code blocks
            $jsonMatch = null;
            if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
                $jsonMatch = $matches[1];
            } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
                $jsonMatch = $matches[1];
            } else {
                $jsonMatch = $response;
            }

            // Try to parse JSON response
            $aiData = json_decode($jsonMatch, true);

            if (!$aiData) {
                // If JSON parsing fails, use the response as enhanced content
                return [
                    'title' => $title,
                    'content' => $response,
                    'metadata' => ['ai_processed' => true]
                ];
            }

            // Build enhanced content from AI analysis
            $enhancedContent = $aiData['summary'] ?? $response;

            if (!empty($aiData['key_points'])) {
                $enhancedContent .= "\n\nKey Points:\n" . implode("\n", array_map(fn($point) => "• {$point}", $aiData['key_points']));
            }

            return [
                'title' => $aiData['enhanced_title'] ?? $title,
                'content' => $enhancedContent,
                'metadata' => array_merge(
                    $aiData['metadata'] ?? [],
                    [
                        'content_type' => $aiData['content_type'] ?? 'general',
                        'keywords' => $aiData['keywords'] ?? [],
                        'ai_processed' => true,
                        'original_url' => $url
                    ]
                )
            ];

        } catch (\Exception $e) {
            Log::error('AI content enhancement error: ' . $e->getMessage());
            return [
                'title' => $title,
                'content' => $content,
                'metadata' => ['ai_processing_failed' => true]
            ];
        }
    }

    public function extractFromPdf(string $filePath): array
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);

            $title = $pdf->getDetails()['Title'] ?? basename($filePath, '.pdf');
            $content = $pdf->getText();

            // Clean up content
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            return [
                'title' => $title,
                'content' => $content,
            ];

        } catch (\Exception $e) {
            Log::error('PDF extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function extractFromImage(string $filePath): array
    {
        try {
            $title = pathinfo($filePath, PATHINFO_FILENAME);
            $content = '';

            // Use OpenAI Vision API to extract text from image
            if (file_exists($filePath)) {
                $imageFile = new SymfonyFile($filePath);
                $content = $this->openAIService->extractTextFromImage($imageFile);
            }

            // If no content extracted, use filename as fallback
            if (empty($content)) {
                $content = 'Image: ' . $title;
            }

            return [
                'title' => $title,
                'content' => $content,
            ];

        } catch (\Exception $e) {
            Log::error('Image extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function extractFromExcel(string $filePath): array
    {
        try {
            $title = pathinfo($filePath, PATHINFO_FILENAME);
            $content = '';

            // Read Excel file using PhpSpreadsheet
            $spreadsheet = IOFactory::load($filePath);
            
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $content .= "Sheet: " . $sheet->getTitle() . "\n\n";
                
                foreach ($sheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $value = $cell->getValue();
                        if ($value !== null) {
                            $rowData[] = $value;
                        }
                    }
                    
                    if (!empty($rowData)) {
                        $content .= implode(" | ", $rowData) . "\n";
                    }
                }
                $content .= "\n";
            }

            // Clean up content
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            // If no content extracted, use filename as fallback
            if (empty($content)) {
                $content = 'Excel file: ' . $title;
            }

            return [
                'title' => $title,
                'content' => $content,
            ];

        } catch (\Exception $e) {
            Log::error('Excel extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function extractFromYoutube(string $url): array
    {
        try {
            // Extract video ID from URL
            $videoId = $this->extractYoutubeVideoId($url);

            if (!$videoId) {
                throw new \Exception('Invalid YouTube URL');
            }

            // Get video details using YouTube API or web scraping
            $videoTitle = $this->getYoutubeVideoTitle($videoId);

            // For now, we'll just return the video info
            // In a production environment, you'd want to use YouTube's API or transcript services
            $content = "YouTube video: {$videoTitle}\nVideo URL: {$url}\n\nNote: Transcript extraction requires YouTube API setup or third-party services.";

            return [
                'title' => $videoTitle,
                'content' => $content,
            ];

        } catch (\Exception $e) {
            Log::error('YouTube extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function extractYoutubeVideoId(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function getYoutubeVideoTitle(string $videoId): string
    {
        try {
            $url = "https://www.youtube.com/watch?v={$videoId}";
            $response = Http::timeout(15)->get($url);

            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $response->body(), $matches)) {
                $title = strip_tags(trim($matches[1]));
                // Remove " - YouTube" suffix
                $title = preg_replace('/ - YouTube$/', '', $title);
                return $title;
            }

            return "YouTube Video ({$videoId})";

        } catch (\Exception $e) {
            return "YouTube Video ({$videoId})";
        }
    }

    public function calculatePriorityScore(array $metadata, string $url = '', string $content = '', ?int $chatbotId = null): int
    {
        $score = 50; // Default score

        // Check metadata content type
        if (isset($metadata['content_type'])) {
            switch ($metadata['content_type']) {
                case 'product':
                    $score = 90; // Highest priority for products
                    break;
                case 'service':
                    $score = 85; // High priority for services
                    break;
                case 'documentation':
                    $score = 75; // Medium-high for documentation
                    break;
                case 'article':
                    $score = 60; // Medium for articles
                    break;
                default:
                    $score = 50; // Default
            }
        }

        // Get company indicators dynamically from existing sources if chatbot provided
        $companyIndicators = [];
        if ($chatbotId) {
            $companyIndicators = $this->extractCompanyIndicators($chatbotId);
        }

        // Fallback to generic indicators if no specific company data found
        if (empty($companyIndicators)) {
            $companyIndicators = ['codecanyon.net/item', 'github.com'];
        }

        $urlLower = strtolower($url);
        $contentLower = strtolower($content);

        foreach ($companyIndicators as $indicator) {
            if (strpos($urlLower, strtolower($indicator)) !== false ||
                strpos($contentLower, strtolower($indicator)) !== false) {
                $score += 20; // Boost company/user content
                break;
            }
        }

        // Check for product/service keywords in content
        $productKeywords = [
            'ecommerce', 'marketplace', 'cms', 'script', 'php', 'laravel', 'react',
            'multivendor', 'freelancer', 'service booking', 'donation', 'rental'
        ];

        foreach ($productKeywords as $keyword) {
            if (strpos($contentLower, $keyword) !== false) {
                $score += 5; // Small boost for relevant keywords
            }
        }

        // Ensure score stays within bounds
        return min(100, max(10, $score));
    }

    /**
     * Extract company indicators from existing chatbot sources
     */
    protected function extractCompanyIndicators(int $chatbotId): array
    {
        $indicators = [];

        try {
            // Get unique domains from existing sources
            $sources = \App\Models\Source::where('chatbot_id', $chatbotId)
                ->where('status', 'completed')
                ->whereNotNull('url')
                ->pluck('url')
                ->take(50);

            foreach ($sources as $url) {
                $parsedUrl = parse_url($url);
                if (isset($parsedUrl['host'])) {
                    $domain = strtolower($parsedUrl['host']);
                    $domain = preg_replace('/^www\./', '', $domain); // Remove www
                    $indicators[] = $domain;
                }
            }

            // Get unique indicators and return most common ones
            $indicators = array_unique($indicators);
            $indicators = array_slice($indicators, 0, 10); // Limit to top 10

        } catch (\Exception $e) {
            Log::info('Could not extract company indicators: ' . $e->getMessage());
        }

        return $indicators;
    }
}