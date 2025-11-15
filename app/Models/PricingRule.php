<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingRule extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'channel_id',
        'channel_type',
        'name',
        'description',
        'priority',
        'rule_type',
        'strategy_config',
        'target_competitor_id',
        'competitor_selection',
        'adjustment_value',
        'adjustment_type',
        'min_price',
        'max_price',
        'min_margin_percent',
        'max_margin_percent',
        'cost_basis',
        'conditions',
        'active',
        'auto_apply',
        'require_approval',
        'active_from_time',
        'active_to_time',
        'active_days',
        'max_price_change_percent',
        'max_price_change_amount',
        'max_changes_per_day',
        'last_applied_at',
        'times_applied',
        'last_checked_at',
    ];

    protected $casts = [
        'strategy_config' => 'array',
        'adjustment_value' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'max_margin_percent' => 'decimal:2',
        'cost_basis' => 'decimal:2',
        'conditions' => 'array',
        'active' => 'boolean',
        'auto_apply' => 'boolean',
        'require_approval' => 'boolean',
        'active_days' => 'array',
        'max_price_change_percent' => 'decimal:2',
        'max_price_change_amount' => 'decimal:2',
        'last_applied_at' => 'datetime',
        'last_checked_at' => 'datetime',
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
     * Get the target competitor
     */
    public function targetCompetitor(): BelongsTo
    {
        return $this->belongsTo(CompetitorProduct::class, 'target_competitor_id');
    }

    /**
     * Get price changes made by this rule
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class);
    }

    /**
     * Get price suggestions from this rule
     */
    public function priceSuggestions(): HasMany
    {
        return $this->hasMany(PriceSuggestion::class);
    }

    /**
     * Check if rule is currently active based on schedule
     */
    public function isActiveNow(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = now();

        // Check day of week
        if ($this->active_days && !in_array($now->dayOfWeek, $this->active_days)) {
            return false;
        }

        // Check time of day
        if ($this->active_from_time && $this->active_to_time) {
            $currentTime = $now->format('H:i:s');
            if ($currentTime < $this->active_from_time || $currentTime > $this->active_to_time) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if rule can be applied (hasn't exceeded daily limits)
     */
    public function canApply(): bool
    {
        if (!$this->isActiveNow()) {
            return false;
        }

        if ($this->max_changes_per_day) {
            $todayChanges = $this->priceChanges()
                ->whereDate('created_at', today())
                ->count();

            if ($todayChanges >= $this->max_changes_per_day) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate price against boundaries
     */
    public function validatePrice(float $price, float $currentPrice): array
    {
        $errors = [];

        if ($this->min_price && $price < $this->min_price) {
            $errors[] = "Price {$price} is below minimum {$this->min_price}";
        }

        if ($this->max_price && $price > $this->max_price) {
            $errors[] = "Price {$price} exceeds maximum {$this->max_price}";
        }

        if ($this->max_price_change_percent) {
            $changePercent = abs((($price - $currentPrice) / $currentPrice) * 100);
            if ($changePercent > $this->max_price_change_percent) {
                $errors[] = "Price change {$changePercent}% exceeds maximum {$this->max_price_change_percent}%";
            }
        }

        if ($this->max_price_change_amount) {
            $changeAmount = abs($price - $currentPrice);
            if ($changeAmount > $this->max_price_change_amount) {
                $errors[] = "Price change \${$changeAmount} exceeds maximum \${$this->max_price_change_amount}";
            }
        }

        if ($this->min_margin_percent && $this->cost_basis) {
            $margin = (($price - $this->cost_basis) / $price) * 100;
            if ($margin < $this->min_margin_percent) {
                $errors[] = "Margin {$margin}% is below minimum {$this->min_margin_percent}%";
            }
        }

        return $errors;
    }

    /**
     * Calculate margin percent for a given price
     */
    public function calculateMarginPercent(float $price): ?float
    {
        if (!$this->cost_basis) {
            return null;
        }

        return (($price - $this->cost_basis) / $price) * 100;
    }

    /**
     * Scope to active rules
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to rules for a product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where(function ($q) use ($productId) {
            $q->where('product_id', $productId)
                ->orWhereNull('product_id');
        });
    }

    /**
     * Scope to rules for a variant
     */
    public function scopeForVariant($query, int $variantId)
    {
        return $query->where(function ($q) use ($variantId) {
            $q->where('variant_id', $variantId)
                ->orWhereNull('variant_id');
        });
    }

    /**
     * Scope to rules for a channel
     */
    public function scopeForChannel($query, int $channelId)
    {
        return $query->where(function ($q) use ($channelId) {
            $q->where('channel_id', $channelId)
                ->orWhereNull('channel_id');
        });
    }

    /**
     * Scope to rules ordered by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Increment times applied counter
     */
    public function incrementTimesApplied(): void
    {
        $this->increment('times_applied');
        $this->update(['last_applied_at' => now()]);
    }

    /**
     * Update last checked timestamp
     */
    public function markAsChecked(): void
    {
        $this->update(['last_checked_at' => now()]);
    }
}
