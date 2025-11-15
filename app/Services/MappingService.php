<?php

namespace App\Services;

use App\Models\{Channel, Product, AttributeMapping, ChannelCategory};
use Illuminate\Support\Arr;

class MappingService
{
    public static function for(Channel $channel, Product $product): self
    { return new self($channel, $product); }


    public function __construct(public Channel $channel, public Product $product) {}


    public function compose(?int $channelCategoryId = null): array
    {
// Global channel mapping (no product_id), then per-product override
        $global = AttributeMapping::where('channel_id', $this->channel->id)
            ->whereNull('product_id')->first();
        $perProduct = AttributeMapping::where('channel_id', $this->channel->id)
            ->where('product_id', $this->product->id)->first();


        $base = $global?->mapping_json ?? [];
        $over = $perProduct?->mapping_json ?? [];
        $merged = array_replace_recursive($base, $over);


// Optional: inject category schema hints
        if ($channelCategoryId) {
            $cat = ChannelCategory::find($channelCategoryId);
            if ($cat) { $merged['category'] = $cat->external_category_id; }
        }
        return $merged;
    }
}
