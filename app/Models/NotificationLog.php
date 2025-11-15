<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'shop_id',
        'channel',
        'to',
        'from',
        'message',
        'template_key',
        'template_variables',
        'message_sid',
        'status',
        'error_code',
        'error_message',
        'price',
        'price_unit',
        'related_type',
        'related_id',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'template_variables' => 'array',
        'price' => 'decimal:4',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Get the shop that owns the notification log
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the related model (order, product, etc.)
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(array $twilioResponse): void
    {
        $this->update([
            'status' => 'sent',
            'message_sid' => $twilioResponse['message_sid'] ?? null,
            'from' => $twilioResponse['from'] ?? $this->from,
            'price' => $twilioResponse['price'] ?? null,
            'price_unit' => $twilioResponse['price_unit'] ?? null,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $errorMessage, ?string $errorCode = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'failed_at' => now(),
        ]);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by channel
     */
    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to filter by shop
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        return $this->price_unit . ' ' . number_format($this->price, 4);
    }
}
