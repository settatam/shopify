<?php

namespace App\Jobs\ZohoInventory;

use App\Models\Channel;
use App\Models\ProductVariant;
use App\Services\ZohoInventory\ZohoInventoryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInventoryToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected Channel $channel;
    protected ProductVariant $variant;

    /**
     * Create a new job instance.
     */
    public function __construct(Channel $channel, ProductVariant $variant)
    {
        $this->channel = $channel;
        $this->variant = $variant;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $client = new ZohoInventoryClient($this->channel);

            // Get the Zoho item ID from mapping
            $mapping = $this->variant->product->channelMappings()
                ->where('channel_id', $this->channel->id)
                ->first();

            if (!$mapping) {
                Log::warning('No Zoho mapping found for product', [
                    'product_id' => $this->variant->product_id,
                    'variant_id' => $this->variant->id,
                ]);
                return;
            }

            $externalData = json_decode($mapping->external_data, true);
            $zohoItemId = $externalData['items'][$this->variant->id] ?? null;

            if (!$zohoItemId) {
                Log::warning('No Zoho item ID found for variant', [
                    'variant_id' => $this->variant->id,
                ]);
                return;
            }

            // Get current stock from Zoho
            $stockInfo = $client->getItemStock($zohoItemId);
            $currentZohoStock = $stockInfo['item']['actual_available_stock'] ?? 0;

            // Calculate our available stock
            $ourStock = $this->variant->stockItems->sum('quantity');

            // Apply safety stock if configured
            $settings = $this->channel->settings ?? [];
            $safetyStock = $settings['safety_stock'] ?? 0;
            $availableStock = max(0, $ourStock - $safetyStock);

            // Calculate adjustment needed
            $adjustment = $availableStock - $currentZohoStock;

            if ($adjustment != 0) {
                // Perform inventory adjustment
                $reason = $adjustment > 0
                    ? "Stock increase from multichannel platform"
                    : "Stock decrease from multichannel platform";

                $client->adjustInventory($zohoItemId, $adjustment, $reason);

                Log::info('Inventory synced to Zoho', [
                    'variant_id' => $this->variant->id,
                    'zoho_item_id' => $zohoItemId,
                    'adjustment' => $adjustment,
                    'our_stock' => $ourStock,
                    'available_stock' => $availableStock,
                    'zoho_stock' => $currentZohoStock,
                ]);
            } else {
                Log::debug('Inventory already in sync with Zoho', [
                    'variant_id' => $this->variant->id,
                    'stock' => $availableStock,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync inventory to Zoho', [
                'variant_id' => $this->variant->id,
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
