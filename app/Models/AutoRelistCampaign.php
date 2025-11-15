<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoRelistCampaign extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'description',
        'active',
        'channel_ids',
        'min_days_listed',
        'max_views',
        'max_sales',
        'min_view_to_sale_ratio',
        'check_frequency_hours',
        'auto_rewrite_title',
        'auto_fix_description',
        'auto_fix_category',
        'auto_add_attributes',
        'auto_swap_images',
        'auto_adjust_price',
        'auto_relist',
        'require_approval',
        'price_adjustment_strategy',
        'price_adjustment_percent',
        'min_price_floor',
        'max_price_ceiling',
        'optimize_relist_time',
        'preferred_relist_time',
        'preferred_relist_days',
        'max_relists_per_day',
        'max_relists_per_product',
        'products_detected',
        'products_relisted',
        'pending_approval',
        'last_run_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'channel_ids' => 'array',
        'auto_rewrite_title' => 'boolean',
        'auto_fix_description' => 'boolean',
        'auto_fix_category' => 'boolean',
        'auto_add_attributes' => 'boolean',
        'auto_swap_images' => 'boolean',
        'auto_adjust_price' => 'boolean',
        'auto_relist' => 'boolean',
        'require_approval' => 'boolean',
        'price_adjustment_percent' => 'decimal:2',
        'min_price_floor' => 'decimal:2',
        'max_price_ceiling' => 'decimal:2',
        'optimize_relist_time' => 'boolean',
        'preferred_relist_days' => 'array',
        'last_run_at' => 'datetime',
    ];

    /**
     * Get the shop
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get all actions for this campaign
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AutoRelistAction::class, 'campaign_id');
    }

    /**
     * Get pending approval actions
     */
    public function pendingActions(): HasMany
    {
        return $this->actions()->where('status', 'pending_approval');
    }

    /**
     * Get completed actions
     */
    public function completedActions(): HasMany
    {
        return $this->actions()->where('status', 'completed');
    }

    /**
     * Check if campaign is due for run
     */
    public function isDueForRun(): bool
    {
        if (!$this->active) {
            return false;
        }

        if (!$this->last_run_at) {
            return true;
        }

        $hoursSinceLastRun = now()->diffInHours($this->last_run_at);
        return $hoursSinceLastRun >= $this->check_frequency_hours;
    }

    /**
     * Check if can relist more products today
     */
    public function canRelistMoreToday(): bool
    {
        if (!$this->max_relists_per_day) {
            return true;
        }

        $todayRelists = $this->actions()
            ->where('status', 'completed')
            ->whereDate('relisted_at', today())
            ->count();

        return $todayRelists < $this->max_relists_per_day;
    }

    /**
     * Mark campaign as run
     */
    public function markAsRun(): void
    {
        $this->update(['last_run_at' => now()]);
    }

    /**
     * Increment products detected
     */
    public function incrementDetected(): void
    {
        $this->increment('products_detected');
    }

    /**
     * Increment products relisted
     */
    public function incrementRelisted(): void
    {
        $this->increment('products_relisted');
    }

    /**
     * Increment pending approval
     */
    public function incrementPending(): void
    {
        $this->increment('pending_approval');
    }

    /**
     * Decrement pending approval
     */
    public function decrementPending(): void
    {
        $this->decrement('pending_approval');
    }

    /**
     * Get success rate
     */
    public function getSuccessRate(): ?float
    {
        if ($this->products_detected === 0) {
            return null;
        }

        return round(($this->products_relisted / $this->products_detected) * 100, 1);
    }

    /**
     * Scope to active campaigns
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to campaigns due for run
     */
    public function scopeDueForRun($query)
    {
        return $query->where('active', true)
            ->where(function ($q) {
                $q->whereNull('last_run_at')
                    ->orWhereRaw('TIMESTAMPDIFF(HOUR, last_run_at, NOW()) >= check_frequency_hours');
            });
    }

    /**
     * Scope by shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }
}
