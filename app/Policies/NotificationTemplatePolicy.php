<?php

namespace App\Policies;

use App\Models\User;
use App\Models\NotificationTemplate;

class NotificationTemplatePolicy
{
    /**
     * Determine if user can view any templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only admins and owners can manage templates
    }

    /**
     * Determine if user can view the template.
     */
    public function view(User $user, NotificationTemplate $template): bool
    {
        return $user->shop_id === $template->shop_id &&
               $user->isAdmin();
    }

    /**
     * Determine if user can create templates.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if user can update the template.
     */
    public function update(User $user, NotificationTemplate $template): bool
    {
        // Can't update system templates
        if ($template->is_system && !$user->isOwner()) {
            return false;
        }

        return $user->shop_id === $template->shop_id &&
               $user->isAdmin();
    }

    /**
     * Determine if user can delete the template.
     */
    public function delete(User $user, NotificationTemplate $template): bool
    {
        // Can't delete system templates
        if ($template->is_system) {
            return false;
        }

        return $user->shop_id === $template->shop_id &&
               $user->isAdmin();
    }
}
