<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorPrice extends Model
{
    protected $fillable = [
        'competitor_product_id',
        'price',
        'currency',
        'shipping_cost',
        'total_cost',
        'in_stock',
        'stock_quantity',
        'previous_price',
        'price_change',
        'price_change_percent',
        'additional_data',
        'scraped_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'in_stock' => 'boolean',
        'previous_price' => 'decimal:2',
        'price_change' => 'decimal:2',
        'price_change_percent' => 'decimal:2',
        'additional_data' => 'array',
        'scraped_at' => 'datetime',
    ];

    /**
     * Get the competitor product
     */
    public function competitorProduct(): BelongsTo
    {
        return $this->belongsTo(CompetitorProduct::class);
    }

    /**
     * Check if price increased
     */
    public function priceIncreased(): bool
    {
        return $this->price_change > 0;
    }

    /**
     * Check if price decreased
     */
    public function priceDecreased(): bool
    {
        return $this->price_change < 0;
    }

    /**
     * Check if significant price change (>5%)
     */
    public function hasSignificantChange(float $threshold = 5.0): bool
    {
        return abs($this->price_change_percent) >= $threshold;
    }
}
