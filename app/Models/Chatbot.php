<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chatbot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'appearance',
        'welcome_message',
        'initial_message',
        'is_active',
        // Advanced settings
        'service_urls',
        'customer_personas',
        'brand_voice',
        'customer_query_examples',
        'integrations',
        'conversation_memory_enabled',
        'conversation_memory_limit',
        'lead_qualification_enabled',
        'qualification_fields',
        'smart_handoff_enabled',
        'handoff_triggers',
        'contact_settings',
        'metadata',
    ];

    protected $casts = [
        'appearance' => 'array',
        'is_active' => 'boolean',
        'service_urls' => 'array',
        'customer_personas' => 'array',
        'customer_query_examples' => 'array',
        'integrations' => 'array',
        'qualification_fields' => 'array',
        'handoff_triggers' => 'array',
        'contact_settings' => 'array',
        'metadata' => 'array',
        'conversation_memory_enabled' => 'boolean',
        'lead_qualification_enabled' => 'boolean',
        'smart_handoff_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function tokenUsage(): HasMany
    {
        return $this->hasMany(TokenUsage::class);
    }

    public function getCompletedSourcesCountAttribute(): int
    {
        return $this->sources()->where('status', 'completed')->count();
    }

    public function getTotalConversationsCountAttribute(): int
    {
        return $this->conversations()->count();
    }

    public function getTotalTokensUsedAttribute(): int
    {
        return $this->tokenUsage()->sum('total_tokens');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->tokenUsage()->sum('cost');
    }
}
