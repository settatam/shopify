<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'event_type',
        'email_enabled',
        'sms_enabled',
        'push_enabled',
        'in_app_enabled',
        'settings',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shop that owns the preference.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Check if notifications are enabled for a specific channel.
     */
    public function isEnabledFor(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->email_enabled,
            'sms' => $this->sms_enabled,
            'push' => $this->push_enabled,
            'in_app' => $this->in_app_enabled,
            default => false,
        };
    }

    /**
     * Enable notifications for a channel.
     */
    public function enable(string $channel): void
    {
        match ($channel) {
            'email' => $this->update(['email_enabled' => true]),
            'sms' => $this->update(['sms_enabled' => true]),
            'push' => $this->update(['push_enabled' => true]),
            'in_app' => $this->update(['in_app_enabled' => true]),
            default => null,
        };
    }

    /**
     * Disable notifications for a channel.
     */
    public function disable(string $channel): void
    {
        match ($channel) {
            'email' => $this->update(['email_enabled' => false]),
            'sms' => $this->update(['sms_enabled' => false]),
            'push' => $this->update(['push_enabled' => false]),
            'in_app' => $this->update(['in_app_enabled' => false]),
            default => null,
        };
    }

    /**
     * Get or create preference for user and event type.
     */
    public static function getOrCreateFor(User $user, string $eventType, bool $defaultEnabled = true): self
    {
        return static::firstOrCreate(
            [
                'user_id' => $user->id,
                'event_type' => $eventType,
            ],
            [
                'shop_id' => $user->shop_id,
                'email_enabled' => $defaultEnabled,
                'sms_enabled' => false,
                'push_enabled' => false,
                'in_app_enabled' => $defaultEnabled,
            ]
        );
    }

    /**
     * Scope: Filter by user.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope: Filter by event type.
     */
    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: Enabled for specific channel.
     */
    public function scopeEnabledFor($query, string $channel)
    {
        $column = $channel . '_enabled';
        return $query->where($column, true);
    }
}
