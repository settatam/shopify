<?php

namespace App\Jobs\QuickBooks;

use App\Models\Product;
use App\Models\Channel;
use App\Services\QuickBooks\QuickBooksClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductsToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public Channel $quickbooksChannel
    ) {}

    public function handle(): void
    {
        try {
            $client = new QuickBooksClient($this->quickbooksChannel);

            // Get account IDs from channel policy
            $policy = $this->quickbooksChannel->policy_json ?? [];
            $incomeAccountId = $policy['income_account_id'] ?? null;
            $expenseAccountId = $policy['expense_account_id'] ?? null;
            $assetAccountId = $policy['asset_account_id'] ?? null;

            if (!$incomeAccountId || !$expenseAccountId || !$assetAccountId) {
                throw new \Exception('QuickBooks accounts not configured. Please set up income, expense, and asset accounts in channel settings.');
            }

            // Sync each variant as a separate item
            foreach ($this->product->variants as $variant) {
                $this->syncVariant($client, $variant, $incomeAccountId, $expenseAccountId, $assetAccountId);
            }

            Log::info("Product {$this->product->id} synced to QuickBooks");
        } catch (\Exception $e) {
            Log::error("Failed to sync product {$this->product->id} to QuickBooks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync a product variant as a QuickBooks item
     */
    protected function syncVariant(
        QuickBooksClient $client,
        $variant,
        string $incomeAccountId,
        string $expenseAccountId,
        string $assetAccountId
    ): void {
        // Check if item already exists by SKU
        $existingItemId = null;
        $sku = $variant->sku;

        if ($sku) {
            try {
                $query = "SELECT * FROM Item WHERE Sku = '{$sku}' MAXRESULTS 1";
                $result = $client->query($query);

                if (!empty($result['QueryResponse']['Item'])) {
                    $existingItemId = $result['QueryResponse']['Item'][0]['Id'];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to query QuickBooks item: " . $e->getMessage());
            }
        }

        // Calculate total quantity across all locations
        $totalQuantity = $variant->stockItems()->sum('quantity');

        // Prepare item data
        $itemData = [
            'Name' => $this->buildItemName($variant),
            'Description' => $this->product->description ?? '',
            'Type' => 'Inventory',
            'TrackQtyOnHand' => true,
            'QtyOnHand' => $totalQuantity,
            'InvStartDate' => now()->format('Y-m-d'),
            'UnitPrice' => $variant->price ?? 0,
            'IncomeAccountRef' => [
                'value' => $incomeAccountId,
            ],
            'ExpenseAccountRef' => [
                'value' => $expenseAccountId,
            ],
            'AssetAccountRef' => [
                'value' => $assetAccountId,
            ],
        ];

        if ($sku) {
            $itemData['Sku'] = $sku;
        }

        // Add cost if available
        if ($variant->cost) {
            $itemData['PurchaseCost'] = $variant->cost;
        }

        try {
            if ($existingItemId) {
                // Update existing item
                $response = $client->updateItem($existingItemId, $itemData);
                $itemId = $existingItemId;
                Log::info("Updated QuickBooks item {$itemId} for variant {$variant->id}");
            } else {
                // Create new item
                $response = $client->createItem($itemData);
                $itemId = $response['Item']['Id'];
                Log::info("Created QuickBooks item {$itemId} for variant {$variant->id}");
            }

            // Store QuickBooks item ID in variant metadata
            $variant->update([
                'sync_metadata' => array_merge($variant->sync_metadata ?? [], [
                    'quickbooks_item_id' => $itemId,
                    'quickbooks_synced_at' => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync variant {$variant->id} to QuickBooks: " . $e->getMessage());
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

        // Truncate to 100 characters (QuickBooks limit)
        return substr($name, 0, 100);
    }
}
