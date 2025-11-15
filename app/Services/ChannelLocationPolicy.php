<?php

namespace App\Services;

use App\Models\Channel;
use Illuminate\Support\Facades\DB;

class ChannelLocationPolicy
{
    // Sum available respecting included locations + safety stock, ignoring inactive locations
    /** Returns [location_ids: int[], safety_stock: int] */
    public function forChannel(Channel $channel): array
    {
        $policy = $channel->inventory_policy ?? [];
        return [
            'location_ids' => array_values(array_filter((array)($policy['location_ids'] ?? []), 'is_numeric')),
            'safety_stock' => (int)($policy['safety_stock'] ?? 0),
        ];
    }
}

