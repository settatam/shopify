<?php

namespace App\Jobs\Square;

use App\Models\Channel;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ProductVariant;
use App\Services\Square\SquareClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchSquareOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Channel $channel,
        public ?string $beginTime = null
    ) {}

    public function handle(): void
    {
        try {
            $client = new SquareClient($this->channel);
            $locationId = $client->getConfiguredLocation();

            if (!$locationId) {
                throw new \Exception('No Square location configured');
            }

            // Build search query
            $query = [
                'location_ids' => [$locationId],
                'query' => [
                    'filter' => [
                        'state_filter' => [
                            'states' => ['COMPLETED'],
                        ],
                    ],
                    'sort' => [
                        'sort_field' => 'CREATED_AT',
                        'sort_order' => 'DESC',
                    ],
                ],
                'limit' => 100,
            ];

            // Add date filter if provided
            if ($this->beginTime) {
                $query['query']['filter']['date_time_filter'] = [
                    'created_at' => [
                        'start_at' => $this->beginTime,
                    ],
                ];
            } else {
                // Default to last 7 days
                $query['query']['filter']['date_time_filter'] = [
                    'created_at' => [
                        'start_at' => now()->subDays(7)->toIso8601String(),
                    ],
                ];
            }

            $response = $client->searchOrders($query);
            $orders = $response['orders'] ?? [];

            foreach ($orders as $orderData) {
                $this->processOrder($orderData);
            }

            Log::info("Fetched " . count($orders) . " orders from Square for channel {$this->channel->id}");
        } catch (\Exception $e) {
            Log::error("Failed to fetch Square orders for channel {$this->channel->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single Square order
     */
    protected function processOrder(array $orderData): void
    {
        $orderId = $orderData['id'];

        // Calculate totals
        $totalMoney = $orderData['total_money'] ?? [];
        $totalPrice = isset($totalMoney['amount']) ? $totalMoney['amount'] / 100 : 0;
        $currency = $totalMoney['currency'] ?? 'USD';

        // Get customer name from tenders or default
        $customerName = $this->extractCustomerName($orderData);

        $order = ChannelOrder::updateOrCreate(
            [
                'channel_id' => $this->channel->id,
                'channel_order_id' => $orderId,
            ],
            [
                'shop_id' => $this->channel->shop_id,
                'order_number' => $orderData['reference_id'] ?? $orderId,
                'financial_status' => $orderData['state'] === 'COMPLETED' ? 'paid' : 'pending',
                'fulfillment_status' => 'unfulfilled',
                'currency' => $currency,
                'total_price' => $totalPrice,
                'customer_name' => $customerName,
                'placed_at' => isset($orderData['created_at']) ?
                    \Carbon\Carbon::parse($orderData['created_at']) : now(),
                'raw_data' => $orderData,
            ]
        );

        // Process line items
        $lineItems = $orderData['line_items'] ?? [];
        foreach ($lineItems as $lineItem) {
            $this->processLineItem($order, $lineItem);
        }

        // Update local inventory (decrease stock)
        $this->updateLocalInventory($order);
    }

    /**
     * Process a single line item
     */
    protected function processLineItem(ChannelOrder $order, array $lineItem): void
    {
        $catalogObjectId = $lineItem['catalog_object_id'] ?? null;
        $variant = null;

        // Try to find variant by Square variation ID
        if ($catalogObjectId) {
            $variant = ProductVariant::whereJsonContains('sync_metadata->square_variation_id', $catalogObjectId)->first();
        }

        // Get price
        $basePriceMoney = $lineItem['base_price_money'] ?? [];
        $price = isset($basePriceMoney['amount']) ? $basePriceMoney['amount'] / 100 : 0;

        $quantity = (int) ($lineItem['quantity'] ?? 1);
        $totalMoney = $lineItem['total_money'] ?? [];
        $total = isset($totalMoney['amount']) ? $totalMoney['amount'] / 100 : 0;

        ChannelOrderItem::updateOrCreate(
            [
                'channel_order_id' => $order->id,
                'channel_line_id' => $lineItem['uid'],
            ],
            [
                'product_variant_id' => $variant?->id,
                'sku' => $variant?->sku,
                'title' => $lineItem['name'] ?? 'Unknown Product',
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
                'raw_data' => $lineItem,
            ]
        );
    }

    /**
     * Update local inventory after Square sale
     */
    protected function updateLocalInventory(ChannelOrder $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                $variant = $item->productVariant;

                // Get the first stock item (or primary location)
                $stockItem = $variant->stockItems()->first();

                if ($stockItem) {
                    // Decrease quantity
                    $newQuantity = max(0, $stockItem->quantity - $item->quantity);
                    $stockItem->update(['quantity' => $newQuantity]);

                    Log::info("Decreased local inventory for variant {$variant->id} by {$item->quantity}");
                }
            }
        }
    }

    /**
     * Extract customer name from order data
     */
    protected function extractCustomerName(array $orderData): string
    {
        // Try to get from fulfillments
        $fulfillments = $orderData['fulfillments'] ?? [];
        foreach ($fulfillments as $fulfillment) {
            $recipient = $fulfillment['shipment_details']['recipient'] ?? [];
            if (!empty($recipient['display_name'])) {
                return $recipient['display_name'];
            }
        }

        // Try to get from tenders
        $tenders = $orderData['tenders'] ?? [];
        foreach ($tenders as $tender) {
            if (!empty($tender['customer_id'])) {
                return 'Customer ' . substr($tender['customer_id'], 0, 8);
            }
        }

        return 'Walk-in Customer';
    }
}
