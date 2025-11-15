<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if user can view any team members.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can view another user.
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->shop_id === $targetUser->shop_id &&
               $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can invite team members.
     */
    public function invite(User $user): bool
    {
        return $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can update another user.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Can't update yourself through this policy
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Must be in same shop and have manage_team permission
        if ($user->shop_id !== $targetUser->shop_id) {
            return false;
        }

        if (!$user->hasPermission('manage_team')) {
            return false;
        }

        // Can't update an owner unless you're an owner
        if ($targetUser->isOwner() && !$user->isOwner()) {
            return false;
        }

        return true;
    }

    /**
     * Determine if user can delete another user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Can't delete yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Can't delete owner
        if ($targetUser->isOwner()) {
            return false;
        }

        return $user->shop_id === $targetUser->shop_id &&
               $user->hasPermission('manage_team');
    }

    /**
     * Determine if user can update their own profile.
     */
    public function updateOwn(User $user): bool
    {
        return true; // Everyone can update their own profile
    }
}
