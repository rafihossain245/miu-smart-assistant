<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

class SitemapParserService
{
    /**
     * Parse sitemap and extract URLs
     */
    public function parseSitemap(string $sitemapUrl): array
    {
        try {
            Log::info("Parsing sitemap: {$sitemapUrl}");

            $response = Http::timeout(30)->get($sitemapUrl);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch sitemap: HTTP {$response->status()}");
            }

            $xmlContent = $response->body();
            $urls = [];

            // Load XML
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);

            if (!$dom->loadXML($xmlContent)) {
                throw new \Exception("Invalid XML content in sitemap");
            }

            $xpath = new DOMXPath($dom);

            // Register the sitemap namespace
            $xpath->registerNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            // Check if this is a sitemap index or regular sitemap
            $sitemapNodes = $xpath->query('//sm:sitemap/sm:loc | //sitemap/loc');

            if ($sitemapNodes->length > 0) {
                // This is a sitemap index, parse sub-sitemaps
                Log::info("Found sitemap index with {$sitemapNodes->length} sub-sitemaps");

                foreach ($sitemapNodes as $node) {
                    $subSitemapUrl = trim($node->textContent);
                    $subUrls = $this->parseSitemap($subSitemapUrl);
                    $urls = array_merge($urls, $subUrls);
                }
            } else {
                // Regular sitemap, extract URLs
                $urlNodes = $xpath->query('//sm:url/sm:loc | //url/loc');

                Log::info("Found regular sitemap with {$urlNodes->length} URLs");

                foreach ($urlNodes as $node) {
                    $url = trim($node->textContent);

                    // Get additional metadata if available
                    $urlParent = $node->parentNode;
                    $lastmod = $xpath->query('sm:lastmod | lastmod', $urlParent)->item(0)?->textContent;
                    $priority = $xpath->query('sm:priority | priority', $urlParent)->item(0)?->textContent;
                    $changefreq = $xpath->query('sm:changefreq | changefreq', $urlParent)->item(0)?->textContent;

                    $urls[] = [
                        'url' => $url,
                        'lastmod' => $lastmod,
                        'priority' => $priority,
                        'changefreq' => $changefreq,
                    ];
                }
            }

            Log::info("Total URLs extracted from sitemap: " . count($urls));
            return $urls;

        } catch (\Exception $e) {
            Log::error("Sitemap parsing error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Filter URLs based on patterns and content types
     */
    public function filterUrls(array $urls, array $includePatterns = [], array $excludePatterns = []): array
    {
        $filtered = [];

        foreach ($urls as $urlData) {
            $url = is_array($urlData) ? $urlData['url'] : $urlData;

            // Skip if URL matches exclude patterns
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $url) || strpos($url, $pattern) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            // Include if matches include patterns (if any specified)
            if (!empty($includePatterns)) {
                $shouldInclude = false;
                foreach ($includePatterns as $pattern) {
                    if (fnmatch($pattern, $url) || strpos($url, $pattern) !== false) {
                        $shouldInclude = true;
                        break;
                    }
                }

                if (!$shouldInclude) {
                    continue;
                }
            }

            $filtered[] = $urlData;
        }

        Log::info("Filtered URLs: " . count($filtered) . " from " . count($urls));
        return $filtered;
    }

    /**
     * Validate sitemap URL
     */
    public function validateSitemapUrl(string $url): bool
    {
        try {
            // Basic URL validation
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }

            // Check if URL ends with .xml or contains sitemap
            $isXml = str_ends_with(strtolower($url), '.xml');
            $containsSitemap = str_contains(strtolower($url), 'sitemap');

            if (!$isXml && !$containsSitemap) {
                return false;
            }

            // Try to fetch and validate XML structure
            $response = Http::timeout(10)->head($url);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("Sitemap URL validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sitemap statistics
     */
    public function getSitemapStats(string $sitemapUrl): array
    {
        try {
            $urls = $this->parseSitemap($sitemapUrl);

            $stats = [
                'total_urls' => count($urls),
                'url_types' => [],
                'last_modified_range' => [],
                'domains' => [],
            ];

            foreach ($urls as $urlData) {
                $url = is_array($urlData) ? $urlData['url'] : $urlData;
                $parsed = parse_url($url);

                // Count domains
                $domain = $parsed['host'] ?? 'unknown';
                $stats['domains'][$domain] = ($stats['domains'][$domain] ?? 0) + 1;

                // Categorize URL types
                $path = $parsed['path'] ?? '/';
                if (str_contains($path, '/blog/') || str_contains($path, '/post/')) {
                    $stats['url_types']['blog'] = ($stats['url_types']['blog'] ?? 0) + 1;
                } elseif (str_contains($path, '/product/') || str_contains($path, '/shop/')) {
                    $stats['url_types']['product'] = ($stats['url_types']['product'] ?? 0) + 1;
                } elseif (str_contains($path, '/page/')) {
                    $stats['url_types']['page'] = ($stats['url_types']['page'] ?? 0) + 1;
                } else {
                    $stats['url_types']['other'] = ($stats['url_types']['other'] ?? 0) + 1;
                }

                // Track modification dates
                if (is_array($urlData) && isset($urlData['lastmod'])) {
                    $lastmod = $urlData['lastmod'];
                    if (!isset($stats['last_modified_range']['earliest']) || $lastmod < $stats['last_modified_range']['earliest']) {
                        $stats['last_modified_range']['earliest'] = $lastmod;
                    }
                    if (!isset($stats['last_modified_range']['latest']) || $lastmod > $stats['last_modified_range']['latest']) {
                        $stats['last_modified_range']['latest'] = $lastmod;
                    }
                }
            }

            return $stats;

        } catch (\Exception $e) {
            Log::error("Error getting sitemap stats: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'total_urls' => 0,
            ];
        }
    }
}