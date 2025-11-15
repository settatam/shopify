<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AICategoryMapping extends Model
{
    protected $fillable = [
        'product_id',
        'channel_id',
        'channel_type',
        'suggested_category_id',
        'suggested_category_name',
        'suggested_category_path',
        'confidence_score',
        'alternative_suggestions',
        'ai_reasoning',
        'matched_keywords',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'final_category_id',
        'final_category_name',
        'final_category_path',
        'product_data_used',
        'channel_categories',
        'rejection_reason',
    ];

    protected $casts = [
        'confidence_score' => 'decimal:2',
        'alternative_suggestions' => 'array',
        'matched_keywords' => 'array',
        'product_data_used' => 'array',
        'channel_categories' => 'array',
        'reviewed_at' => 'datetime',
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
     * Approve the suggestion
     */
    public function approve(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'final_category_id' => $this->suggested_category_id,
            'final_category_name' => $this->suggested_category_name,
            'final_category_path' => $this->suggested_category_path,
        ]);
    }

    /**
     * Reject the suggestion
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
     * Modify and approve with different category
     */
    public function modify(User $user, string $categoryId, string $categoryName, ?string $categoryPath = null): void
    {
        $this->update([
            'status' => 'modified',
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'final_category_id' => $categoryId,
            'final_category_name' => $categoryName,
            'final_category_path' => $categoryPath,
        ]);
    }

    /**
     * Check if mapping is approved
     */
    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'modified']);
    }

    /**
     * Check if mapping is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Scope to pending mappings
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to approved mappings
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
}
