<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class ReservationAllocator
{
    /**
     * Build a plan like [[location_id, qty], ...].
     * $opts: strategy: priority|most_stock|proximity, priority: int[], dest: [lat,lng]
     */
    public function plan(int $variantId, int $qty, array $opts = []): array
    {
        $strategy = $opts['strategy'] ?? 'priority';

        $rows = DB::table('stock_items')
            ->join('locations','locations.id','=','stock_items.location_id')
            ->where('product_variant_id', $variantId)
            ->where('locations.is_active', true)
            ->select('stock_items.location_id','stock_items.on_hand','stock_items.reserved','locations.lat','locations.lng')
            ->get();

        $candidates = [];
        foreach ($rows as $r) {
            $avail = (int)$r->on_hand - (int)$r->reserved;
            if ($avail > 0) $candidates[] = ['location_id'=>(int)$r->location_id,'available'=>$avail,'lat'=>$r->lat,'lng'=>$r->lng];
        }

        if ($strategy === 'most_stock') {
            usort($candidates, fn($a,$b)=> $b['available'] <=> $a['available']);
        } elseif ($strategy === 'proximity' && isset($opts['dest'])) {
            [$dlat,$dlng] = $opts['dest'];
            $dist = fn($lat,$lng)=> ($lat===null||$lng===null) ? PHP_INT_MAX : (($lat-$dlat)**2 + ($lng-$dlng)**2);
            usort($candidates, fn($a,$b)=> $dist($a['lat'],$a['lng']) <=> $dist($b['lat'],$b['lng']));
        } else { // priority list
            $priority = array_values(array_map('intval', $opts['priority'] ?? []));
            $rank = array_flip($priority);
            usort($candidates, fn($a,$b)=> ($rank[$a['location_id']] ?? 9999) <=> ($rank[$b['location_id']] ?? 9999));
        }

        $need = $qty; $plan = [];
        foreach ($candidates as $c) {
            if ($need <= 0) break;
            $take = min($c['available'], $need);
            if ($take > 0) { $plan[] = [$c['location_id'], $take]; $need -= $take; }
        }
        return $plan;
    }

    public function allocateByPriority(int $shopId, int $variantId, int $qty): array
    {
        $map = [];
        $remaining = $qty;
        $items = StockItem::query()
            ->where('shop_id', $shopId)
            ->where('variant_id', $variantId)
            ->join('locations','locations.id','=','stock_items.location_id')
            ->where('locations.is_active', true)
            ->orderBy('locations.priority')
            ->select('stock_items.*','locations.priority')
            ->get();


        foreach ($items as $item) {
            $avail = max($item->on_hand - $item->reserved, 0);
            if ($avail <= 0) continue;
            $take = min($avail, $remaining);
            if ($take > 0) {
                $map[$item->location_id] = ($map[$item->location_id] ?? 0) + $take;
                $remaining -= $take;
            }
            if ($remaining <= 0) break;
        }
        return $map; // caller will perform InventoryService::reserve per location
    }
}

