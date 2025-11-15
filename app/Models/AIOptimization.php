<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIOptimization extends Model
{
    protected $fillable = [
        'product_id',
        'channel_id',
        'channel_type',
        'optimization_type',
        'original_title',
        'original_description',
        'optimized_title',
        'optimized_description',
        'quality_score',
        'ai_reasoning',
        'improvements_made',
        'keywords_added',
        'channel_guidelines',
        'original_title_length',
        'optimized_title_length',
        'original_description_length',
        'optimized_description_length',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'final_title',
        'final_description',
        'product_data_used',
        'rejection_reason',
        'applied_to_product',
        'applied_at',
    ];

    protected $casts = [
        'quality_score' => 'decimal:2',
        'improvements_made' => 'array',
        'keywords_added' => 'array',
        'channel_guidelines' => 'array',
        'product_data_used' => 'array',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
        'applied_to_product' => 'boolean',
    ];

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
     * Get the user who reviewed
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Approve the optimization
     */
    public function approve(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'final_title' => $this->optimized_title,
            'final_description' => $this->optimized_description,
        ]);
    }

    /**
     * Reject the optimization
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
     * Modify and approve with different content
     */
    public function modify(User $user, ?string $title = null, ?string $description = null): void
    {
        $this->update([
            'status' => 'modified',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'final_title' => $title ?? $this->optimized_title,
            'final_description' => $description ?? $this->optimized_description,
        ]);
    }

    /**
     * Apply optimization to product
     */
    public function applyToProduct(): void
    {
        if (!$this->isApproved()) {
            throw new \Exception('Cannot apply unapproved optimization');
        }

        // Update product with optimized content
        // This would typically update a channel-specific field or mapping
        $this->update([
            'applied_to_product' => true,
            'applied_at' => now(),
        ]);
    }

    /**
     * Check if optimization is approved
     */
    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'modified']);
    }

    /**
     * Check if optimization is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Scope to pending optimizations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to approved optimizations
     */
    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'modified']);
    }

    /**
     * Scope by channel type
     */
    public function scopeForChannelType($query, string $channelType)
    {
        return $query->where('channel_type', $channelType);
    }

    /**
     * Scope by optimization type
     */
    public function scopeOptimizationType($query, string $type)
    {
        return $query->where('optimization_type', $type);
    }

    /**
     * Get title for display
     */
    public function getDisplayTitle(): string
    {
        if ($this->isApproved()) {
            return $this->final_title ?? $this->optimized_title ?? $this->original_title ?? '';
        }
        return $this->optimized_title ?? $this->original_title ?? '';
    }

    /**
     * Get description for display
     */
    public function getDisplayDescription(): string
    {
        if ($this->isApproved()) {
            return $this->final_description ?? $this->optimized_description ?? $this->original_description ?? '';
        }
        return $this->optimized_description ?? $this->original_description ?? '';
    }
}
