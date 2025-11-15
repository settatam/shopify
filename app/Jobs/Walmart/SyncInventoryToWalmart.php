<?php

namespace App\Jobs\Walmart;

use App\Models\ProductVariant;
use App\Models\Channel;
use App\Services\Walmart\WalmartClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInventoryToWalmart implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ProductVariant $variant,
        public Channel $channel
    ) {}

    public function handle(): void
    {
        try {
            $client = new WalmartClient($this->channel);

            // Calculate available quantity based on policy
            $quantity = $this->calculateAvailableQuantity();

            // Update inventory on Walmart
            $response = $client->updateInventory($this->variant->sku, $quantity);

            Log::info("Inventory synced to Walmart for SKU {$this->variant->sku}: {$quantity} units, feed ID: {$response['feedId'] ?? 'N/A'}");
        } catch (\Exception $e) {
            Log::error("Failed to sync inventory to Walmart for SKU {$this->variant->sku}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function calculateAvailableQuantity(): int
    {
        // Get channel policy
        $policy = $this->channel->policy_json ?? [];
        $safetyStock = $policy['safety_stock'] ?? 0;
        $includedLocations = $policy['included_locations'] ?? [];

        // Calculate total quantity from included locations
        $totalQuantity = $this->variant->stockItems()
            ->when(!empty($includedLocations), function ($query) use ($includedLocations) {
                return $query->whereIn('location_id', $includedLocations);
            })
            ->sum('quantity');

        // Subtract reserved quantities
        $reserved = $this->variant->stockItems()
            ->when(!empty($includedLocations), function ($query) use ($includedLocations) {
                return $query->whereIn('location_id', $includedLocations);
            })
            ->sum('reserved');

        // Apply safety stock
        $available = max(0, $totalQuantity - $reserved - $safetyStock);

        return (int) $available;
    }
}
