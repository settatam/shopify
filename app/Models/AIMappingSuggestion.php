<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIMappingSuggestion extends Model
{
    protected $fillable = [
        'product_id',
        'channel_id',
        'status',
        'suggested_mapping',
        'channel_category_suggestion',
        'ai_reasoning',
        'confidence_score',
        'metadata',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'suggested_mapping' => 'array',
        'channel_category_suggestion' => 'array',
        'metadata' => 'array',
        'confidence_score' => 'float',
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if suggestion is high confidence (>= 0.8)
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence_score >= 0.8;
    }

    /**
     * Check if suggestion has warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->metadata['warnings'] ?? []);
    }

    /**
     * Get warnings array
     */
    public function getWarnings(): array
    {
        return $this->metadata['warnings'] ?? [];
    }
}
