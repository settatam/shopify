<?php

namespace App\Actions;

use App\Services\{ReservationAllocator, InventoryService};


class AllocateForCheckout
{
    public function __invoke(int $shopId, int $variantId, int $qty): array
    {
        $alloc = app(ReservationAllocator::class)->allocateByPriority($shopId, $variantId, $qty);
        $svc = app(InventoryService::class);
        foreach ($alloc as $locationId => $take) {
            $svc->reserve($shopId, $variantId, $locationId, $take, 'checkout', 'cart');
        }
        return $alloc; // [location_id => qty]
    }
}
