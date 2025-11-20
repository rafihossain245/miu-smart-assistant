<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = [
        'chatbot_id',
        'name',
        'type',
        'description',
        'short_description',
        'pricing',
        'features',
        'specifications',
        'primary_url',
        'demo_url',
        'documentation_url',
        'additional_urls',
        'image_url',
        'gallery_urls',
        'video_url',
        'target_audience',
        'use_cases',
        'industries',
        'ai_summary',
        'common_questions',
        'key_benefits',
        'competitors',
        'keywords',
        'meta_description',
        'tags',
        'description_embedding',
        'features_embedding',
        'is_active',
        'is_featured',
        'mention_count',
        'conversion_score',
        'metadata',
        'sort_order',
    ];

    protected $casts = [
        'pricing' => 'array',
        'features' => 'array',
        'specifications' => 'array',
        'additional_urls' => 'array',
        'gallery_urls' => 'array',
        'target_audience' => 'array',
        'use_cases' => 'array',
        'industries' => 'array',
        'common_questions' => 'array',
        'key_benefits' => 'array',
        'competitors' => 'array',
        'keywords' => 'array',
        'tags' => 'array',
        'description_embedding' => 'array',
        'features_embedding' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'conversion_score' => 'decimal:2',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    /**
     * Find similar products based on description embedding
     */
    public static function findSimilarProducts(array $queryEmbedding, int $chatbotId, int $limit = 3, float $threshold = 0.7): \Illuminate\Database\Eloquent\Collection
    {
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->where('is_active', true)
            ->selectRaw('*, (1 - (description_embedding <=> ?::vector)) as similarity', [$embeddingString])
            ->whereRaw('(1 - (description_embedding <=> ?::vector)) >= ?', [$embeddingString, $threshold])
            ->orderByDesc('similarity')
            ->orderByDesc('is_featured')
            ->orderByDesc('mention_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Search products by keywords
     */
    public static function searchByKeywords(string $query, int $chatbotId): \Illuminate\Database\Eloquent\Collection
    {
        $keywords = explode(' ', strtolower(trim($query)));

        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->where('is_active', true)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('name', 'ILIKE', "%{$keyword}%")
                      ->orWhere('description', 'ILIKE', "%{$keyword}%")
                      ->orWhere('short_description', 'ILIKE', "%{$keyword}%")
                      ->orWhereJsonContains('keywords', $keyword)
                      ->orWhereJsonContains('tags', $keyword);
                }
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('mention_count')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get featured products for chatbot
     */
    public static function getFeaturedProducts(int $chatbotId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderByDesc('mention_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get products by type
     */
    public static function getByType(int $chatbotId, string $type = 'product'): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Record product mention in conversation
     */
    public function recordMention(bool $successful = false): void
    {
        $this->increment('mention_count');

        if ($successful && $this->mention_count > 0) {
            // Update conversion score
            $currentScore = $this->conversion_score ?? 0;
            $newScore = (($currentScore * ($this->mention_count - 1)) + ($successful ? 100 : 0)) / $this->mention_count;
            $this->update(['conversion_score' => $newScore]);
        }
    }

    /**
     * Get AI-friendly summary for chatbot responses
     */
    public function getAiSummary(): string
    {
        if ($this->ai_summary) {
            return $this->ai_summary;
        }

        // Generate summary from available data
        $summary = "**{$this->name}** ({$this->type}): " . ($this->short_description ?: substr($this->description, 0, 200) . '...');

        if ($this->key_benefits) {
            $summary .= "\n\nKey Benefits: " . implode(', ', array_slice($this->key_benefits, 0, 3));
        }

        if ($this->primary_url) {
            $summary .= "\n\nLearn more: {$this->primary_url}";
        }

        return $summary;
    }

    /**
     * Get analytics for products
     */
    public static function getAnalytics(int $chatbotId): array
    {
        $products = self::where('chatbot_id', $chatbotId)->get();

        return [
            'total_products' => $products->where('type', 'product')->count(),
            'total_services' => $products->where('type', 'service')->count(),
            'featured_count' => $products->where('is_featured', true)->count(),
            'total_mentions' => $products->sum('mention_count'),
            'avg_conversion_score' => $products->avg('conversion_score'),
            'top_performing' => $products->sortByDesc('mention_count')->take(5)->values(),
            'least_mentioned' => $products->where('mention_count', 0)->count(),
        ];
    }
}