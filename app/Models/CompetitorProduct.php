<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitorProduct extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'channel_id',
        'channel_type',
        'competitor_name',
        'competitor_url',
        'competitor_product_id',
        'competitor_product_url',
        'competitor_product_title',
        'competitor_product_description',
        'match_confidence',
        'current_price',
        'current_currency',
        'current_shipping_cost',
        'current_total_cost',
        'in_stock',
        'stock_quantity',
        'active',
        'check_frequency_minutes',
        'last_checked_at',
        'last_price_change_at',
        'additional_data',
        'notes',
    ];

    protected $casts = [
        'match_confidence' => 'decimal:2',
        'current_price' => 'decimal:2',
        'current_shipping_cost' => 'decimal:2',
        'current_total_cost' => 'decimal:2',
        'in_stock' => 'boolean',
        'active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_price_change_at' => 'datetime',
        'additional_data' => 'array',
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
     * Get price history
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(CompetitorPrice::class);
    }

    /**
     * Get latest price record
     */
    public function latestPrice(): BelongsTo
    {
        return $this->belongsTo(CompetitorPrice::class, 'id', 'competitor_product_id')
            ->latest('scraped_at');
    }

    /**
     * Update current price from latest scrape
     */
    public function updateCurrentPrice(array $priceData): void
    {
        $this->update([
            'current_price' => $priceData['price'],
            'current_currency' => $priceData['currency'] ?? 'USD',
            'current_shipping_cost' => $priceData['shipping_cost'] ?? null,
            'current_total_cost' => ($priceData['price'] + ($priceData['shipping_cost'] ?? 0)),
            'in_stock' => $priceData['in_stock'] ?? true,
            'stock_quantity' => $priceData['stock_quantity'] ?? null,
            'last_checked_at' => now(),
        ]);

        // Check if price changed
        $latestHistoricalPrice = $this->priceHistory()->latest('scraped_at')->first();
        if ($latestHistoricalPrice && $latestHistoricalPrice->price != $priceData['price']) {
            $this->update(['last_price_change_at' => now()]);
        }
    }

    /**
     * Check if due for price check
     */
    public function isDueForCheck(): bool
    {
        if (!$this->active) {
            return false;
        }

        if (!$this->last_checked_at) {
            return true;
        }

        $minutesSinceLastCheck = now()->diffInMinutes($this->last_checked_at);
        return $minutesSinceLastCheck >= $this->check_frequency_minutes;
    }

    /**
     * Scope to active competitors
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to competitors due for check
     */
    public function scopeDueForCheck($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->whereNull('last_checked_at')
                    ->orWhereRaw('TIMESTAMPDIFF(MINUTE, last_checked_at, NOW()) >= check_frequency_minutes');
            });
    }

    /**
     * Scope by channel type
     */
    public function scopeForChannelType($query, string $channelType)
    {
        return $query->where('channel_type', $channelType);
    }

    /**
     * Scope by product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Get price change percentage since last check
     */
    public function getPriceChangePercent(): ?float
    {
        $latestPrice = $this->priceHistory()->latest('scraped_at')->first();
        if (!$latestPrice || !$latestPrice->price_change_percent) {
            return null;
        }

        return (float) $latestPrice->price_change_percent;
    }

    /**
     * Get price trend (up, down, stable)
     */
    public function getPriceTrend(int $days = 7): string
    {
        $prices = $this->priceHistory()
            ->where('scraped_at', '>=', now()->subDays($days))
            ->orderBy('scraped_at')
            ->pluck('price')
            ->toArray();

        if (count($prices) < 2) {
            return 'stable';
        }

        $firstPrice = $prices[0];
        $lastPrice = $prices[count($prices) - 1];

        $change = (($lastPrice - $firstPrice) / $firstPrice) * 100;

        if ($change > 2) {
            return 'up';
        } elseif ($change < -2) {
            return 'down';
        }

        return 'stable';
    }

    /**
     * Get average price over period
     */
    public function getAveragePrice(int $days = 30): ?float
    {
        $average = $this->priceHistory()
            ->where('scraped_at', '>=', now()->subDays($days))
            ->avg('price');

        return $average ? (float) $average : null;
    }

    /**
     * Get lowest price over period
     */
    public function getLowestPrice(int $days = 30): ?float
    {
        $lowest = $this->priceHistory()
            ->where('scraped_at', '>=', now()->subDays($days))
            ->min('price');

        return $lowest ? (float) $lowest : null;
    }

    /**
     * Get highest price over period
     */
    public function getHighestPrice(int $days = 30): ?float
    {
        $highest = $this->priceHistory()
            ->where('scraped_at', '>=', now()->subDays($days))
            ->max('price');

        return $highest ? (float) $highest : null;
    }
}
