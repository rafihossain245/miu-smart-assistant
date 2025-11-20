<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ConversationPattern extends Model
{
    protected $fillable = [
        'chatbot_id',
        'input_pattern',
        'generated_response',
        'context',
        'pattern_type',
        'intent',
        'extracted_entities',
        'input_embedding',
        'response_embedding',
        'similarity_score',
        'usage_count',
        'avg_response_time',
        'quality_score',
        'positive_feedback',
        'negative_feedback',
        'metadata',
        'last_used_at',
    ];

    protected $casts = [
        'extracted_entities' => 'array',
        'input_embedding' => 'array',
        'response_embedding' => 'array',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'similarity_score' => 'decimal:4',
        'avg_response_time' => 'decimal:2',
        'quality_score' => 'decimal:2',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    /**
     * Find similar patterns based on input embedding
     */
    public static function findSimilarPatterns(array $queryEmbedding, int $chatbotId, int $limit = 5, float $threshold = 0.7): \Illuminate\Database\Eloquent\Collection
    {
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->selectRaw('*, (1 - (input_embedding <=> ?::vector)) as similarity', [$embeddingString])
            ->whereRaw('(1 - (input_embedding <=> ?::vector)) >= ?', [$embeddingString, $threshold])
            ->orderByDesc('similarity')
            ->orderByDesc('usage_count')
            ->orderByDesc('quality_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Get top performing patterns for a chatbot
     */
    public static function getTopPatterns(int $chatbotId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->orderByDesc('quality_score')
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Record pattern usage
     */
    public function recordUsage(float $responseTime = null): void
    {
        $this->increment('usage_count');

        if ($responseTime !== null) {
            $currentAvg = $this->avg_response_time ?? 0;
            $newAvg = (($currentAvg * ($this->usage_count - 1)) + $responseTime) / $this->usage_count;
            $this->update([
                'avg_response_time' => $newAvg,
                'last_used_at' => now(),
            ]);
        } else {
            $this->update(['last_used_at' => now()]);
        }
    }

    /**
     * Record feedback for this pattern
     */
    public function recordFeedback(bool $positive): void
    {
        if ($positive) {
            $this->increment('positive_feedback');
        } else {
            $this->increment('negative_feedback');
        }

        // Recalculate quality score
        $totalFeedback = $this->positive_feedback + $this->negative_feedback;
        if ($totalFeedback > 0) {
            $qualityScore = ($this->positive_feedback / $totalFeedback) * 10;
            $this->update(['quality_score' => $qualityScore]);
        }
    }

    /**
     * Get patterns that need improvement (low quality score)
     */
    public static function getPatternsNeedingImprovement(int $chatbotId, float $qualityThreshold = 5.0): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->where('usage_count', '>=', 3) // Only patterns used multiple times
            ->where(function ($query) use ($qualityThreshold) {
                $query->where('quality_score', '<', $qualityThreshold)
                      ->orWhereNull('quality_score');
            })
            ->orderBy('quality_score')
            ->orderByDesc('usage_count')
            ->get();
    }

    /**
     * Clean up old, unused patterns
     */
    public static function cleanupOldPatterns(int $chatbotId, int $daysOld = 30, int $minUsageCount = 5): int
    {
        return self::query()
            ->where('chatbot_id', $chatbotId)
            ->where('usage_count', '<', $minUsageCount)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}