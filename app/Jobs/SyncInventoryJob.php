<?php

namespace App\Jobs;

use App\Integrations\ChannelRegistry;
use App\Models\{Channel, ProductVariant};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class SyncInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public int $variantId, public int $channelId, public int $quantity) {}


    public function handle(ChannelLocationPolicy $policy): void
    {
        $channel = Channel::findOrFail($this->channelId);
        [$locationIds, $safety] = (function($p){ return [$p['location_ids'],$p['safety_stock']]; })($policy->forChannel($channel));
        if (empty($locationIds)) return; // nothing to sync


        $variants = ProductVariant::query()
            ->when($this->variantIds, fn($q) => $q->whereIn('id', $this->variantIds))
            ->where('shop_id', $channel->shop_id)
            ->get();


        foreach ($variants as $variant) {
            $row = DB::table('stock_items')
                ->selectRaw('SUM(GREATEST(on_hand - reserved, 0)) as available')
                ->where('variant_id', $variant->id)
                ->whereIn('location_id', $locationIds)
                ->first();


            $available = max((int)($row->available ?? 0) - $safety, 0);


// ChannelRegistry->adapter($channel)->publishInventory($variant, $available)
            app('channels')->adapter($channel)->publishInventory($variant, $available);
        }
    }
}
