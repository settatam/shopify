<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailchimpSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'user_id',
        'mailchimp_id',
        'email',
        'audience_id',
        'first_name',
        'last_name',
        'phone',
        'status',
        'tags',
        'merge_fields',
        'email_marketing',
        'sms_marketing',
        'subscribed_at',
        'unsubscribed_at',
        'last_synced_at',
        'sync_status',
        'sync_error',
    ];

    protected $casts = [
        'tags' => 'array',
        'merge_fields' => 'array',
        'email_marketing' => 'boolean',
        'sms_marketing' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the shop that owns the subscriber.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user associated with the subscriber.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get subscriber's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Check if subscriber is subscribed.
     */
    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    /**
     * Check if subscriber is pending confirmation.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if subscriber is unsubscribed.
     */
    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }

    /**
     * Mark as subscribed.
     */
    public function markAsSubscribed(): void
    {
        $this->update([
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    /**
     * Mark as unsubscribed.
     */
    public function markAsUnsubscribed(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Mark as pending.
     */
    public function markAsPending(): void
    {
        $this->update([
            'status' => 'pending',
        ]);
    }

    /**
     * Mark as synced.
     */
    public function markAsSynced(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'sync_status' => 'synced',
            'sync_error' => null,
        ]);
    }

    /**
     * Mark sync as failed.
     */
    public function markSyncFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'error',
            'sync_error' => $error,
        ]);
    }

    /**
     * Add tag.
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    /**
     * Remove tag.
     */
    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        $tags = array_filter($tags, fn($t) => $t !== $tag);

        $this->update(['tags' => array_values($tags)]);
    }

    /**
     * Set merge field.
     */
    public function setMergeField(string $key, $value): void
    {
        $fields = $this->merge_fields ?? [];
        $fields[$key] = $value;
        $this->update(['merge_fields' => $fields]);
    }

    /**
     * Get merge field.
     */
    public function getMergeField(string $key, $default = null)
    {
        return $this->merge_fields[$key] ?? $default;
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Subscribed only.
     */
    public function scopeSubscribed($query)
    {
        return $query->where('status', 'subscribed');
    }

    /**
     * Scope: Unsubscribed only.
     */
    public function scopeUnsubscribed($query)
    {
        return $query->where('status', 'unsubscribed');
    }

    /**
     * Scope: Pending confirmation.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: By audience.
     */
    public function scopeByAudience($query, string $audienceId)
    {
        return $query->where('audience_id', $audienceId);
    }

    /**
     * Scope: Needs sync (never synced or old sync).
     */
    public function scopeNeedsSync($query, int $hoursThreshold = 24)
    {
        return $query->where(function($q) use ($hoursThreshold) {
            $q->whereNull('last_synced_at')
              ->orWhere('last_synced_at', '<', now()->subHours($hoursThreshold));
        });
    }

    /**
     * Scope: Sync failed.
     */
    public function scopeSyncFailed($query)
    {
        return $query->where('sync_status', 'error');
    }
}
