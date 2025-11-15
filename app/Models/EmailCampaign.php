<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'created_by',
        'provider',
        'provider_campaign_id',
        'name',
        'subject',
        'preview_text',
        'content_html',
        'content_text',
        'audience_id',
        'segment_criteria',
        'recipient_count',
        'from_email',
        'from_name',
        'reply_to',
        'status',
        'scheduled_at',
        'sent_at',
        'emails_sent',
        'opens',
        'unique_opens',
        'clicks',
        'unique_clicks',
        'bounces',
        'unsubscribes',
        'open_rate',
        'click_rate',
        'settings',
        'tracking_options',
        'last_synced_at',
    ];

    protected $casts = [
        'segment_criteria' => 'array',
        'recipient_count' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'emails_sent' => 'integer',
        'opens' => 'integer',
        'unique_opens' => 'integer',
        'clicks' => 'integer',
        'unique_clicks' => 'integer',
        'bounces' => 'integer',
        'unsubscribes' => 'integer',
        'open_rate' => 'decimal:2',
        'click_rate' => 'decimal:2',
        'settings' => 'array',
        'tracking_options' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the shop that owns the campaign.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who created the campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if campaign is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if campaign is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if campaign is sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if campaign is sending.
     */
    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    /**
     * Check if campaign can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    /**
     * Check if campaign can be sent.
     */
    public function canBeSent(): bool
    {
        return $this->isDraft() && !empty($this->content_html);
    }

    /**
     * Check if campaign can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['scheduled', 'sending']);
    }

    /**
     * Mark as scheduled.
     */
    public function markAsScheduled(?\DateTimeInterface $scheduledAt = null): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt ?? now(),
        ]);
    }

    /**
     * Mark as sending.
     */
    public function markAsSending(): void
    {
        $this->update([
            'status' => 'sending',
        ]);
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    /**
     * Update analytics data.
     */
    public function updateAnalytics(array $data): void
    {
        $updates = [];

        if (isset($data['emails_sent'])) {
            $updates['emails_sent'] = $data['emails_sent'];
        }

        if (isset($data['opens'])) {
            $updates['opens'] = $data['opens'];
        }

        if (isset($data['unique_opens'])) {
            $updates['unique_opens'] = $data['unique_opens'];
        }

        if (isset($data['clicks'])) {
            $updates['clicks'] = $data['clicks'];
        }

        if (isset($data['unique_clicks'])) {
            $updates['unique_clicks'] = $data['unique_clicks'];
        }

        if (isset($data['bounces'])) {
            $updates['bounces'] = $data['bounces'];
        }

        if (isset($data['unsubscribes'])) {
            $updates['unsubscribes'] = $data['unsubscribes'];
        }

        // Calculate rates
        if (isset($updates['unique_opens']) && $this->emails_sent > 0) {
            $updates['open_rate'] = ($updates['unique_opens'] / $this->emails_sent) * 100;
        }

        if (isset($updates['unique_clicks']) && $this->emails_sent > 0) {
            $updates['click_rate'] = ($updates['unique_clicks'] / $this->emails_sent) * 100;
        }

        $updates['last_synced_at'] = now();

        $this->update($updates);
    }

    /**
     * Get engagement score (0-100).
     */
    public function getEngagementScore(): float
    {
        if ($this->emails_sent === 0) {
            return 0;
        }

        // Weight: 60% opens, 40% clicks
        return ($this->open_rate * 0.6) + ($this->click_rate * 0.4);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Draft campaigns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: Scheduled campaigns.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope: Sent campaigns.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: Due to send (scheduled for now or earlier).
     */
    public function scopeDueToSend($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope: By provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: Recent campaigns (last 30 days).
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDays(30));
    }
}
