<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeamInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'invited_by',
        'email',
        'token',
        'role',
        'permissions',
        'status',
        'message',
        'expires_at',
        'accepted_at',
        'rejected_at',
        'revoked_at',
        'user_id',
        'accepted_ip',
        'accepted_user_agent',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Boot model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (!$invitation->token) {
                $invitation->token = static::generateToken();
            }

            if (!$invitation->expires_at) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    /**
     * Get the shop that owns the invitation.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get the user who accepted the invitation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate unique invitation token.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    /**
     * Get invitation URL.
     */
    public function getInvitationUrl(): string
    {
        return url('/team/accept-invitation/' . $this->token);
    }

    /**
     * Check if invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Check if invitation can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Accept invitation.
     */
    public function accept(User $user, ?string $ip = null, ?string $userAgent = null): void
    {
        if (!$this->canBeAccepted()) {
            throw new \Exception('Invitation cannot be accepted');
        }

        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'user_id' => $user->id,
            'accepted_ip' => $ip,
            'accepted_user_agent' => $userAgent,
        ]);

        // Update user role and permissions
        $user->update([
            'shop_id' => $this->shop_id,
            'role' => $this->role,
            'permissions' => $this->permissions,
            'is_team_member' => true,
            'invited_by' => $this->invited_by,
        ]);
    }

    /**
     * Reject invitation.
     */
    public function reject(): void
    {
        if ($this->status !== 'pending') {
            throw new \Exception('Only pending invitations can be rejected');
        }

        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);
    }

    /**
     * Revoke invitation.
     */
    public function revoke(): void
    {
        if (!in_array($this->status, ['pending'])) {
            throw new \Exception('Only pending invitations can be revoked');
        }

        $this->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }

    /**
     * Mark as expired.
     */
    public function markExpired(): void
    {
        if ($this->status !== 'pending') {
            return;
        }

        $this->update(['status' => 'expired']);
    }

    /**
     * Resend invitation with new token.
     */
    public function resend(): self
    {
        if ($this->status === 'accepted') {
            throw new \Exception('Cannot resend accepted invitation');
        }

        // Create new invitation with fresh token and expiry
        return static::create([
            'shop_id' => $this->shop_id,
            'invited_by' => $this->invited_by,
            'email' => $this->email,
            'role' => $this->role,
            'permissions' => $this->permissions,
            'message' => $this->message,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Get role display name.
     */
    public function getRoleDisplayName(): string
    {
        return match ($this->role) {
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'readonly' => 'Read Only',
            default => ucfirst($this->role),
        };
    }

    /**
     * Get role permissions.
     */
    public static function getRolePermissions(string $role): array
    {
        return match ($role) {
            'owner' => [
                'manage_team',
                'manage_billing',
                'manage_shop',
                'manage_products',
                'manage_orders',
                'manage_inventory',
                'manage_channels',
                'view_reports',
            ],
            'admin' => [
                'manage_team',
                'manage_products',
                'manage_orders',
                'manage_inventory',
                'manage_channels',
                'view_reports',
            ],
            'manager' => [
                'manage_products',
                'manage_orders',
                'manage_inventory',
                'view_reports',
            ],
            'staff' => [
                'manage_orders',
                'view_products',
                'view_inventory',
            ],
            'readonly' => [
                'view_products',
                'view_orders',
                'view_inventory',
                'view_reports',
            ],
            default => [],
        };
    }

    /**
     * Scope: Pending invitations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope: Filter by email.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope: Filter by shop.
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
