<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'shop_id',
        'role',
        'permissions',
        'is_team_member',
        'invited_by',
        'phone',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'permissions' => 'array',
            'settings' => 'array',
            'is_team_member' => 'boolean',
        ];
    }

    /**
     * Get the shop that owns the user.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the user who invited this team member.
     */
    public function invitedByUser()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get all permissions for this user.
     */
    public function getAllPermissions(): array
    {
        // If custom permissions are set, use those
        if ($this->permissions && is_array($this->permissions)) {
            return $this->permissions;
        }

        // Otherwise, get permissions from role
        return TeamInvitation::getRolePermissions($this->role ?? 'staff');
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getAllPermissions();
        return in_array($permission, $permissions);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if user is owner.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if user is admin or owner.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin']);
    }

    /**
     * Check if user can receive notifications for an event.
     */
    public function canReceiveNotification(string $eventType): bool
    {
        // Get the event
        $event = \App\Models\NotificationEvent::where('event_type', $eventType)->first();

        if (!$event) {
            return false;
        }

        // If event doesn't require permission, everyone can receive it
        if (!$event->required_permission) {
            return true;
        }

        // Check if user has the required permission
        return $this->hasPermission($event->required_permission);
    }
}
