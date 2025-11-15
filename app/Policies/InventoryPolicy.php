<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Inventory;

class InventoryPolicy
{
    /**
     * Determine if user can view any inventory.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage_inventory', 'view_inventory']);
    }

    /**
     * Determine if user can view the inventory.
     */
    public function view(User $user, Inventory $inventory): bool
    {
        return $user->shop_id === $inventory->shop_id &&
               $user->hasAnyPermission(['manage_inventory', 'view_inventory']);
    }

    /**
     * Determine if user can adjust inventory.
     */
    public function adjust(User $user): bool
    {
        return $user->hasPermission('manage_inventory');
    }

    /**
     * Determine if user can transfer inventory.
     */
    public function transfer(User $user): bool
    {
        return $user->hasPermission('manage_inventory');
    }

    /**
     * Determine if user can reserve inventory.
     */
    public function reserve(User $user): bool
    {
        return $user->hasPermission('manage_inventory');
    }

    /**
     * Determine if user can release inventory.
     */
    public function release(User $user): bool
    {
        return $user->hasPermission('manage_inventory');
    }
}
