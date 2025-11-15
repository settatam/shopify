<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;

class ProductPolicy
{
    /**
     * Determine if user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage_products', 'view_products']);
    }

    /**
     * Determine if user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        // Must be in same shop and have view permission
        return $user->shop_id === $product->shop_id &&
               $user->hasAnyPermission(['manage_products', 'view_products']);
    }

    /**
     * Determine if user can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_products');
    }

    /**
     * Determine if user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->shop_id === $product->shop_id &&
               $user->hasPermission('manage_products');
    }

    /**
     * Determine if user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->shop_id === $product->shop_id &&
               $user->hasPermission('manage_products');
    }

    /**
     * Determine if user can restore the product.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->shop_id === $product->shop_id &&
               $user->hasPermission('manage_products');
    }

    /**
     * Determine if user can permanently delete the product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isOwner() &&
               $user->shop_id === $product->shop_id;
    }

    /**
     * Determine if user can publish product to channels.
     */
    public function publish(User $user, Product $product): bool
    {
        return $user->shop_id === $product->shop_id &&
               $user->hasAllPermissions(['manage_products', 'manage_channels']);
    }
}
