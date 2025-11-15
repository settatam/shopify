<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartPublishReport extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'selected_channels',
        'auto_optimize_title',
        'auto_optimize_description',
        'auto_map_category',
        'auto_suggest_attributes',
        'auto_optimize_images',
        'auto_set_price',
        'auto_check_compliance',
        'status',
        'title_optimization_status',
        'description_optimization_status',
        'category_mapping_status',
        'attribute_suggestion_status',
        'image_optimization_status',
        'pricing_status',
        'compliance_check_status',
        'publishing_status',
        'optimized_content',
        'mapped_categories',
        'suggested_attributes',
        'optimized_images',
        'pricing_data',
        'compliance_results',
        'publish_results',
        'errors',
        'warnings',
        'failure_reason',
        'channels_attempted',
        'channels_succeeded',
        'channels_failed',
        'total_steps',
        'completed_steps',
        'failed_steps',
        'skipped_steps',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected $casts = [
        'selected_channels' => 'array',
        'auto_optimize_title' => 'boolean',
        'auto_optimize_description' => 'boolean',
        'auto_map_category' => 'boolean',
        'auto_suggest_attributes' => 'boolean',
        'auto_optimize_images' => 'boolean',
        'auto_set_price' => 'boolean',
        'auto_check_compliance' => 'boolean',
        'optimized_content' => 'array',
        'mapped_categories' => 'array',
        'suggested_attributes' => 'array',
        'optimized_images' => 'array',
        'pricing_data' => 'array',
        'compliance_results' => 'array',
        'publish_results' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark report as started
     */
    public function markStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark report as completed
     */
    public function markCompleted(): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $status = 'completed';
        if ($this->failed_steps > 0) {
            $status = $this->completed_steps > 0 ? 'partial_success' : 'failed';
        }

        $this->update([
            'status' => $status,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark report as failed
     */
    public function markFailed(string $reason): void
    {
        $duration = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Update step status
     */
    public function updateStepStatus(string $step, string $status): void
    {
        $stepField = $step . '_status';

        $this->update([$stepField => $status]);

        // Update counters
        $counters = [];
        if ($status === 'completed') {
            $counters['completed_steps'] = $this->completed_steps + 1;
        } elseif ($status === 'failed') {
            $counters['failed_steps'] = $this->failed_steps + 1;
        } elseif ($status === 'skipped') {
            $counters['skipped_steps'] = $this->skipped_steps + 1;
        }

        if (!empty($counters)) {
            $this->update($counters);
        }
    }

    /**
     * Add error
     */
    public function addError(string $step, string $message): void
    {
        $errors = $this->errors ?? [];
        $errors[] = [
            'step' => $step,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update(['errors' => $errors]);
    }

    /**
     * Add warning
     */
    public function addWarning(string $step, string $message): void
    {
        $warnings = $this->warnings ?? [];
        $warnings[] = [
            'step' => $step,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update(['warnings' => $warnings]);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_steps === 0) {
            return 0;
        }

        return round(($this->completed_steps / $this->total_steps) * 100, 1);
    }

    /**
     * Get success rate
     */
    public function getSuccessRate(): ?float
    {
        if ($this->channels_attempted === 0) {
            return null;
        }

        return round(($this->channels_succeeded / $this->channels_attempted) * 100, 1);
    }

    /**
     * Check if processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'partial_success']);
    }

    /**
     * Check if failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Scope to recent reports
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to user's reports
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to product's reports
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to processing reports
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope to completed reports
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'partial_success']);
    }
}
