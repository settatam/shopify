<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Shop;

class ShopPolicy
{
    /**
     * Determine if user can view the shop.
     */
    public function view(User $user, Shop $shop): bool
    {
        return $user->shop_id === $shop->id;
    }

    /**
     * Determine if user can update the shop.
     */
    public function update(User $user, Shop $shop): bool
    {
        return $user->shop_id === $shop->id &&
               $user->hasPermission('manage_shop');
    }

    /**
     * Determine if user can delete the shop.
     */
    public function delete(User $user, Shop $shop): bool
    {
        return $user->shop_id === $shop->id &&
               $user->isOwner();
    }

    /**
     * Determine if user can manage billing.
     */
    public function manageBilling(User $user, Shop $shop): bool
    {
        return $user->shop_id === $shop->id &&
               $user->hasPermission('manage_billing');
    }

    /**
     * Determine if user can view reports.
     */
    public function viewReports(User $user, Shop $shop): bool
    {
        return $user->shop_id === $shop->id &&
               $user->hasPermission('view_reports');
    }
}
