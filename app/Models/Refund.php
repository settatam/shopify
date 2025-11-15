<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'return_request_id',
        'channel_order_id',
        'refund_number',
        'order_number',
        'refund_type',
        'items_refund',
        'shipping_refund',
        'tax_refund',
        'restocking_fee',
        'total_refund',
        'refund_method',
        'original_payment_method',
        'original_transaction_id',
        'payment_gateway',
        'status',
        'gateway_refund_id',
        'gateway_response',
        'processed_at',
        'processed_by_id',
        'failure_reason',
        'retry_count',
        'last_retry_at',
        'store_credit_code',
        'store_credit_expires_at',
        'customer_email',
        'customer_name',
        'channel_id',
        'channel_refund_id',
        'synced_to_channel',
        'synced_to_channel_at',
        'customer_notified',
        'customer_notified_at',
        'refund_reason',
        'internal_notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'synced_to_channel' => 'boolean',
        'customer_notified' => 'boolean',
        'processed_at' => 'datetime',
        'last_retry_at' => 'datetime',
        'store_credit_expires_at' => 'datetime',
        'synced_to_channel_at' => 'datetime',
        'customer_notified_at' => 'datetime',
        'items_refund' => 'decimal:2',
        'shipping_refund' => 'decimal:2',
        'tax_refund' => 'decimal:2',
        'restocking_fee' => 'decimal:2',
        'total_refund' => 'decimal:2',
    ];

    /**
     * Get the shop.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the return request.
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    /**
     * Get the channel order.
     */
    public function channelOrder(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class);
    }

    /**
     * Get the channel.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the user who processed the refund.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    /**
     * Generate a unique refund number.
     */
    public static function generateRefundNumber(): string
    {
        do {
            $refundNumber = 'REF-' . strtoupper(substr(uniqid(), -8));
        } while (static::where('refund_number', $refundNumber)->exists());

        return $refundNumber;
    }

    /**
     * Mark refund as processing.
     */
    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    /**
     * Mark refund as completed.
     */
    public function markCompleted(User $user, ?string $gatewayRefundId = null): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'processed_by_id' => $user->id,
            'gateway_refund_id' => $gatewayRefundId,
        ]);
    }

    /**
     * Mark refund as failed.
     */
    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update(['last_retry_at' => now()]);
    }

    /**
     * Check if refund can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }

    /**
     * Mark as synced to channel.
     */
    public function markSyncedToChannel(string $channelRefundId): void
    {
        $this->update([
            'synced_to_channel' => true,
            'synced_to_channel_at' => now(),
            'channel_refund_id' => $channelRefundId,
        ]);
    }

    /**
     * Mark customer as notified.
     */
    public function markCustomerNotified(): void
    {
        $this->update([
            'customer_notified' => true,
            'customer_notified_at' => now(),
        ]);
    }

    /**
     * Get refund type label.
     */
    public function getRefundTypeLabel(): string
    {
        return match($this->refund_type) {
            'full' => 'Full Refund',
            'partial' => 'Partial Refund',
            'shipping_only' => 'Shipping Only',
            'tax_only' => 'Tax Only',
            'custom' => 'Custom Amount',
            default => ucfirst($this->refund_type),
        };
    }

    /**
     * Get refund method label.
     */
    public function getRefundMethodLabel(): string
    {
        return match($this->refund_method) {
            'original_payment' => 'Original Payment Method',
            'store_credit' => 'Store Credit',
            'cash' => 'Cash',
            'check' => 'Check',
            'bank_transfer' => 'Bank Transfer',
            'paypal' => 'PayPal',
            'manual' => 'Manual',
            default => ucfirst($this->refund_method),
        };
    }

    /**
     * Get status label with color.
     */
    public function getStatusInfo(): array
    {
        return match($this->status) {
            'pending' => ['label' => 'Pending', 'color' => 'yellow'],
            'processing' => ['label' => 'Processing', 'color' => 'blue'],
            'completed' => ['label' => 'Completed', 'color' => 'green'],
            'failed' => ['label' => 'Failed', 'color' => 'red'],
            'cancelled' => ['label' => 'Cancelled', 'color' => 'gray'],
            'on_hold' => ['label' => 'On Hold', 'color' => 'orange'],
            default => ['label' => ucfirst($this->status), 'color' => 'gray'],
        };
    }

    /**
     * Check if requires channel sync.
     */
    public function requiresChannelSync(): bool
    {
        return $this->channel_id !== null &&
               !$this->synced_to_channel &&
               $this->status === 'completed';
    }
}
