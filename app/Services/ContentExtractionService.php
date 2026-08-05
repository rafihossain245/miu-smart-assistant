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

    /**
     * Strip byte sequences that aren't valid UTF-8 (e.g. lone/CESU-8-encoded
     * surrogate halves from mis-encoded emoji in Excel/PDF/Office documents),
     * which Postgres otherwise rejects outright at insert time.
     */
    public function sanitizeUtf8(string $text): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        if ($clean === false) {
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\xED[\xA0-\xBF][\x80-\xBF]/', '', $text);
        }

        return $clean ?? '';
    }

    /**
     * Below this many characters an extraction is treated as having failed rather
     * than as a genuinely short page - it almost always means the crawler latched
     * onto the wrong element (a modal header, a cookie banner) instead of the page.
     */
    public const MIN_EXTRACTED_CONTENT_LENGTH = 100;

    /**
     * Elements that never carry page content. Stripped before scoring so a long
     * nav menu can't out-score the real article.
     */
    protected const NOISE_TAGS = [
        'script', 'style', 'noscript', 'iframe', 'svg', 'form',
        'nav', 'header', 'footer', 'aside',
    ];

    /**
     * class/id fragments that mark chrome rather than content. Matched
     * case-insensitively against both attributes.
     */
    protected const NOISE_PATTERNS = [
        'modal', 'popup', 'cookie', 'consent', 'breadcrumb', 'sidebar', 'widget',
        'menu', 'navbar', 'navigation', 'offcanvas', 'dropdown', 'social',
        'share', 'comment', 'pagination', 'skip-link', 'screen-reader',
    ];

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

            if (trim($html) === '') {
                throw new \Exception('Fetched URL returned an empty body');
            }

            $xpath = new \DOMXPath($this->loadHtmlDocument($html));
            $title = $this->extractDocumentTitle($xpath);

            $this->stripNoiseNodes($xpath);
            $content = $this->extractMainContent($xpath);

            // Themes name their wrappers unpredictably, so the chrome filter can
            // occasionally swallow the page. Re-parse and strip structural tags
            // only rather than reporting a page as empty.
            if (strlen($content) < self::MIN_EXTRACTED_CONTENT_LENGTH) {
                $retryXpath = new \DOMXPath($this->loadHtmlDocument($html));
                $this->stripNoiseNodes($retryXpath, includeClassPatterns: false);
                $retryContent = $this->extractMainContent($retryXpath);

                if (strlen($retryContent) > strlen($content)) {
                    Log::info('URL extraction fell back to tag-only filtering', [
                        'url' => $url,
                        'filtered_length' => strlen($content),
                        'fallback_length' => strlen($retryContent),
                    ]);

                    $content = $retryContent;
                }
            }

            return [
                'title' => $title,
                'content' => $content,
            ];

        } catch (\Exception $e) {
            Log::error('URL extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse HTML into a DOMDocument. Unlike regex matching this understands
     * nesting, so a container's closing tag is its own rather than the first
     * </div> that happens to follow.
     */
    protected function loadHtmlDocument(string $html): \DOMDocument
    {
        $document = new \DOMDocument();

        // Malformed markup is the norm on the open web - collect the warnings
        // instead of letting them surface, and parse whatever we can.
        $previous = libxml_use_internal_errors(true);

        // Force UTF-8: without a hint libxml assumes ISO-8859-1 and mangles
        // any non-ASCII text. mb_convert_encoding's HTML-ENTITIES mode is
        // deprecated as of PHP 8.2, so declare the encoding inline instead.
        $document->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOWARNING | LIBXML_NOERROR
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    protected function extractDocumentTitle(\DOMXPath $xpath): string
    {
        $candidates = [
            '//meta[@property="og:title"]/@content',
            '//title',
            '//h1',
        ];

        foreach ($candidates as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : '';

            if ($value !== '') {
                return $value;
            }
        }

        return 'Untitled';
    }

    /**
     * Drop chrome from the tree so only candidate content remains.
     *
     * @param bool $includeClassPatterns false strips structural tags only - the
     *        conservative pass used when the aggressive one leaves nothing behind.
     */
    protected function stripNoiseNodes(\DOMXPath $xpath, bool $includeClassPatterns = true): void
    {
        $removals = [];

        foreach (self::NOISE_TAGS as $tag) {
            foreach ($xpath->query('//' . $tag) as $node) {
                $removals[] = $node;
            }
        }

        if ($includeClassPatterns) {
            foreach (self::NOISE_PATTERNS as $pattern) {
                foreach ($xpath->query($this->noiseAttributeQuery($pattern)) as $node) {
                    $removals[] = $node;
                }
            }
        }

        foreach ($removals as $node) {
            // Never unhook the page itself - some themes put theme-name classes on
            // <body> (e.g. "mega-menu-main"), and a substring match there would
            // otherwise take the entire document with it.
            if (in_array(strtolower($node->nodeName), ['html', 'body'], true)) {
                continue;
            }

            // A node removed as part of an earlier subtree has no parent left.
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * Match elements whose class/id contains a *token starting with* the pattern.
     *
     * Padding the attribute with spaces anchors the match to a token boundary, so
     * "modal" hits "modal-content" but not "mega-menu-toggle" - plain substring
     * matching conflates the two and strips real content.
     */
    protected function noiseAttributeQuery(string $pattern): string
    {
        $lower = fn (string $attr) => sprintf(
            'concat(" ", translate(normalize-space(@%s), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), " ")',
            $attr
        );

        return sprintf(
            '//*[contains(%s, " %s") or contains(%s, " %s")]',
            $lower('class'),
            $pattern,
            $lower('id'),
            $pattern
        );
    }

    /**
     * Pick the densest content container. Rather than trusting the first
     * selector that matches, score every candidate by text length and take the
     * winner - a page whose real content sits in an unusual wrapper still works,
     * and an empty <main> can't shadow the article beneath it.
     */
    protected function extractMainContent(\DOMXPath $xpath): string
    {
        $candidateQueries = [
            '//article',
            '//main',
            '//*[@role="main"]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "entry-content")]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "post-content")]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "page-content")]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "site-content")]',
            '//*[contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "content")]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "content")]',
        ];

        $best = '';

        foreach ($candidateQueries as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $text = $this->nodeToText($node);

                if (strlen($text) > strlen($best)) {
                    $best = $text;
                }
            }
        }

        // Nothing scored - fall back to the whole body, which is still sound now
        // that navigation and modals have been stripped out.
        if (strlen($best) < self::MIN_EXTRACTED_CONTENT_LENGTH) {
            $body = $xpath->query('//body')?->item(0);
            $bodyText = $body ? $this->nodeToText($body) : '';

            if (strlen($bodyText) > strlen($best)) {
                $best = $bodyText;
            }
        }

        return $best;
    }

    /**
     * Flatten a node to readable text, keeping block-level boundaries so
     * headings and list items don't run into the words that follow them.
     */
    protected function nodeToText(\DOMNode $node): string
    {
        $blockTags = [
            'p', 'div', 'br', 'li', 'tr', 'section', 'table',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        ];

        $document = $node->ownerDocument;
        $html = $document ? $document->saveHTML($node) : '';

        if ($html === false || $html === '') {
            return '';
        }

        $html = preg_replace('/<(' . implode('|', $blockTags) . ')\b[^>]*>/i', "\n$0", $html);
        $html = preg_replace('/<\/(' . implode('|', $blockTags) . ')>/i', "$0\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalise line endings first - a stray \r sits between newlines and stops
        // the blank-line collapsing below from seeing them as consecutive.
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Collapse runs of spaces/tabs, then runs of blank lines, keeping
        // paragraph breaks that make the text readable in a prompt.
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ *\n *(?:\n *)+/u', "\n\n", $text);
        $text = preg_replace('/ *\n */u', "\n", $text);

        return trim($text);
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
                        try {
                            $value = $cell->getCalculatedValue();
                        } catch (\Exception $e) {
                            $value = $cell->getValue();
                        }
                        if ($value !== null && $value !== '') {
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