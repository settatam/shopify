<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Channel;

class ChannelPolicy
{
    /**
     * Determine if user can view any channels.
     */
    public function viewAny(User $user): bool
    {
        return true; // All users can see their shop's channels
    }

    /**
     * Determine if user can view the channel.
     */
    public function view(User $user, Channel $channel): bool
    {
        return $user->shop_id === $channel->shop_id;
    }

    /**
     * Determine if user can create channels.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_channels');
    }

    /**
     * Determine if user can update the channel.
     */
    public function update(User $user, Channel $channel): bool
    {
        return $user->shop_id === $channel->shop_id &&
               $user->hasPermission('manage_channels');
    }

    /**
     * Determine if user can delete the channel.
     */
    public function delete(User $user, Channel $channel): bool
    {
        return $user->shop_id === $channel->shop_id &&
               $user->hasPermission('manage_channels');
    }

    /**
     * Determine if user can connect a channel.
     */
    public function connect(User $user): bool
    {
        return $user->hasPermission('manage_channels');
    }

    /**
     * Determine if user can disconnect the channel.
     */
    public function disconnect(User $user, Channel $channel): bool
    {
        return $user->shop_id === $channel->shop_id &&
               $user->hasPermission('manage_channels');
    }

    /**
     * Determine if user can sync the channel.
     */
    public function sync(User $user, Channel $channel): bool
    {
        return $user->shop_id === $channel->shop_id &&
               $user->hasPermission('manage_channels');
    }
}
