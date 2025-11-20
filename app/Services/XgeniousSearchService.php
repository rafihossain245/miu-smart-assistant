<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XgeniousSearchService
{
    /**
     * Search xgenious.com website for relevant content
     */
    public function searchXgenious(string $query): array
    {
        try {
            // Search for Xgenious-related content using Google Custom Search or web scraping
            $searchUrl = "https://www.google.com/search?q=site:xgenious.com " . urlencode($query);

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])
                ->get($searchUrl);

            if ($response->successful()) {
                $html = $response->body();
                $results = $this->parseSearchResults($html);

                if (!empty($results)) {
                    return [
                        'found' => true,
                        'results' => $results,
                        'source' => 'xgenious.com'
                    ];
                }
            }

            // Fallback: Try to search directly on xgenious.com
            return $this->searchXgeniousDirect($query);

        } catch (\Exception $e) {
            Log::error('Xgenious search error: ' . $e->getMessage());
            return ['found' => false, 'results' => []];
        }
    }

    /**
     * Parse Google search results for Xgenious content
     */
    private function parseSearchResults(string $html): array
    {
        $results = [];

        // Simple regex to extract search result titles and descriptions
        if (preg_match_all('/<h3[^>]*>(.*?)<\/h3>/', $html, $titleMatches)) {
            $titles = $titleMatches[1];

            if (preg_match_all('/<span[^>]*class="[^"]*st[^"]*"[^>]*>(.*?)<\/span>/s', $html, $descMatches)) {
                $descriptions = $descMatches[1];

                for ($i = 0; $i < min(count($titles), count($descriptions), 3); $i++) {
                    $results[] = [
                        'title' => strip_tags($titles[$i]),
                        'description' => strip_tags($descriptions[$i]),
                        'source' => 'Xgenious'
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Search directly on xgenious.com
     */
    private function searchXgeniousDirect(string $query): array
    {
        try {
            // Try to fetch xgenious.com homepage for general info
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])
                ->get('https://xgenious.com');

            if ($response->successful()) {
                $html = $response->body();

                // Extract relevant information from the homepage
                if (preg_match('/<title>(.*?)<\/title>/i', $html, $titleMatch)) {
                    $title = strip_tags($titleMatch[1]);
                }

                // Look for meta description
                if (preg_match('/<meta[^>]*name="description"[^>]*content="([^"]*)"[^>]*>/i', $html, $descMatch)) {
                    $description = $descMatch[1];
                }

                return [
                    'found' => true,
                    'results' => [[
                        'title' => $title ?? 'Xgenious - Digital Agency',
                        'description' => $description ?? 'Xgenious is a leading digital agency providing web development, mobile app development, and digital marketing services.',
                        'source' => 'Xgenious Official Website',
                        'url' => 'https://xgenious.com'
                    ]],
                    'source' => 'xgenious.com'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Direct Xgenious search error: ' . $e->getMessage());
        }

        return ['found' => false, 'results' => []];
    }

    /**
     * Check if a query is related to Xgenious
     */
    public function isXgeniousRelated(string $query): bool
    {
        $lowerQuery = strtolower($query);
        $xgeniousKeywords = [
            'xgenious', 'x-genious', 'xgenious.com',
            'digital agency', 'web development company',
            'mobile app development', 'digital marketing',
            'software development', 'app development',
            'website development', 'laravel development',
            'react development', 'flutter development'
        ];

        foreach ($xgeniousKeywords as $keyword) {
            if (str_contains($lowerQuery, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a contextual response based on Xgenious search results
     */
    public function generateXgeniousResponse(array $searchResults, string $originalQuery): string
    {
        if (!$searchResults['found'] || empty($searchResults['results'])) {
            return "I'd be happy to help you with information about Xgenious! Xgenious is a digital agency that provides web development, mobile app development, and digital marketing services. You can visit their website at xgenious.com for more details. Is there something specific about their services you'd like to know?";
        }

        $response = "Great question about Xgenious! Here's what I found:\n\n";

        foreach ($searchResults['results'] as $index => $result) {
            $response .= "**" . ($index + 1) . ". " . $result['title'] . "**\n";
            $response .= $result['description'] . "\n\n";
        }

        $response .= "For more detailed information, I recommend visiting xgenious.com directly. Is there anything specific about their services you'd like me to help you with?";

        return $response;
    }
}