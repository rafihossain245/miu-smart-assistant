<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotLearningData extends Model
{
    protected $fillable = [
        'chatbot_id',
        'conversation_id',
        'user_query',
        'bot_response',
        'context_used',
        'user_feedback',
        'user_correction',
        'similarity_score',
        'response_time_ms',
        'query_patterns',
        'behavior_data',
        'interaction_type',
        'was_helpful',
    ];

    protected $casts = [
        'context_used' => 'array',
        'user_feedback' => 'array',
        'query_patterns' => 'array',
        'behavior_data' => 'array',
        'was_helpful' => 'boolean',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // Scope for getting learning data for analysis
    public function scopeForChatbot($query, int $chatbotId)
    {
        return $query->where('chatbot_id', $chatbotId);
    }

    public function scopeWithFeedback($query)
    {
        return $query->whereNotNull('user_feedback');
    }

    public function scopeWithCorrections($query)
    {
        return $query->whereNotNull('user_correction');
    }

    public function scopeHelpful($query)
    {
        return $query->where('was_helpful', true);
    }

    public function scopeNotHelpful($query)
    {
        return $query->where('was_helpful', false);
    }
}