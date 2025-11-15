<?php

namespace App\Jobs\ZohoInventory;

use App\Models\Channel;
use App\Models\Product;
use App\Services\ZohoInventory\ZohoInventoryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected Channel $channel;
    protected Product $product;

    /**
     * Create a new job instance.
     */
    public function __construct(Channel $channel, Product $product)
    {
        $this->channel = $channel;
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $client = new ZohoInventoryClient($this->channel);

            // Check if product already synced (stored in mapping)
            $existingMapping = $this->product->channelMappings()
                ->where('channel_id', $this->channel->id)
                ->first();

            // For products with variants, sync each variant as a separate item
            if ($this->product->variants->count() > 1) {
                foreach ($this->product->variants as $variant) {
                    $this->syncVariant($client, $variant, $existingMapping);
                }
            } else {
                // Single product without variants
                $variant = $this->product->variants->first();
                if ($variant) {
                    $this->syncVariant($client, $variant, $existingMapping);
                }
            }

            Log::info('Product synced to Zoho Inventory', [
                'product_id' => $this->product->id,
                'channel_id' => $this->channel->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync product to Zoho Inventory', [
                'product_id' => $this->product->id,
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync a variant to Zoho as an item
     */
    protected function syncVariant($client, $variant, $existingMapping): void
    {
        $itemData = $client->buildItemData([
            'title' => $this->product->title . ($variant->title ? ' - ' . $variant->title : ''),
            'sku' => $variant->sku,
            'description' => $this->product->description,
            'price' => $variant->price,
            'category' => $this->product->category,
            'weight' => $variant->weight,
            'weight_unit' => $variant->weight_unit,
            'is_variant' => $this->product->variants->count() > 1,
            'variant_title' => $variant->title,
            'barcode' => $variant->barcode,
        ]);

        // Check if this variant is already mapped
        $variantMapping = $existingMapping
            ? json_decode($existingMapping->external_data, true)
            : null;

        $zohoItemId = $variantMapping['items'][$variant->id] ?? null;

        if ($zohoItemId) {
            // Update existing item
            $result = $client->updateItem($zohoItemId, $itemData);
        } else {
            // Create new item
            $result = $client->createItem($itemData);
            $zohoItemId = $result['item']['item_id'] ?? null;

            // Store mapping
            if ($zohoItemId) {
                if ($existingMapping) {
                    $externalData = json_decode($existingMapping->external_data, true) ?? [];
                    $externalData['items'][$variant->id] = $zohoItemId;
                    $existingMapping->update(['external_data' => json_encode($externalData)]);
                } else {
                    $this->product->channelMappings()->create([
                        'channel_id' => $this->channel->id,
                        'external_id' => $zohoItemId,
                        'external_data' => json_encode([
                            'items' => [
                                $variant->id => $zohoItemId,
                            ],
                        ]),
                    ]);
                }
            }
        }
    }
}
