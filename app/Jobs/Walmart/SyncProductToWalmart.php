<?php

namespace App\Jobs\Walmart;

use App\Models\Product;
use App\Models\Channel;
use App\Models\ChannelListing;
use App\Services\Walmart\WalmartClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToWalmart implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public Channel $channel
    ) {}

    public function handle(): void
    {
        try {
            $client = new WalmartClient($this->channel);

            // Get the listing for this product/channel
            $listing = ChannelListing::where('product_id', $this->product->id)
                ->where('channel_id', $this->channel->id)
                ->first();

            if (!$listing) {
                Log::warning("No listing found for product {$this->product->id} on channel {$this->channel->id}");
                return;
            }

            $mapping = $listing->mapping_json ?? [];

            // Build item data for Walmart
            $items = [];

            foreach ($this->product->variants as $variant) {
                $items[] = [
                    'sku' => $variant->sku,
                    'productName' => $mapping['title'] ?? $this->product->title,
                    'shortDescription' => $this->truncate($mapping['description'] ?? $this->product->description, 4000),
                    'price' => $variant->price,
                    'upc' => $variant->barcode ?? '',
                    'gtin' => $variant->barcode ?? '',
                    'brand' => $mapping['brand'] ?? $this->product->vendor ?? 'Generic',
                    'images' => is_array($this->product->images_json) ? array_slice($this->product->images_json, 0, 8) : [],
                    'category' => $listing->channelCategory->external_id ?? null,
                ];
            }

            // Submit to Walmart as feed
            $response = $client->bulkItemSetup($items);

            // Update listing with feed ID
            $listing->update([
                'channel_product_id' => $response['feedId'] ?? null,
                'status' => 'pending',
                'last_sync_at' => now(),
                'sync_metadata' => [
                    'feed_id' => $response['feedId'] ?? null,
                    'submitted_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info("Product {$this->product->id} synced to Walmart, feed ID: {$response['feedId']}");
        } catch (\Exception $e) {
            Log::error("Failed to sync product {$this->product->id} to Walmart: " . $e->getMessage());
            throw $e;
        }
    }

    protected function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
