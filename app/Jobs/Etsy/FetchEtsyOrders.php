<?php

namespace App\Jobs\Etsy;

use App\Models\Channel;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ProductVariant;
use App\Services\Etsy\EtsyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchEtsyOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Channel $channel,
        public ?int $minCreated = null
    ) {}

    public function handle(): void
    {
        try {
            $client = new EtsyClient($this->channel);
            $shopId = $this->channel->auth_json['shop_id'] ?? null;

            if (!$shopId) {
                throw new \Exception('No Etsy shop ID found in channel configuration');
            }

            $params = [
                'limit' => 100,
                'offset' => 0,
                'min_created' => $this->minCreated ?? now()->subDays(30)->timestamp,
            ];

            $response = $client->getShopReceipts($shopId, $params);
            $receipts = $response['results'] ?? [];

            foreach ($receipts as $receiptData) {
                $this->processReceipt($receiptData, $shopId, $client);
            }

            Log::info("Fetched " . count($receipts) . " orders from Etsy for channel {$this->channel->id}");
        } catch (\Exception $e) {
            Log::error("Failed to fetch Etsy orders for channel {$this->channel->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single Etsy receipt (order)
     */
    protected function processReceipt(array $receiptData, int $shopId, EtsyClient $client): void
    {
        $receiptId = $receiptData['receipt_id'];

        // Fetch transactions (line items) for this receipt
        $transactionsResponse = $client->getReceiptTransactions($shopId, $receiptId);
        $transactions = $transactionsResponse['results'] ?? [];

        // Calculate totals
        $totalPrice = $receiptData['grandtotal']['amount'] ?? 0;
        $totalPrice = $totalPrice / ($receiptData['grandtotal']['divisor'] ?? 100); // Convert from cents

        $order = ChannelOrder::updateOrCreate(
            [
                'channel_id' => $this->channel->id,
                'channel_order_id' => (string) $receiptId,
            ],
            [
                'shop_id' => $this->channel->shop_id,
                'order_number' => $receiptData['receipt_id'],
                'financial_status' => $this->mapFinancialStatus($receiptData),
                'fulfillment_status' => $this->mapFulfillmentStatus($receiptData),
                'currency' => $receiptData['grandtotal']['currency_code'] ?? 'USD',
                'total_price' => $totalPrice,
                'customer_email' => $receiptData['buyer_email'] ?? null,
                'customer_name' => $receiptData['name'] ?? null,
                'shipping_address' => [
                    'name' => $receiptData['name'] ?? null,
                    'address1' => $receiptData['first_line'] ?? null,
                    'address2' => $receiptData['second_line'] ?? null,
                    'city' => $receiptData['city'] ?? null,
                    'province' => $receiptData['state'] ?? null,
                    'zip' => $receiptData['zip'] ?? null,
                    'country' => $receiptData['country_iso'] ?? null,
                ],
                'placed_at' => isset($receiptData['create_timestamp']) ?
                    \Carbon\Carbon::createFromTimestamp($receiptData['create_timestamp']) : null,
                'raw_data' => $receiptData,
            ]
        );

        // Process transactions (line items)
        foreach ($transactions as $transaction) {
            $this->processTransaction($order, $transaction);
        }
    }

    /**
     * Process a single transaction (line item)
     */
    protected function processTransaction(ChannelOrder $order, array $transaction): void
    {
        $sku = $transaction['sku'] ?? null;
        $variant = $sku ? ProductVariant::where('sku', $sku)->first() : null;

        // Calculate price
        $price = $transaction['price']['amount'] ?? 0;
        $price = $price / ($transaction['price']['divisor'] ?? 100); // Convert from cents

        $quantity = (int) ($transaction['quantity'] ?? 1);
        $total = $price * $quantity;

        ChannelOrderItem::updateOrCreate(
            [
                'channel_order_id' => $order->id,
                'channel_line_id' => (string) $transaction['transaction_id'],
            ],
            [
                'product_variant_id' => $variant?->id,
                'sku' => $sku,
                'title' => $transaction['title'] ?? 'Unknown Product',
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
                'raw_data' => $transaction,
            ]
        );
    }

    /**
     * Map Etsy payment status to our financial status
     */
    protected function mapFinancialStatus(array $receiptData): string
    {
        // Etsy receipts are paid once created
        $isPaid = $receiptData['is_paid'] ?? false;
        return $isPaid ? 'paid' : 'pending';
    }

    /**
     * Map Etsy shipment status to our fulfillment status
     */
    protected function mapFulfillmentStatus(array $receiptData): string
    {
        $isShipped = $receiptData['is_shipped'] ?? false;
        $wasShipped = $receiptData['was_shipped'] ?? false;

        if ($isShipped || $wasShipped) {
            return 'fulfilled';
        }

        return 'unfulfilled';
    }
}
