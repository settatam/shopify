<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TeamInvitation;

class TeamInvitationPolicy
{
    /**
     * Determine if user can view any invitations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can view the invitation.
     */
    public function view(User $user, TeamInvitation $invitation): bool
    {
        return $user->shop_id === $invitation->shop_id &&
               $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can create invitations.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can update the invitation.
     */
    public function update(User $user, TeamInvitation $invitation): bool
    {
        return $user->shop_id === $invitation->shop_id &&
               $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can delete/revoke the invitation.
     */
    public function delete(User $user, TeamInvitation $invitation): bool
    {
        return $user->shop_id === $invitation->shop_id &&
               $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can resend the invitation.
     */
    public function resend(User $user, TeamInvitation $invitation): bool
    {
        return $user->shop_id === $invitation->shop_id &&
               $user->hasPermission('manage_team');
    }
}
