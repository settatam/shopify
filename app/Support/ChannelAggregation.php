<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
class ChannelAggregation
{
    /** Sum available across location set with optional safety stock */
    public static function availableForVariant(int $variantId, array $locationIds, int $safetyStock = 0): int
    {
        $row = DB::table('stock_items')
            ->selectRaw('SUM(GREATEST(on_hand - reserved,0)) as qty')
            ->where('variant_id', $variantId)
            ->whereIn('location_id', $locationIds)
            ->first();
        return max((int)($row->qty ?? 0) - $safetyStock, 0);
    }
}
