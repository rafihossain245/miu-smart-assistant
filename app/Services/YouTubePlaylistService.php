<?php

namespace App\Services;

use App\Models\Source;
use App\Jobs\ProcessSourceContent;
use App\Events\SourceProcessingStarted;
use App\Events\SourceProcessingCompleted;
use App\Events\SourceProcessingFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubePlaylistService
{
    /**
     * Extract playlist ID from YouTube URL
     */
    public function extractPlaylistId(string $url): ?string
    {
        // Parse different YouTube playlist URL formats
        $patterns = [
            '/[?&]list=([a-zA-Z0-9_-]+)/',  // Standard playlist URL
            '/playlist\?list=([a-zA-Z0-9_-]+)/', // Direct playlist URL
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Get playlist videos using YouTube Data API or web scraping
     */
    public function getPlaylistVideos(string $playlistId): array
    {
        try {
            // For demo purposes, we'll use a simple web scraping approach
            // In production, you should use YouTube Data API v3
            return $this->scrapePlaylistVideos($playlistId);
        } catch (\Exception $e) {
            Log::error('YouTube playlist extraction error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Scrape playlist videos from YouTube page
     */
    private function scrapePlaylistVideos(string $playlistId): array
    {
        $playlistUrl = "https://www.youtube.com/playlist?list={$playlistId}";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ])
                ->get($playlistUrl);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch playlist page');
            }

            $html = $response->body();
            $videos = [];

            // Extract video information using regex patterns
            // This is a simplified approach - in production, consider using YouTube API

            // Pattern to match video data in the page
            if (preg_match('/var ytInitialData = ({.*?});/', $html, $matches)) {
                $jsonData = $matches[1];
                $data = json_decode($jsonData, true);

                if ($data && isset($data['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['playlistVideoListRenderer']['contents'])) {
                    $videoList = $data['contents']['twoColumnBrowseResultsRenderer']['tabs'][0]['tabRenderer']['content']['sectionListRenderer']['contents'][0]['itemSectionRenderer']['contents'][0]['playlistVideoListRenderer']['contents'];

                    foreach ($videoList as $item) {
                        if (isset($item['playlistVideoRenderer'])) {
                            $video = $item['playlistVideoRenderer'];
                            if (isset($video['videoId']) && isset($video['title']['runs'][0]['text'])) {
                                $videos[] = [
                                    'video_id' => $video['videoId'],
                                    'title' => $video['title']['runs'][0]['text'],
                                    'url' => "https://www.youtube.com/watch?v=" . $video['videoId'],
                                ];
                            }
                        }
                    }
                }
            }

            // Fallback: try to extract video IDs using simpler regex
            if (empty($videos)) {
                if (preg_match_all('/"videoId":"([a-zA-Z0-9_-]{11})"/', $html, $matches)) {
                    $videoIds = array_unique($matches[1]);

                    foreach (array_slice($videoIds, 0, 50) as $index => $videoId) { // Limit to 50 videos
                        $videos[] = [
                            'video_id' => $videoId,
                            'title' => "Video " . ($index + 1) . " from playlist",
                            'url' => "https://www.youtube.com/watch?v=" . $videoId,
                        ];
                    }
                }
            }

            // Extract playlist title
            $playlistTitle = 'YouTube Playlist';
            if (preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatches)) {
                $playlistTitle = trim(str_replace(' - YouTube', '', $titleMatches[1]));
            }

            return [
                'title' => $playlistTitle,
                'videos' => array_slice($videos, 0, 50), // Limit to 50 videos to prevent system overload
            ];

        } catch (\Exception $e) {
            Log::error('Error scraping YouTube playlist: ' . $e->getMessage());

            // Return empty result rather than throwing exception
            return [
                'title' => 'YouTube Playlist',
                'videos' => [],
            ];
        }
    }

    /**
     * Process playlist by creating individual video sources
     */
    public function processPlaylist(Source $playlistSource): void
    {
        try {
            // Fire processing started event
            event(new SourceProcessingStarted($playlistSource));

            $playlistId = $this->extractPlaylistId($playlistSource->url);

            if (!$playlistId) {
                throw new \Exception('Invalid YouTube playlist URL');
            }

            $playlistData = $this->getPlaylistVideos($playlistId);

            if (empty($playlistData['videos'])) {
                throw new \Exception('No videos found in playlist or playlist is private');
            }

            // Update the playlist source with summary info
            $videoCount = count($playlistData['videos']);
            $playlistSource->update([
                'title' => $playlistData['title'],
                'content' => "YouTube Playlist: {$playlistData['title']}\nContains {$videoCount} videos\nProcessing each video as individual source...",
                'status' => 'processing'
            ]);

            // Create individual sources for each video
            foreach ($playlistData['videos'] as $video) {
                $videoSource = Source::create([
                    'chatbot_id' => $playlistSource->chatbot_id,
                    'type' => 'youtube',
                    'title' => $video['title'],
                    'url' => $video['url'],
                    'content' => '',
                    'status' => 'pending',
                ]);

                // Dispatch job to process each video
                ProcessSourceContent::dispatch($videoSource);
            }

            // Mark playlist source as completed
            $playlistSource->update([
                'status' => 'completed',
                'content' => "YouTube Playlist: {$playlistData['title']}\nProcessed {$videoCount} videos successfully.",
                'error_message' => null,
            ]);

            // Fire processing completed event
            event(new SourceProcessingCompleted($playlistSource));

        } catch (\Exception $e) {
            Log::error('YouTube playlist processing error: ' . $e->getMessage());

            $playlistSource->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Fire processing failed event
            event(new SourceProcessingFailed($playlistSource));

            throw $e;
        }
    }
}