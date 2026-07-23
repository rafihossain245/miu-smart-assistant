<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\ContentExtractionService;
use App\Services\OpenAIService;
use App\Services\SitemapParserService;
use App\Services\YouTubePlaylistService;
use App\Events\SourceProcessingStarted;
use App\Events\SourceProcessingCompleted;
use App\Events\SourceProcessingFailed;
use App\Events\SourceCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessSourceContent implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout
    public int $tries = 3;

    protected Source $source;

    public function __construct(Source $source)
    {
        $this->source = $source;
    }

    public function handle(ContentExtractionService $contentService, OpenAIService $openAIService, SitemapParserService $sitemapService, YouTubePlaylistService $playlistService): void
    {
        try {
            // Mark as processing
            $this->source->update(['status' => 'processing']);

            // Fire processing started event
            event(new SourceProcessingStarted($this->source));

            $extractedData = [];

            // Extract content based on type
            switch ($this->source->type) {
                case 'url':
                    // Use AI-enhanced extraction for URLs to better process products/services
                    $extractedData = $contentService->extractFromUrlWithAISummarization($this->source->url);
                    break;

                case 'pdf':
                    if (Storage::exists($this->source->url)) {
                        $filePath = Storage::path($this->source->url);
                        $extractedData = $contentService->extractFromPdf($filePath);
                    } else {
                        throw new \Exception('PDF file not found');
                    }
                    break;

                case 'image':
                    if (Storage::exists($this->source->url)) {
                        $filePath = Storage::path($this->source->url);
                        $extractedData = $contentService->extractFromImage($filePath);
                    } else {
                        throw new \Exception('Image file not found');
                    }
                    break;

                case 'excel':
                    if (Storage::exists($this->source->url)) {
                        $filePath = Storage::path($this->source->url);
                        $extractedData = $contentService->extractFromExcel($filePath);
                    } else {
                        throw new \Exception('Excel file not found');
                    }
                    break;

                case 'youtube':
                    $extractedData = $contentService->extractFromYoutube($this->source->url);
                    break;

                case 'text':
                case 'technical_issue':
                    $extractedData = [
                        'title' => $this->source->title,
                        'content' => $this->source->content,
                    ];
                    break;

                case 'sitemap':
                    $this->processSitemap($sitemapService, $contentService, $openAIService);
                    return; // Exit early as sitemap creates multiple sources

                case 'youtube_playlist':
                    $playlistService->processPlaylist($this->source);
                    return; // Exit early as playlist creates multiple sources

                default:
                    throw new \Exception('Unknown source type: ' . $this->source->type);
            }

            // Update content if extracted from external source
            if (!in_array($this->source->type, ['text', 'technical_issue'])) {
                $updateData = [
                    'content' => $extractedData['content'],
                ];

                if (empty($this->source->title) || preg_match('/^[a-zA-Z0-9]{40,}$/', $this->source->title)) {
                    $updateData['title'] = $extractedData['title'];
                }

                // Include metadata if available (from AI-enhanced extraction)
                if (isset($extractedData['metadata'])) {
                    $updateData['metadata'] = $extractedData['metadata'];
                }

                // Calculate and set priority score
                $metadata = $extractedData['metadata'] ?? [];
                $priorityScore = $contentService->calculatePriorityScore(
                    $metadata,
                    $this->source->url ?? '',
                    $extractedData['content'] ?? '',
                    $this->source->chatbot_id
                );
                $updateData['priority_score'] = $priorityScore;

                $this->source->update($updateData);
            } else {
                // For text and technical issue sources, calculate priority based on content only
                $priorityScore = $contentService->calculatePriorityScore(
                    [],
                    '',
                    $this->source->content ?? '',
                    $this->source->chatbot_id
                );
                $this->source->update(['priority_score' => $priorityScore]);
            }

            // Chunk content for better embedding
            $chunks = $openAIService->chunkText($extractedData['content'], 1000);

            // Check if this source has already been chunked (has chunks or is a chunk itself)
            $alreadyChunked = $this->source->hasChunks() || $this->source->isChunk();

            if (count($chunks) > 1 && !$alreadyChunked) {
                // This is a multi-chunk document - create parent-child structure
                $parentSourceId = $this->source->id;
                
                foreach ($chunks as $index => $chunk) {
                    if ($index === 0) {
                        // Update current source as first chunk
                        $embedding = $openAIService->generateEmbedding($chunk);
                        $this->source->update([
                            'content' => $chunk,
                            'embedding' => $embedding,
                            'status' => 'completed',
                            'chunk_index' => 0,
                            'total_chunks' => count($chunks),
                            'error_message' => null,
                        ]);

                        // Fire processing completed event
                        event(new SourceProcessingCompleted($this->source));

                        Log::info("Processed chunk {$index} of " . count($chunks) . " for source {$this->source->id}");

                    } else {
                        // Create new sources for remaining chunks
                        $embedding = $openAIService->generateEmbedding($chunk);
                        
                        $chunkSource = Source::create([
                            'chatbot_id' => $this->source->chatbot_id,
                            'parent_source_id' => $parentSourceId,
                            'type' => $this->source->type . '_chunk',
                            'title' => $this->source->title . " (Part " . ($index + 1) . ")",
                            'url' => $this->source->url,
                            'content' => $chunk,
                            'embedding' => $embedding,
                            'status' => 'completed',
                            'chunk_index' => $index,
                            'total_chunks' => count($chunks),
                            'metadata' => [
                                'is_chunk' => true,
                                'parent_id' => $parentSourceId,
                            ]
                        ]);

                        // Fire source created event for new chunk sources
                        event(new SourceCreated($chunkSource));

                        Log::info("Processed chunk {$index} of " . count($chunks) . " for source {$this->source->id} - created chunk source {$chunkSource->id}");
                    }

                }
            } else {
                // Single chunk - process normally
                $contentForEmbedding = !empty($chunks) ? $chunks[0] : $extractedData['content'];
                $embedding = $openAIService->generateEmbedding($contentForEmbedding);
                
                $this->source->update([
                    'embedding' => $embedding,
                    'status' => 'completed',
                    'error_message' => null,
                ]);
            }

            Log::info("Successfully processed source {$this->source->id}");

        } catch (\Exception $e) {
            Log::error("Failed to process source {$this->source->id}: " . $e->getMessage());

            $this->source->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Fire processing failed event
            event(new SourceProcessingFailed($this->source));

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed permanently for source {$this->source->id}: " . $exception->getMessage());

        $this->source->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        // Fire processing failed event
        event(new SourceProcessingFailed($this->source));
    }

    /**
     * Process sitemap by extracting URLs and creating individual sources
     */
    protected function processSitemap(SitemapParserService $sitemapService, ContentExtractionService $contentService, OpenAIService $openAIService): void
    {
        try {
            Log::info("Processing sitemap: {$this->source->url}");

            // Parse sitemap to get URLs
            $urls = $sitemapService->parseSitemap($this->source->url);

            if (empty($urls)) {
                throw new \Exception('No URLs found in sitemap');
            }

            // Filter URLs (exclude common patterns like images, CSS, JS)
            $excludePatterns = [
                '*.css', '*.js', '*.jpg', '*.jpeg', '*.png', '*.gif', '*.svg', '*.ico',
                '*.pdf', '*.zip', '*.xml', '*.txt', '/feed/', '/rss/', '/sitemap'
            ];

            $filteredUrls = $sitemapService->filterUrls($urls, [], $excludePatterns);

            Log::info("Filtered to " . count($filteredUrls) . " URLs from sitemap");

            // Limit to prevent overwhelming the system (max 50 URLs per sitemap)
            $limitedUrls = array_slice($filteredUrls, 0, 50);

            $successCount = 0;
            $failCount = 0;

            // Create individual sources for each URL
            foreach ($limitedUrls as $urlData) {
                $url = is_array($urlData) ? $urlData['url'] : $urlData;

                try {
                    // Check if URL already exists for this chatbot
                    $existingSource = Source::where('chatbot_id', $this->source->chatbot_id)
                        ->where('type', 'url')
                        ->where('url', $url)
                        ->first();

                    if ($existingSource) {
                        Log::info("Skipping existing URL: {$url}");
                        continue;
                    }

                    // Extract content from URL
                    $extractedData = $contentService->extractFromUrl($url);

                    // Calculate priority score
                    $priorityScore = $contentService->calculatePriorityScore(
                        [],
                        $url,
                        $extractedData['content'] ?? '',
                        $this->source->chatbot_id
                    );

                    // Create new source
                    $newSource = Source::create([
                        'chatbot_id' => $this->source->chatbot_id,
                        'type' => 'url',
                        'title' => $extractedData['title'] ?? parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH),
                        'url' => $url,
                        'content' => $extractedData['content'] ?? '',
                        'priority_score' => $priorityScore,
                        'status' => 'processing',
                    ]);

                    // Fire source created event for real-time updates
                    event(new SourceCreated($newSource));

                    // Generate embedding if content is available
                    if (!empty($extractedData['content'])) {
                        $chunks = $openAIService->chunkText($extractedData['content'], 1000);
                        $contentForEmbedding = !empty($chunks) ? $chunks[0] : $extractedData['content'];
                        $embedding = $openAIService->generateEmbedding($contentForEmbedding);

                        $newSource->update([
                            'embedding' => $embedding,
                            'status' => 'completed',
                        ]);

                        $successCount++;
                    } else {
                        $newSource->update([
                            'status' => 'failed',
                            'error_message' => 'No content extracted',
                        ]);
                        $failCount++;
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to process sitemap URL {$url}: " . $e->getMessage());
                    $failCount++;
                }
            }

            // Update the original sitemap source with summary
            $summaryContent = "Sitemap processed successfully.\n\n";
            $summaryContent .= "Total URLs found: " . count($urls) . "\n";
            $summaryContent .= "URLs processed: " . count($limitedUrls) . "\n";
            $summaryContent .= "Successfully created: {$successCount} sources\n";
            $summaryContent .= "Failed: {$failCount} sources\n\n";
            $summaryContent .= "This sitemap source serves as a summary. Individual URL sources have been created for crawled pages.";

            // Generate embedding for summary
            $embedding = $openAIService->generateEmbedding($summaryContent);

            $this->source->update([
                'content' => $summaryContent,
                'embedding' => $embedding,
                'status' => 'completed',
                'error_message' => null,
            ]);

            // Fire processing completed event
            event(new SourceProcessingCompleted($this->source));

            Log::info("Sitemap processing completed. Created {$successCount} sources, {$failCount} failed");

        } catch (\Exception $e) {
            Log::error("Sitemap processing failed: " . $e->getMessage());

            $this->source->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Fire processing failed event
            event(new SourceProcessingFailed($this->source));

            throw $e;
        }
    }
}
