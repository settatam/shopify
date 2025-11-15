<?php

namespace App\Jobs\Xero;

use App\Models\Product;
use App\Models\Channel;
use App\Services\Xero\XeroClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncItemsToXero implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public Channel $xeroChannel
    ) {}

    public function handle(): void
    {
        try {
            $client = new XeroClient($this->xeroChannel);

            // Get account codes from channel policy
            $policy = $this->xeroChannel->policy_json ?? [];
            $salesAccountCode = $policy['sales_account_code'] ?? null;
            $purchaseAccountCode = $policy['purchase_account_code'] ?? null;

            if (!$salesAccountCode) {
                throw new \Exception('Xero sales account not configured. Please set up account codes in channel settings.');
            }

            // Sync each variant as a separate item
            foreach ($this->product->variants as $variant) {
                $this->syncVariant($client, $variant, $salesAccountCode, $purchaseAccountCode);
            }

            Log::info("Product {$this->product->id} synced to Xero");
        } catch (\Exception $e) {
            Log::error("Failed to sync product {$this->product->id} to Xero: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync a product variant as a Xero item
     */
    protected function syncVariant(
        XeroClient $client,
        $variant,
        string $salesAccountCode,
        ?string $purchaseAccountCode
    ): void {
        // Check if item already exists by SKU/Code
        $existingItem = null;
        $sku = $variant->sku;

        if ($sku) {
            $existingItem = $client->searchItemByCode($sku);
        }

        // Calculate total quantity across all locations
        $totalQuantity = $variant->stockItems()->sum('quantity');

        // Build item name
        $itemName = $this->buildItemName($variant);
        $itemCode = $sku ?: substr($itemName, 0, 30);

        // Prepare item data
        $itemData = [
            'Code' => $itemCode,
            'Name' => substr($itemName, 0, 50),
            'Description' => $this->product->description ?? '',
            'IsTrackedAsInventory' => true,
            'IsSold' => true,
            'IsPurchased' => true,
            'SalesDetails' => [
                'UnitPrice' => $variant->price ?? 0,
                'AccountCode' => $salesAccountCode,
            ],
        ];

        // Add purchase details if account provided
        if ($purchaseAccountCode) {
            $itemData['PurchaseDetails'] = [
                'UnitPrice' => $variant->cost ?? 0,
                'AccountCode' => $purchaseAccountCode,
            ];
        }

        // Add inventory quantity
        if ($totalQuantity > 0) {
            $itemData['QuantityOnHand'] = $totalQuantity;
        }

        try {
            if ($existingItem) {
                // Update existing item
                $itemId = $existingItem['ItemID'];
                $response = $client->updateItem($itemId, $itemData);
                Log::info("Updated Xero item {$itemId} for variant {$variant->id}");
            } else {
                // Create new item
                $response = $client->createItem($itemData);
                $item = $response['Items'][0] ?? [];
                $itemId = $item['ItemID'] ?? null;
                Log::info("Created Xero item {$itemId} for variant {$variant->id}");
            }

            // Store Xero item ID in variant metadata
            $variant->update([
                'sync_metadata' => array_merge($variant->sync_metadata ?? [], [
                    'xero_item_id' => $itemId ?? $existingItem['ItemID'],
                    'xero_item_code' => $itemCode,
                    'xero_synced_at' => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync variant {$variant->id} to Xero: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Build item name from product and variant
     */
    protected function buildItemName($variant): string
    {
        $name = $this->product->title;

        // Add variant title if it's different
        if ($variant->title && $variant->title !== 'Default Title') {
            $name .= ' - ' . $variant->title;
        }

        return $name;
    }
}
