<?php

namespace App\Jobs;

use App\Models\{Channel, ProductVariant, Variant, VariantChannelPricing};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $variantId,
        public ?int $channelId = null // null = all channels for the variant's shop
    ) {}

    public function handle(): void
    {
        $variant = ProductVariant::query()->findOrFail($this->variantId);

        $channels = Channel::query()
            ->where('shop_id', $variant->shop_id)
            ->when($this->channelId, fn($q) => $q->where('id', $this->channelId))
            ->get(['id','name','shop_id']);

        foreach ($channels as $channel) {
            $override = VariantChannelPricing::query()
                ->where('variant_id', $variant->id)
                ->where('channel_id', $channel->id)
                ->first();

            $effective = [
                'price_min' => $override->price_min ?? $variant->price_min,
                'price_max' => $override->price_max ?? $variant->price_max,
                'msrp'      => $override->msrp      ?? $variant->msrp,
            ];

            // Push to the channel adapter (assumes your ChannelRegistry is bound as 'channels')
            app('channels')->adapter($channel)->publishPrice($variant, $effective);
        }
    }
}
