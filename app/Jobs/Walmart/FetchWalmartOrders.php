<?php

namespace App\Jobs\Walmart;

use App\Models\Channel;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ProductVariant;
use App\Services\Walmart\WalmartClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchWalmartOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Channel $channel,
        public ?string $startDate = null
    ) {}

    public function handle(): void
    {
        try {
            $client = new WalmartClient($this->channel);

            $params = [
                'limit' => 200,
                'createdStartDate' => $this->startDate ?? now()->subDays(7)->toIso8601String(),
            ];

            $response = $client->getOrders($params);

            $orders = $response['list']['elements']['order'] ?? [];

            foreach ($orders as $orderData) {
                $this->processOrder($orderData);
            }

            Log::info("Fetched " . count($orders) . " orders from Walmart for channel {$this->channel->id}");
        } catch (\Exception $e) {
            Log::error("Failed to fetch Walmart orders for channel {$this->channel->id}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function processOrder(array $orderData): void
    {
        $purchaseOrderId = $orderData['purchaseOrderId'];

        $order = ChannelOrder::updateOrCreate(
            [
                'channel_id' => $this->channel->id,
                'channel_order_id' => $purchaseOrderId,
            ],
            [
                'shop_id' => $this->channel->shop_id,
                'order_number' => $purchaseOrderId,
                'financial_status' => $this->mapFinancialStatus($orderData),
                'fulfillment_status' => $this->mapFulfillmentStatus($orderData),
                'currency' => 'USD',
                'total_price' => $orderData['orderLines']['orderLine'][0]['charges']['charge'][0]['chargeAmount']['amount'] ?? 0,
                'customer_email' => $orderData['customerEmailId'] ?? null,
                'customer_name' => $orderData['shippingInfo']['postalAddress']['name'] ?? null,
                'shipping_address' => [
                    'name' => $orderData['shippingInfo']['postalAddress']['name'] ?? null,
                    'address1' => $orderData['shippingInfo']['postalAddress']['address1'] ?? null,
                    'address2' => $orderData['shippingInfo']['postalAddress']['address2'] ?? null,
                    'city' => $orderData['shippingInfo']['postalAddress']['city'] ?? null,
                    'province' => $orderData['shippingInfo']['postalAddress']['state'] ?? null,
                    'zip' => $orderData['shippingInfo']['postalAddress']['postalCode'] ?? null,
                    'country' => $orderData['shippingInfo']['postalAddress']['country'] ?? 'US',
                    'phone' => $orderData['shippingInfo']['phone'] ?? null,
                ],
                'placed_at' => isset($orderData['orderDate']) ? \Carbon\Carbon::parse($orderData['orderDate']) : null,
                'raw_data' => $orderData,
            ]
        );

        // Process order lines
        $orderLines = $orderData['orderLines']['orderLine'] ?? [];
        if (!is_array($orderLines)) {
            $orderLines = [$orderLines];
        }

        foreach ($orderLines as $line) {
            $this->processOrderLine($order, $line);
        }
    }

    protected function processOrderLine(ChannelOrder $order, array $line): void
    {
        $sku = $line['item']['sku'] ?? null;
        $variant = $sku ? ProductVariant::where('sku', $sku)->first() : null;

        ChannelOrderItem::updateOrCreate(
            [
                'channel_order_id' => $order->id,
                'channel_line_id' => $line['lineNumber'] ?? null,
            ],
            [
                'product_variant_id' => $variant?->id,
                'sku' => $sku,
                'title' => $line['item']['productName'] ?? 'Unknown Product',
                'quantity' => (int) ($line['orderLineQuantity']['amount'] ?? 1),
                'price' => (float) ($line['charges']['charge'][0]['chargeAmount']['amount'] ?? 0),
                'total' => (float) ($line['charges']['charge'][0]['chargeAmount']['amount'] ?? 0),
                'raw_data' => $line,
            ]
        );
    }

    protected function mapFinancialStatus(array $orderData): string
    {
        // Walmart orders are pre-paid
        return 'paid';
    }

    protected function mapFulfillmentStatus(array $orderData): string
    {
        $orderStatus = $orderData['orderLines']['orderLine'][0]['orderLineStatuses']['orderLineStatus'][0]['status'] ?? 'Created';

        return match ($orderStatus) {
            'Created', 'Acknowledged' => 'unfulfilled',
            'Shipped' => 'fulfilled',
            'Delivered' => 'delivered',
            'Cancelled' => 'cancelled',
            default => 'unfulfilled',
        };
    }
}
