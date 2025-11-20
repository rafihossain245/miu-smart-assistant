<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Source extends Model
{
    protected $fillable = [
        'chatbot_id',
        'type',
        'title',
        'url',
        'content',
        'embedding',
        'status',
        'error_message',
        'metadata',
        'priority_score',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public static function findSimilar(array $queryEmbedding, int $chatbotId, int $limit = 5): \Illuminate\Support\Collection
    {
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

        return DB::table('sources')
            ->select(['id', 'title', 'content', 'url', 'type', 'priority_score', 'metadata'])
            ->selectRaw('(embedding <=> ?) as distance', [$embeddingString])
            ->selectRaw('(embedding <=> ?) * (1.0 - (priority_score / 100.0)) as weighted_score', [$embeddingString])
            ->where('chatbot_id', $chatbotId)
            ->where('status', 'completed')
            ->whereNotNull('embedding')
            ->orderBy('weighted_score')
            ->limit($limit)
            ->get();
    }

    public static function findSimilarForTechnicalIssues(array $queryEmbedding, int $chatbotId, int $limit = 5): \Illuminate\Support\Collection
    {
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

        // First, try to find technical issue sources
        $technicalSources = DB::table('sources')
            ->select(['id', 'title', 'content', 'url', 'type', 'priority_score', 'metadata'])
            ->selectRaw('(embedding <=> ?) as distance', [$embeddingString])
            ->selectRaw('(embedding <=> ?) * 0.7 as weighted_score', [$embeddingString]) // Boost technical issue sources
            ->where('chatbot_id', $chatbotId)
            ->where('status', 'completed')
            ->where('type', 'technical_issue')
            ->whereNotNull('embedding')
            ->orderBy('weighted_score')
            ->limit(3) // Get top 3 technical solutions
            ->get();

        // If we have technical sources, include some general sources too
        $remainingLimit = $limit - $technicalSources->count();
        if ($remainingLimit > 0) {
            $generalSources = DB::table('sources')
                ->select(['id', 'title', 'content', 'url', 'type', 'priority_score', 'metadata'])
                ->selectRaw('(embedding <=> ?) as distance', [$embeddingString])
                ->selectRaw('(embedding <=> ?) * (1.0 - (priority_score / 100.0)) as weighted_score', [$embeddingString])
                ->where('chatbot_id', $chatbotId)
                ->where('status', 'completed')
                ->where('type', '!=', 'technical_issue')
                ->whereNotNull('embedding')
                ->orderBy('weighted_score')
                ->limit($remainingLimit)
                ->get();

            return $technicalSources->concat($generalSources);
        }

        return $technicalSources;
    }
}
