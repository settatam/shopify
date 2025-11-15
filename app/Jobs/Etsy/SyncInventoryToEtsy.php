<?php

namespace App\Jobs\Etsy;

use App\Models\ProductVariant;
use App\Models\Channel;
use App\Models\ChannelListing;
use App\Services\Etsy\EtsyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInventoryToEtsy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ProductVariant $variant,
        public Channel $channel
    ) {}

    public function handle(): void
    {
        try {
            $client = new EtsyClient($this->channel);

            // Find the listing for this variant's product
            $listing = ChannelListing::where('product_id', $this->variant->product_id)
                ->where('channel_id', $this->channel->id)
                ->first();

            if (!$listing || !$listing->channel_listing_id) {
                Log::warning("No Etsy listing found for variant {$this->variant->id}");
                return;
            }

            $listingId = (int) $listing->channel_listing_id;

            // Calculate available quantity based on policy
            $quantity = $this->calculateAvailableQuantity();

            // Update inventory on Etsy
            $response = $client->updateListingQuantity($listingId, $quantity);

            Log::info("Inventory synced to Etsy for listing {$listingId}: {$quantity} units");

            // Update listing metadata
            $listing->update([
                'sync_metadata' => array_merge($listing->sync_metadata ?? [], [
                    'last_inventory_sync' => now()->toIso8601String(),
                    'quantity_synced' => $quantity,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync inventory to Etsy for variant {$this->variant->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate available quantity based on channel policy
     */
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
