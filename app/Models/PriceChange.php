<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChange extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'channel_id',
        'old_price',
        'new_price',
        'price_difference',
        'price_difference_percent',
        'currency',
        'change_source',
        'pricing_rule_id',
        'user_id',
        'reason',
        'context',
        'status',
        'approved_by_user_id',
        'approved_at',
        'applied_at',
        'sales_before',
        'sales_after',
        'units_sold_before',
        'units_sold_after',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'price_difference_percent' => 'decimal:2',
        'context' => 'array',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'sales_before' => 'decimal:2',
        'sales_after' => 'decimal:2',
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
     * Get the user who made the change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who approved the change
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Check if price increased
     */
    public function isPriceIncrease(): bool
    {
        return $this->price_difference > 0;
    }

    /**
     * Check if price decreased
     */
    public function isPriceDecrease(): bool
    {
        return $this->price_difference < 0;
    }

    /**
     * Get performance impact
     */
    public function getPerformanceImpact(): ?array
    {
        if (!$this->sales_before || !$this->sales_after) {
            return null;
        }

        $salesChange = $this->sales_after - $this->sales_before;
        $salesChangePercent = (($salesChange) / $this->sales_before) * 100;

        $unitsChange = ($this->units_sold_after ?? 0) - ($this->units_sold_before ?? 0);

        return [
            'sales_change' => $salesChange,
            'sales_change_percent' => $salesChangePercent,
            'units_change' => $unitsChange,
            'revenue_per_unit_before' => $this->units_sold_before > 0
                ? $this->sales_before / $this->units_sold_before
                : 0,
            'revenue_per_unit_after' => $this->units_sold_after > 0
                ? $this->sales_after / $this->units_sold_after
                : 0,
        ];
    }

    /**
     * Scope to pending changes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to approved changes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to applied changes
     */
    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    /**
     * Scope to automated changes
     */
    public function scopeAutomated($query)
    {
        return $query->where('change_source', 'automated_rule');
    }

    /**
     * Scope to manual changes
     */
    public function scopeManual($query)
    {
        return $query->where('change_source', 'manual');
    }
}
