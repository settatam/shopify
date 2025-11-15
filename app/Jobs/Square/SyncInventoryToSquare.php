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

class SyncInventoryToSquare implements ShouldQueue
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
            $locationId = $client->getConfiguredLocation();

            if (!$locationId) {
                throw new \Exception('No Square location configured');
            }

            // Get channel policy for inventory calculation
            $policy = $this->squareChannel->policy_json ?? [];
            $includedLocations = $policy['included_locations'] ?? [];
            $safetyStock = $policy['safety_stock'] ?? 0;

            // Sync each variant's inventory
            foreach ($this->product->variants as $variant) {
                $variationId = $variant->sync_metadata['square_variation_id'] ?? null;

                if (!$variationId) {
                    Log::warning("Variant {$variant->id} has no Square variation ID, skipping inventory sync");
                    continue;
                }

                // Calculate available quantity
                $totalQuantity = $variant->stockItems()
                    ->when(!empty($includedLocations), function ($query) use ($includedLocations) {
                        return $query->whereIn('location_id', $includedLocations);
                    })
                    ->sum('quantity');

                $reserved = $variant->stockItems()
                    ->when(!empty($includedLocations), function ($query) use ($includedLocations) {
                        return $query->whereIn('location_id', $includedLocations);
                    })
                    ->sum('reserved');

                $available = max(0, $totalQuantity - $reserved - $safetyStock);

                // Update inventory in Square
                $response = $client->updateInventory(
                    $variationId,
                    $locationId,
                    (int) $available
                );

                Log::info("Synced inventory to Square for variant {$variant->id}: {$available} units");
            }

            Log::info("Inventory synced to Square for product {$this->product->id}");
        } catch (\Exception $e) {
            Log::error("Failed to sync inventory to Square for product {$this->product->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
