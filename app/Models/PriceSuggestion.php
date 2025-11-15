<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSuggestion extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'channel_id',
        'pricing_rule_id',
        'current_price',
        'suggested_price',
        'price_difference',
        'price_difference_percent',
        'currency',
        'reasoning',
        'analysis_data',
        'lowest_competitor_price',
        'highest_competitor_price',
        'average_competitor_price',
        'competitors_checked',
        'current_margin_percent',
        'suggested_margin_percent',
        'current_margin_amount',
        'suggested_margin_amount',
        'confidence_score',
        'priority',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'expires_at',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'suggested_price' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'price_difference_percent' => 'decimal:2',
        'analysis_data' => 'array',
        'lowest_competitor_price' => 'decimal:2',
        'highest_competitor_price' => 'decimal:2',
        'average_competitor_price' => 'decimal:2',
        'current_margin_percent' => 'decimal:2',
        'suggested_margin_percent' => 'decimal:2',
        'current_margin_amount' => 'decimal:2',
        'suggested_margin_amount' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * Get the channel
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the pricing rule
     */
    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    /**
     * Get the user who reviewed
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Approve suggestion and create price change
     */
    public function approve(User $user): PriceChange
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        // Create price change record
        return PriceChange::create([
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'channel_id' => $this->channel_id,
            'old_price' => $this->current_price,
            'new_price' => $this->suggested_price,
            'price_difference' => $this->price_difference,
            'price_difference_percent' => $this->price_difference_percent,
            'currency' => $this->currency,
            'change_source' => 'automated_rule',
            'pricing_rule_id' => $this->pricing_rule_id,
            'user_id' => $user->id,
            'reason' => $this->reasoning,
            'context' => $this->analysis_data,
            'status' => 'applied',
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
            'applied_at' => now(),
        ]);
    }

    /**
     * Reject suggestion
     */
    public function reject(User $user, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Check if suggestion is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    /**
     * Check if price increase
     */
    public function isPriceIncrease(): bool
    {
        return $this->price_difference > 0;
    }

    /**
     * Check if price decrease
     */
    public function isPriceDecrease(): bool
    {
        return $this->price_difference < 0;
    }

    /**
     * Scope to pending suggestions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to high priority suggestions
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    /**
     * Scope to high confidence suggestions
     */
    public function scopeHighConfidence($query, float $threshold = 80.0)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Scope to expired suggestions
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Mark expired suggestions
     */
    public static function markExpired(): int
    {
        return static::expired()->update(['status' => 'expired']);
    }
}
