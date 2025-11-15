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

class SyncOrderToZoho implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected Channel $channel;
    protected ChannelOrder $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Channel $channel, ChannelOrder $order)
    {
        $this->channel = $channel;
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $client = new ZohoInventoryClient($this->channel);

            // Check if order is from this Zoho channel (don't sync back)
            if ($this->order->channel_id === $this->channel->id) {
                Log::debug('Skipping sync - order is from this Zoho channel', [
                    'order_id' => $this->order->id,
                ]);
                return;
            }

            // Check if already synced
            $metadata = $this->order->metadata ?? [];
            $zohoSalesOrderId = $metadata['zoho_inventory_sales_order_id'] ?? null;

            // Build sales order data
            $orderData = $client->buildSalesOrderData([
                'customer_name' => $this->order->customer_name,
                'customer_email' => $this->order->customer_email,
                'customer_phone' => $this->order->customer_phone,
                'order_number' => $this->order->order_number,
                'created_at' => $this->order->order_date ?? $this->order->created_at,
                'notes' => $this->order->notes,
                'shipping_address' => $this->order->shipping_address
                    ? json_decode($this->order->shipping_address, true)
                    : null,
                'items' => $this->order->items->map(function ($item) {
                    return [
                        'title' => $item->name,
                        'sku' => $item->sku,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                    ];
                })->toArray(),
            ]);

            if ($zohoSalesOrderId) {
                // Update existing sales order
                $result = $client->updateSalesOrder($zohoSalesOrderId, $orderData);
                Log::info('Order updated in Zoho Inventory', [
                    'order_id' => $this->order->id,
                    'zoho_sales_order_id' => $zohoSalesOrderId,
                ]);
            } else {
                // Create new sales order
                $result = $client->createSalesOrder($orderData);
                $zohoSalesOrderId = $result['salesorder']['salesorder_id'] ?? null;

                if ($zohoSalesOrderId) {
                    // Store Zoho ID in metadata
                    $metadata['zoho_inventory_sales_order_id'] = $zohoSalesOrderId;
                    $this->order->update(['metadata' => $metadata]);

                    // Auto-confirm if configured
                    $settings = $this->channel->settings ?? [];
                    if ($settings['auto_confirm_orders'] ?? false) {
                        $client->confirmSalesOrder($zohoSalesOrderId);
                        Log::info('Order auto-confirmed in Zoho', [
                            'zoho_sales_order_id' => $zohoSalesOrderId,
                        ]);
                    }

                    Log::info('Order synced to Zoho Inventory', [
                        'order_id' => $this->order->id,
                        'zoho_sales_order_id' => $zohoSalesOrderId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync order to Zoho Inventory', [
                'order_id' => $this->order->id,
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
