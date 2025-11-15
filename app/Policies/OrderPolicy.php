<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{
    /**
     * Determine if user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['manage_orders', 'view_orders']);
    }

    /**
     * Determine if user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->shop_id === $order->shop_id &&
               $user->hasAnyPermission(['manage_orders', 'view_orders']);
    }

    /**
     * Determine if user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_orders');
    }

    /**
     * Determine if user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->shop_id === $order->shop_id &&
               $user->hasPermission('manage_orders');
    }

    /**
     * Determine if user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only owners can delete orders
        return $user->isOwner() &&
               $user->shop_id === $order->shop_id;
    }

    /**
     * Determine if user can fulfill the order.
     */
    public function fulfill(User $user, Order $order): bool
    {
        return $user->shop_id === $order->shop_id &&
               $user->hasPermission('manage_orders');
    }

    /**
     * Determine if user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->shop_id === $order->shop_id &&
               $user->hasPermission('manage_orders');
    }

    /**
     * Determine if user can refund the order.
     */
    public function refund(User $user, Order $order): bool
    {
        return $user->shop_id === $order->shop_id &&
               $user->hasPermission('manage_orders');
    }
}
