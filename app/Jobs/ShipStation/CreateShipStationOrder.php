<?php

namespace App\Jobs\ShipStation;

use App\Models\ChannelOrder;
use App\Models\Shop;
use App\Services\ShipStation\ShipStationClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateShipStationOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance
     */
    public function __construct(
        public Shop $shop,
        public ChannelOrder $order
    ) {
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            $client = new ShipStationClient($this->shop);

            // Check if order already exists in ShipStation
            $existingOrder = $client->getOrder($this->order->order_number);

            if ($existingOrder) {
                Log::info("Order {$this->order->order_number} already exists in ShipStation");

                // Update the order
                $orderData = $client->buildOrderData($this->order);
                $orderData['orderId'] = $existingOrder['orderId'];

                $response = $client->createOrder($orderData);
            } else {
                // Create new order
                $orderData = $client->buildOrderData($this->order);
                $response = $client->createOrder($orderData);
            }

            // Store ShipStation order ID
            $fulfillmentData = $this->order->fulfillment_data ?? [];
            $fulfillmentData['shipstation'] = [
                'order_id' => $response['orderId'] ?? null,
                'order_number' => $response['orderNumber'] ?? null,
                'order_key' => $response['orderKey'] ?? null,
                'created_at' => now()->toIso8601String(),
            ];

            $this->order->update([
                'fulfillment_data' => $fulfillmentData,
            ]);

            Log::info("Created/updated order in ShipStation", [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'shipstation_order_id' => $response['orderId'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to create order in ShipStation: " . $e->getMessage(), [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
            ]);

            throw $e;
        }
    }
}
