<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'chunk_index',
        'parent_source_id',
        'total_chunks',
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

    /**
     * Check if this source is a chunk (has a parent)
     */
    public function isChunk(): bool
    {
        return $this->parent_source_id !== null;
    }

    /**
     * Check if this source has child chunks
     */
    public function hasChunks(): bool
    {
        return static::where('parent_source_id', $this->id)->exists();
    }

    /**
     * Get the parent source relationship
     */
    public function parentSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'parent_source_id');
    }

    /**
     * Get the child chunks relationship
     */
    public function chunks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Source::class, 'parent_source_id');
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
