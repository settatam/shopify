<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'user_id',
        'notification_template_id',
        'event_type',
        'channel',
        'recipient_email',
        'recipient_phone',
        'subject',
        'body',
        'variables',
        'metadata',
        'status',
        'error_message',
        'provider',
        'provider_message_id',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'failed_at',
        'retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'bounced_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    /**
     * Get the shop that owns the log.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user that received the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template used for this notification.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /**
     * Mark notification as queued.
     */
    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
            'queued_at' => now(),
        ]);
    }

    /**
     * Mark notification as sending.
     */
    public function markAsSending(): void
    {
        $this->update(['status' => 'sending']);
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(?string $providerMessageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
        ]);
    }

    /**
     * Mark notification as delivered (webhook confirmation).
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as opened (tracking pixel).
     */
    public function markAsOpened(): void
    {
        // Don't overwrite status if it's already delivered
        if ($this->status === 'sent') {
            $this->update(['status' => 'opened']);
        }

        $this->update(['opened_at' => now()]);
    }

    /**
     * Mark notification as clicked (link tracking).
     */
    public function markAsClicked(): void
    {
        $this->update(['clicked_at' => now()]);
    }

    /**
     * Mark notification as bounced.
     */
    public function markAsBounced(?string $errorMessage = null): void
    {
        $this->update([
            'status' => 'bounced',
            'bounced_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark notification as rejected.
     */
    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'error_message' => $reason,
        ]);
    }

    /**
     * Schedule retry for failed notification.
     */
    public function scheduleRetry(int $delayMinutes = 5): void
    {
        $this->update([
            'status' => 'queued',
            'next_retry_at' => now()->addMinutes($delayMinutes),
        ]);
    }

    /**
     * Check if notification can be retried.
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->retry_count < $maxRetries &&
               in_array($this->status, ['failed', 'bounced']);
    }

    /**
     * Get delivery rate (percentage delivered).
     */
    public static function getDeliveryRate(int $shopId, ?string $eventType = null): float
    {
        $query = static::where('shop_id', $shopId);

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $delivered = $query->whereIn('status', ['delivered', 'opened', 'clicked'])->count();

        return round(($delivered / $total) * 100, 2);
    }

    /**
     * Get open rate (percentage opened).
     */
    public static function getOpenRate(int $shopId, ?string $eventType = null): float
    {
        $query = static::where('shop_id', $shopId)->where('channel', 'email');

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $sent = $query->whereIn('status', ['sent', 'delivered', 'opened', 'clicked'])->count();

        if ($sent === 0) {
            return 0;
        }

        $opened = $query->whereNotNull('opened_at')->count();

        return round(($opened / $sent) * 100, 2);
    }

    /**
     * Get click rate (percentage clicked).
     */
    public static function getClickRate(int $shopId, ?string $eventType = null): float
    {
        $query = static::where('shop_id', $shopId)->where('channel', 'email');

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        $sent = $query->whereIn('status', ['sent', 'delivered', 'opened', 'clicked'])->count();

        if ($sent === 0) {
            return 0;
        }

        $clicked = $query->whereNotNull('clicked_at')->count();

        return round(($clicked / $sent) * 100, 2);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by event type.
     */
    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Filter by channel.
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope: Pending retry.
     */
    public function scopePendingRetry($query)
    {
        return $query->where('status', 'queued')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now());
    }

    /**
     * Scope: Failed notifications that can be retried.
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->whereIn('status', ['failed', 'bounced'])
            ->where('retry_count', '<', $maxRetries);
    }
}
