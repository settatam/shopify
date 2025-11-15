<?php

namespace App\Jobs;

use App\Integrations\ChannelRegistry;
use App\Models\{Channel, Product};
use App\Services\MappingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishListingJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $productId, public int $channelId, public ?int $channelCategoryId = null) {}


    public function handle(ChannelRegistry $registry)
    {
        $product = Product::with('variants')->findOrFail($this->productId);
        $channel = Channel::findOrFail($this->channelId);
        $adapter = $registry->for($channel);


        $mapping = MappingService::for($channel, $product)->compose($this->channelCategoryId);
        $adapter->upsertProduct($product, $mapping);
        foreach ($product->variants as $v) {
            $adapter->upsertVariant($v, $mapping);
        }
    }
}
