<?php

namespace App\Policies;

use App\Models\User;
use App\Models\NotificationLog;

class NotificationLogPolicy
{
    /**
     * Determine if user can view any notification logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin(); // Only admins can view logs
    }

    /**
     * Determine if user can view the log.
     */
    public function view(User $user, NotificationLog $log): bool
    {
        return $user->shop_id === $log->shop_id &&
               $user->isAdmin();
    }

    /**
     * Determine if user can retry the notification.
     */
    public function update(User $user, NotificationLog $log): bool
    {
        return $user->shop_id === $log->shop_id &&
               $user->isAdmin();
    }
}
