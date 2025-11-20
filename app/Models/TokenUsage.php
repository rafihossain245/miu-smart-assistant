<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chatbot_id',
        'conversation_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
        'model',
        'service_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'cost' => 'decimal:6',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Calculate cost based on OpenAI pricing
     */
    public static function calculateCost(int $promptTokens, int $completionTokens, string $model = 'gpt-3.5-turbo'): float
    {
        // OpenAI pricing (as of 2024)
        $pricing = [
            'gpt-3.5-turbo' => [
                'prompt' => 0.0015 / 1000,  // $0.0015 per 1K tokens
                'completion' => 0.002 / 1000, // $0.002 per 1K tokens
            ],
            'gpt-4' => [
                'prompt' => 0.03 / 1000,   // $0.03 per 1K tokens
                'completion' => 0.06 / 1000, // $0.06 per 1K tokens
            ],
            'gpt-4-turbo' => [
                'prompt' => 0.01 / 1000,   // $0.01 per 1K tokens
                'completion' => 0.03 / 1000, // $0.03 per 1K tokens
            ],
        ];

        $modelPricing = $pricing[$model] ?? $pricing['gpt-3.5-turbo'];

        return ($promptTokens * $modelPricing['prompt']) +
               ($completionTokens * $modelPricing['completion']);
    }
}