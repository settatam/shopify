<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class EmailProviderSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'provider',
        'is_active',
        'is_primary',
        'api_key',
        'api_secret',
        'server_prefix',
        'from_email',
        'from_name',
        'reply_to',
        'default_audience_id',
        'audience_ids',
        'double_optin',
        'settings',
        'webhooks',
        'emails_sent_today',
        'emails_sent_month',
        'total_emails_sent',
        'last_sync_at',
        'last_email_sent_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'double_optin' => 'boolean',
        'audience_ids' => 'array',
        'settings' => 'array',
        'webhooks' => 'array',
        'emails_sent_today' => 'integer',
        'emails_sent_month' => 'integer',
        'total_emails_sent' => 'integer',
        'last_sync_at' => 'datetime',
        'last_email_sent_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    /**
     * Get the shop that owns the email provider setting.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get decrypted API key.
     */
    public function getDecryptedApiKey(): ?string
    {
        if (!$this->api_key) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt API key', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get decrypted API secret.
     */
    public function getDecryptedApiSecret(): ?string
    {
        if (!$this->api_secret) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_secret);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt API secret', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Set encrypted API key.
     */
    public function setApiKey(string $apiKey): void
    {
        $this->api_key = Crypt::encryptString($apiKey);
    }

    /**
     * Set encrypted API secret.
     */
    public function setApiSecret(string $apiSecret): void
    {
        $this->api_secret = Crypt::encryptString($apiSecret);
    }

    /**
     * Increment email sent counters.
     */
    public function incrementEmailsSent(int $count = 1): void
    {
        $this->increment('emails_sent_today', $count);
        $this->increment('emails_sent_month', $count);
        $this->increment('total_emails_sent', $count);
        $this->update(['last_email_sent_at' => now()]);
    }

    /**
     * Reset daily email counter.
     */
    public function resetDailyCounter(): void
    {
        $this->update(['emails_sent_today' => 0]);
    }

    /**
     * Reset monthly email counter.
     */
    public function resetMonthlyCounter(): void
    {
        $this->update(['emails_sent_month' => 0]);
    }

    /**
     * Check if provider is configured.
     */
    public function isConfigured(): bool
    {
        if (!$this->api_key) {
            return false;
        }

        if ($this->provider === 'mailchimp' && !$this->server_prefix) {
            return false;
        }

        return true;
    }

    /**
     * Check if provider is ready to send emails.
     */
    public function isReadyToSend(): bool
    {
        return $this->is_active && $this->isConfigured();
    }

    /**
     * Get Mailchimp base URL.
     */
    public function getMailchimpBaseUrl(): ?string
    {
        if ($this->provider !== 'mailchimp' || !$this->server_prefix) {
            return null;
        }

        return "https://{$this->server_prefix}.api.mailchimp.com/3.0";
    }

    /**
     * Scope: Get primary provider for shop.
     */
    public function scopePrimary($query, int $shopId)
    {
        return $query->where('shop_id', $shopId)
                    ->where('is_primary', true)
                    ->where('is_active', true)
                    ->first();
    }

    /**
     * Scope: Get active providers for shop.
     */
    public function scopeActive($query, int $shopId)
    {
        return $query->where('shop_id', $shopId)
                    ->where('is_active', true);
    }

    /**
     * Scope: Get provider by type.
     */
    public function scopeByProvider($query, int $shopId, string $provider)
    {
        return $query->where('shop_id', $shopId)
                    ->where('provider', $provider)
                    ->first();
    }
}
