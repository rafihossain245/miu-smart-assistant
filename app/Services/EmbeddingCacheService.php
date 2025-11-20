<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmbeddingCacheService
{
    protected OpenAIService $openAIService;
    protected int $cacheTimeout = 86400; // 24 hours

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Get embedding from cache or generate new one
     */
    public function getOrGenerateEmbedding(string $text, string $model = 'text-embedding-3-small'): array
    {
        // Create a unique cache key based on text content and model
        $cacheKey = 'embedding:' . hash('sha256', $text . ':' . $model);

        return Cache::remember($cacheKey, $this->cacheTimeout, function() use ($text, $model) {
            Log::info('Generating new embedding (cache miss)', [
                'text_length' => strlen($text),
                'model' => $model
            ]);

            return $this->openAIService->generateEmbedding($text);
        });
    }

    /**
     * Batch get or generate multiple embeddings
     */
    public function getOrGenerateEmbeddingsBatch(array $texts, string $model = 'text-embedding-3-small'): array
    {
        $results = [];
        $textsToGenerate = [];

        // Check cache for each text
        foreach ($texts as $index => $text) {
            $cacheKey = 'embedding:' . hash('sha256', $text . ':' . $model);
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$index] = $cached;
            } else {
                $textsToGenerate[$index] = $text;
            }
        }

        // Generate embeddings for cache misses
        if (!empty($textsToGenerate)) {
            Log::info('Generating embeddings for cache misses', [
                'count' => count($textsToGenerate),
                'model' => $model
            ]);

            foreach ($textsToGenerate as $index => $text) {
                $embedding = $this->openAIService->generateEmbedding($text);
                $cacheKey = 'embedding:' . hash('sha256', $text . ':' . $model);

                Cache::put($cacheKey, $embedding, $this->cacheTimeout);
                $results[$index] = $embedding;
            }
        }

        // Sort results by original index
        ksort($results);
        return array_values($results);
    }

    /**
     * Preload embeddings for common queries
     */
    public function preloadCommonEmbeddings(array $commonTexts, string $model = 'text-embedding-3-small'): void
    {
        $uncachedTexts = [];

        foreach ($commonTexts as $text) {
            $cacheKey = 'embedding:' . hash('sha256', $text . ':' . $model);
            if (!Cache::has($cacheKey)) {
                $uncachedTexts[] = $text;
            }
        }

        if (!empty($uncachedTexts)) {
            Log::info('Preloading embeddings for common queries', [
                'count' => count($uncachedTexts)
            ]);

            $this->getOrGenerateEmbeddingsBatch($uncachedTexts, $model);
        }
    }

    /**
     * Clear embedding cache
     */
    public function clearCache(): void
    {
        // Note: This is a simple implementation. In production, you might want
        // to use cache tags or a more sophisticated approach
        Cache::flush();
        Log::info('Embedding cache cleared');
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // This would need to be implemented based on your cache driver
        // For now, return basic info
        return [
            'cache_driver' => config('cache.default'),
            'cache_timeout' => $this->cacheTimeout,
            'message' => 'Cache statistics would need cache driver specific implementation'
        ];
    }

    /**
     * Set cache timeout
     */
    public function setCacheTimeout(int $seconds): void
    {
        $this->cacheTimeout = $seconds;
    }
}