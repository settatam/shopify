<?php

namespace App\Jobs\Square;

use App\Models\Product;
use App\Models\Channel;
use App\Services\Square\SquareClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCatalogToSquare implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public Channel $squareChannel
    ) {}

    public function handle(): void
    {
        try {
            $client = new SquareClient($this->squareChannel);

            // Build catalog item from product
            $variants = $this->product->variants->map(function ($variant) {
                return [
                    'sku' => $variant->sku,
                    'title' => $variant->title !== 'Default Title' ? $variant->title : $this->product->title,
                    'price' => $variant->price,
                ];
            })->toArray();

            $catalogItem = $client->buildCatalogItem([
                'id' => $this->product->id,
                'sku' => $this->product->variants->first()->sku ?? 'PROD-' . $this->product->id,
                'title' => $this->product->title,
                'description' => $this->product->description,
                'variants' => $variants,
            ]);

            // Check if item already exists in Square
            $existingItemId = $this->findExistingItem($client);

            if ($existingItemId) {
                // Update existing item
                $catalogItem['id'] = $existingItemId;
                $response = $client->updateCatalogObject($existingItemId, $catalogItem);
                Log::info("Updated Square catalog item {$existingItemId} for product {$this->product->id}");
            } else {
                // Create new item
                $response = $client->createCatalogObject($catalogItem);
                $catalogObject = $response['catalog_object'] ?? [];
                $itemId = $catalogObject['id'] ?? null;
                Log::info("Created Square catalog item {$itemId} for product {$this->product->id}");
            }

            // Store Square item ID in product metadata
            $squareItemId = $response['catalog_object']['id'] ?? $existingItemId;
            $this->product->update([
                'sync_metadata' => array_merge($this->product->sync_metadata ?? [], [
                    'square_item_id' => $squareItemId,
                    'square_synced_at' => now()->toIso8601String(),
                ]),
            ]);

            // Also store variation IDs in variants
            $catalogObject = $response['catalog_object'] ?? [];
            $variations = $catalogObject['item_data']['variations'] ?? [];

            foreach ($variations as $variation) {
                $sku = $variation['item_variation_data']['sku'] ?? null;
                if ($sku) {
                    $variant = $this->product->variants()->where('sku', $sku)->first();
                    if ($variant) {
                        $variant->update([
                            'sync_metadata' => array_merge($variant->sync_metadata ?? [], [
                                'square_variation_id' => $variation['id'],
                            ]),
                        ]);
                    }
                }
            }

            // Sync inventory after catalog is created/updated
            SyncInventoryToSquare::dispatch($this->product, $this->squareChannel);
        } catch (\Exception $e) {
            Log::error("Failed to sync product {$this->product->id} to Square: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Find existing item in Square by SKU
     */
    protected function findExistingItem(SquareClient $client): ?string
    {
        // Check if we already have a Square item ID
        $squareItemId = $this->product->sync_metadata['square_item_id'] ?? null;
        if ($squareItemId) {
            return $squareItemId;
        }

        // Search by SKU
        $sku = $this->product->variants->first()->sku ?? null;
        if (!$sku) {
            return null;
        }

        try {
            $searchResult = $client->searchCatalog([
                'object_types' => ['ITEM'],
                'query' => [
                    'text_query' => [
                        'keywords' => [$sku],
                    ],
                ],
            ]);

            $objects = $searchResult['objects'] ?? [];
            if (!empty($objects)) {
                return $objects[0]['id'];
            }
        } catch (\Exception $e) {
            Log::warning("Failed to search for existing Square item: " . $e->getMessage());
        }

        return null;
    }
}
