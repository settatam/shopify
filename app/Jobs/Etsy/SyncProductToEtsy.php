<?php

namespace App\Jobs\Etsy;

use App\Models\Product;
use App\Models\Channel;
use App\Models\ChannelListing;
use App\Services\Etsy\EtsyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToEtsy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public Channel $channel
    ) {}

    public function handle(): void
    {
        try {
            $client = new EtsyClient($this->channel);
            $shopId = $this->channel->auth_json['shop_id'] ?? null;

            if (!$shopId) {
                throw new \Exception('No Etsy shop ID found in channel configuration');
            }

            // Get the listing for this product/channel
            $listing = ChannelListing::where('product_id', $this->product->id)
                ->where('channel_id', $this->channel->id)
                ->first();

            if (!$listing) {
                Log::warning("No listing found for product {$this->product->id} on channel {$this->channel->id}");
                return;
            }

            $mapping = $listing->mapping_json ?? [];

            // Prepare listing data
            $listingData = $this->buildListingData($listing, $mapping);

            // Check if listing already exists on Etsy
            if ($listing->channel_listing_id) {
                // Update existing listing
                $response = $client->updateListing(
                    (int) $listing->channel_listing_id,
                    $listingData
                );

                Log::info("Updated Etsy listing {$listing->channel_listing_id} for product {$this->product->id}");
            } else {
                // Create new listing
                $response = $client->createListing($shopId, $listingData);
                $listingId = $response['listing_id'];

                // Upload images
                $this->uploadImages($client, $shopId, $listingId);

                Log::info("Created Etsy listing {$listingId} for product {$this->product->id}");
            }

            // Update listing record
            $listing->update([
                'channel_listing_id' => $response['listing_id'] ?? $listing->channel_listing_id,
                'status' => $response['state'] === 'active' ? 'active' : 'pending',
                'last_sync_at' => now(),
                'sync_metadata' => [
                    'listing_id' => $response['listing_id'] ?? null,
                    'state' => $response['state'] ?? null,
                    'url' => $response['url'] ?? null,
                    'synced_at' => now()->toIso8601String(),
                ],
            ]);

            // Sync inventory after listing creation/update
            SyncInventoryToEtsy::dispatch($this->product->variants->first(), $this->channel);
        } catch (\Exception $e) {
            Log::error("Failed to sync product {$this->product->id} to Etsy: " . $e->getMessage());

            // Update listing status to error
            if (isset($listing)) {
                $listing->update([
                    'status' => 'error',
                    'sync_metadata' => array_merge($listing->sync_metadata ?? [], [
                        'last_error' => $e->getMessage(),
                        'error_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Build Etsy listing data from product and mapping
     */
    protected function buildListingData(ChannelListing $listing, array $mapping): array
    {
        $variant = $this->product->variants->first();

        $listingData = [
            'quantity' => $mapping['quantity'] ?? 1,
            'title' => substr($mapping['title'] ?? $this->product->title, 0, 140),
            'description' => $mapping['description'] ?? $this->product->description ?? '',
            'price' => $variant->price ?? 0,
            'who_made' => $mapping['who_made'] ?? 'i_did',
            'when_made' => $mapping['when_made'] ?? '2020_2024',
            'taxonomy_id' => $listing->channel_category_id ?
                (int) $listing->channelCategory->external_id : null,
            'is_taxable' => $mapping['is_taxable'] ?? true,
            'should_auto_renew' => $mapping['should_auto_renew'] ?? true,
            'type' => $mapping['type'] ?? 'physical',
        ];

        // Add optional fields if present
        if (!empty($mapping['materials'])) {
            $listingData['materials'] = array_slice($mapping['materials'], 0, 13);
        }

        if (!empty($mapping['tags'])) {
            $listingData['tags'] = array_slice($mapping['tags'], 0, 13);
        }

        if (!empty($mapping['shipping_profile_id'])) {
            $listingData['shipping_profile_id'] = $mapping['shipping_profile_id'];
        }

        if (!empty($mapping['return_policy_id'])) {
            $listingData['return_policy_id'] = $mapping['return_policy_id'];
        }

        // Processing time
        $listingData['processing_min'] = $mapping['processing_min'] ?? 1;
        $listingData['processing_max'] = $mapping['processing_max'] ?? 3;

        // Dimensions and weight
        if (!empty($variant->weight)) {
            $listingData['item_weight'] = $variant->weight;
            $listingData['item_weight_unit'] = $variant->weight_unit ?? 'oz';
        }

        return $listingData;
    }

    /**
     * Upload product images to Etsy listing
     */
    protected function uploadImages(EtsyClient $client, int $shopId, int $listingId): void
    {
        $images = $this->product->images_json ?? [];

        if (empty($images)) {
            return;
        }

        // Etsy allows up to 10 images
        $images = array_slice($images, 0, 10);

        foreach ($images as $imageUrl) {
            try {
                $client->uploadListingImage($shopId, $listingId, $imageUrl);
                Log::info("Uploaded image to Etsy listing {$listingId}");
            } catch (\Exception $e) {
                Log::warning("Failed to upload image to Etsy listing {$listingId}: " . $e->getMessage());
                // Continue with other images even if one fails
            }
        }
    }
}
