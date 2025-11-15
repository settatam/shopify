<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoRelistAction extends Model
{
    protected $fillable = [
        'campaign_id',
        'product_id',
        'channel_id',
        'days_listed',
        'total_views',
        'total_sales',
        'view_to_sale_ratio',
        'detected_at',
        'status',
        'original_title',
        'original_description',
        'original_category_id',
        'original_attributes',
        'original_images',
        'original_price',
        'original_listed_at',
        'new_title',
        'new_description',
        'new_category_id',
        'new_attributes',
        'new_images',
        'new_price',
        'relist_scheduled_at',
        'ai_analysis',
        'changes_made',
        'optimization_reasoning',
        'views_before',
        'views_after',
        'sales_before',
        'sales_after',
        'conversion_rate_before',
        'conversion_rate_after',
        'failure_reason',
        'approved_by_user_id',
        'approved_at',
        'relisted_at',
        'completed_at',
    ];

    protected $casts = [
        'view_to_sale_ratio' => 'decimal:2',
        'detected_at' => 'datetime',
        'original_attributes' => 'array',
        'original_images' => 'array',
        'original_price' => 'decimal:2',
        'original_listed_at' => 'datetime',
        'new_attributes' => 'array',
        'new_images' => 'array',
        'new_price' => 'decimal:2',
        'relist_scheduled_at' => 'datetime',
        'ai_analysis' => 'array',
        'changes_made' => 'array',
        'conversion_rate_before' => 'decimal:2',
        'conversion_rate_after' => 'decimal:2',
        'approved_at' => 'datetime',
        'relisted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AutoRelistCampaign::class, 'campaign_id');
    }

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the channel
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the user who approved
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Approve the action
     */
    public function approve(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        $this->campaign->decrementPending();
    }

    /**
     * Reject the action
     */
    public function reject(User $user, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'failure_reason' => $reason,
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        $this->campaign->decrementPending();
    }

    /**
     * Mark as relisted
     */
    public function markRelisted(): void
    {
        $this->update([
            'status' => 'completed',
            'relisted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->campaign->incrementRelisted();
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get improvement percentage
     */
    public function getImprovementPercentage(): ?float
    {
        if (!$this->conversion_rate_before || !$this->conversion_rate_after) {
            return null;
        }

        if ($this->conversion_rate_before == 0) {
            return 100; // Any conversion is 100% improvement
        }

        $improvement = (($this->conversion_rate_after - $this->conversion_rate_before) / $this->conversion_rate_before) * 100;
        return round($improvement, 1);
    }

    /**
     * Check if successful (has sales after relist)
     */
    public function isSuccessful(): bool
    {
        return $this->sales_after > 0;
    }

    /**
     * Get changes summary
     */
    public function getChangesSummary(): array
    {
        $changes = [];

        if ($this->new_title !== $this->original_title) {
            $changes[] = 'Title rewritten';
        }

        if ($this->new_description !== $this->original_description) {
            $changes[] = 'Description improved';
        }

        if ($this->new_category_id !== $this->original_category_id) {
            $changes[] = 'Category changed';
        }

        if ($this->new_price !== $this->original_price) {
            $priceDiff = $this->new_price - $this->original_price;
            $direction = $priceDiff > 0 ? 'increased' : 'decreased';
            $changes[] = "Price {$direction} by $" . abs($priceDiff);
        }

        if ($this->new_images !== $this->original_images) {
            $changes[] = 'Images reordered';
        }

        return $changes;
    }

    /**
     * Scope to pending approval
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    /**
     * Scope to approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to successful (has sales after relist)
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed')
            ->where('sales_after', '>', 0);
    }
}
