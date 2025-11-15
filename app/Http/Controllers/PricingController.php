<?php

namespace App\Http\Controllers;

use App\Jobs\SyncPriceJob;
use App\Models\Channel;
use App\Models\ProductVariant;
use App\Models\VariantChannelPricing;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    //
    public function updateBase(Request $request, ProductVariant $variant, PricingService $svc)
    {
        $this->authorize('update', $variant);
        $data = $request->validate([
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'msrp'      => 'nullable|numeric|min:0',
        ]);
        $updated = $svc->setBasePricing(
            $variant,
            $data['price_min'] ?? null,
            $data['price_max'] ?? null,
            $data['msrp'] ?? null
        );

        // Sync all channels for this variant's shop
        dispatch(new SyncPriceJob(($variant->id, channelId: null));

        return ['variant' => $updated->only('id','price_min','price_max','msrp')];
    }

    public function updateOverride(Request $request, Channel $channel, ProductVariant $variant, PricingService $svc)
    {
        $this->authorize('update', $variant);
        $data = $request->validate([
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'msrp'      => 'nullable|numeric|min:0',
        ]);
        $row = $svc->setChannelOverride($channel->id, $variant, $data);

        // Sync only this channel
        dispatch(new SyncPriceJob($variant->id, channelId: $channel->id));

        return ['override' => $row->only('variant_id','channel_id','price_min','price_max','msrp')];
    }

    public function showEffective(Request $request, Channel $channel, ProductVariant $variant)
    {
        $override = VariantChannelPricing::where('variant_id',$variant->id)
            ->where('channel_id',$channel->id)->first();
        return [
            'effective' => [
                'price_min' => $override->price_min ?? $variant->price_min,
                'price_max' => $override->price_max ?? $variant->price_max,
                'msrp' => $override->msrp ?? $variant->msrp,
            ],
            'override' => $override,
        ];
    }
}
