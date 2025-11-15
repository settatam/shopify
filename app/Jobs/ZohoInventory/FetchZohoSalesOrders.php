<?php

namespace App\Jobs\ZohoInventory;

use App\Models\Channel;
use App\Models\ChannelOrder;
use App\Services\ZohoInventory\ZohoInventoryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchZohoSalesOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected Channel $channel;

    /**
     * Create a new job instance.
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $client = new ZohoInventoryClient($this->channel);

            // Fetch recent orders (last 30 days)
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $result = $client->getSalesOrders($page, [
                    'date' => Carbon::now()->subDays(30)->format('Y-m-d'),
                ]);

                $orders = $result['salesorders'] ?? [];

                foreach ($orders as $zohoOrder) {
                    $this->importOrder($zohoOrder);
                }

                // Check if there are more pages
                $pageContext = $result['page_context'] ?? [];
                $hasMore = $pageContext['has_more_page'] ?? false;
                $page++;
            }

            Log::info('Zoho sales orders fetched successfully', [
                'channel_id' => $this->channel->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Zoho sales orders', [
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Import a single order
     */
    protected function importOrder(array $zohoOrder): void
    {
        // Check if order already exists
        $existingOrder = ChannelOrder::where('channel_id', $this->channel->id)
            ->where('external_order_id', $zohoOrder['salesorder_id'])
            ->first();

        if ($existingOrder) {
            // Update existing order
            $this->updateOrder($existingOrder, $zohoOrder);
            return;
        }

        // Create new order
        try {
            $orderData = [
                'shop_id' => $this->channel->shop_id,
                'channel_id' => $this->channel->id,
                'external_order_id' => $zohoOrder['salesorder_id'],
                'order_number' => $zohoOrder['salesorder_number'],
                'customer_name' => $zohoOrder['customer_name'] ?? 'Guest',
                'customer_email' => $zohoOrder['email'] ?? null,
                'customer_phone' => $zohoOrder['phone'] ?? null,
                'subtotal' => $zohoOrder['sub_total'] ?? 0,
                'tax_amount' => $zohoOrder['tax_total'] ?? 0,
                'shipping_amount' => $zohoOrder['shipping_charge'] ?? 0,
                'discount_amount' => $zohoOrder['discount'] ?? 0,
                'total_amount' => $zohoOrder['total'] ?? 0,
                'currency' => $zohoOrder['currency_code'] ?? 'USD',
                'status' => $this->mapOrderStatus($zohoOrder['status']),
                'payment_status' => $this->mapPaymentStatus($zohoOrder),
                'fulfillment_status' => $this->mapFulfillmentStatus($zohoOrder['shipment_status'] ?? ''),
                'notes' => $zohoOrder['notes'] ?? null,
                'order_date' => Carbon::parse($zohoOrder['date'])->toDateTimeString(),
                'raw_data' => json_encode($zohoOrder),
            ];

            // Add shipping address
            if (!empty($zohoOrder['shipping_address'])) {
                $address = $zohoOrder['shipping_address'];
                $orderData['shipping_address'] = json_encode([
                    'address1' => $address['address'] ?? '',
                    'city' => $address['city'] ?? '',
                    'province' => $address['state'] ?? '',
                    'zip' => $address['zip'] ?? '',
                    'country' => $address['country'] ?? '',
                ]);
            }

            $order = ChannelOrder::create($orderData);

            // Add line items
            $this->addLineItems($order, $zohoOrder['line_items'] ?? []);

            Log::info('Zoho sales order imported', [
                'order_id' => $order->id,
                'zoho_order_id' => $zohoOrder['salesorder_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to import Zoho order', [
                'zoho_order_id' => $zohoOrder['salesorder_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update existing order
     */
    protected function updateOrder(ChannelOrder $order, array $zohoOrder): void
    {
        $order->update([
            'status' => $this->mapOrderStatus($zohoOrder['status']),
            'payment_status' => $this->mapPaymentStatus($zohoOrder),
            'fulfillment_status' => $this->mapFulfillmentStatus($zohoOrder['shipment_status'] ?? ''),
            'raw_data' => json_encode($zohoOrder),
        ]);

        Log::debug('Zoho order updated', [
            'order_id' => $order->id,
            'zoho_order_id' => $zohoOrder['salesorder_id'],
        ]);
    }

    /**
     * Add line items to order
     */
    protected function addLineItems(ChannelOrder $order, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            $order->items()->create([
                'external_product_id' => $item['item_id'] ?? null,
                'sku' => $item['sku'] ?? null,
                'name' => $item['name'] ?? 'Unknown Item',
                'quantity' => $item['quantity'] ?? 1,
                'price' => $item['rate'] ?? 0,
                'total' => $item['item_total'] ?? 0,
            ]);
        }
    }

    /**
     * Map Zoho order status to our status
     */
    protected function mapOrderStatus(string $zohoStatus): string
    {
        $statusMap = [
            'draft' => 'pending',
            'confirmed' => 'processing',
            'approved' => 'processing',
            'invoiced' => 'completed',
            'closed' => 'completed',
            'void' => 'cancelled',
        ];

        return $statusMap[strtolower($zohoStatus)] ?? 'pending';
    }

    /**
     * Map payment status
     */
    protected function mapPaymentStatus(array $zohoOrder): string
    {
        // Zoho doesn't have direct payment status on sales order
        // We infer from invoiced status
        if (isset($zohoOrder['invoiced_status'])) {
            if ($zohoOrder['invoiced_status'] === 'invoiced') {
                return 'paid';
            }
        }

        return 'pending';
    }

    /**
     * Map fulfillment/shipment status
     */
    protected function mapFulfillmentStatus(string $shipmentStatus): string
    {
        $statusMap = [
            'not_shipped' => 'unfulfilled',
            'partially_shipped' => 'partial',
            'shipped' => 'fulfilled',
            'delivered' => 'delivered',
        ];

        return $statusMap[strtolower($shipmentStatus)] ?? 'unfulfilled';
    }
}
