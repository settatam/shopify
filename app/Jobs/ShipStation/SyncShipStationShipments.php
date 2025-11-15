<?php

namespace App\Jobs\ShipStation;

use App\Models\Shop;
use App\Models\ChannelOrder;
use App\Services\ShipStation\ShipStationClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncShipStationShipments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 120, 300];

    protected ?string $createDateStart;

    /**
     * Create a new job instance
     */
    public function __construct(
        public Shop $shop,
        ?string $createDateStart = null
    ) {
        // Default to last 7 days
        $this->createDateStart = $createDateStart ?? Carbon::now()->subDays(7)->toIso8601String();
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            $client = new ShipStationClient($this->shop);

            $params = [
                'createDateStart' => $this->createDateStart,
                'pageSize' => 500,
                'page' => 1,
            ];

            Log::info('Syncing ShipStation shipments', [
                'shop_id' => $this->shop->id,
                'create_date_start' => $this->createDateStart,
            ]);

            do {
                $response = $client->getShipments($params);
                $shipments = $response['shipments'] ?? [];
                $totalPages = $response['pages'] ?? 1;

                foreach ($shipments as $shipment) {
                    $this->processShipment($shipment);
                }

                $params['page']++;
            } while ($params['page'] <= $totalPages);

            Log::info('Completed ShipStation shipments sync', [
                'shop_id' => $this->shop->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync ShipStation shipments: ' . $e->getMessage(), [
                'shop_id' => $this->shop->id,
            ]);

            throw $e;
        }
    }

    /**
     * Process a single shipment
     */
    protected function processShipment(array $shipment): void
    {
        $orderNumber = $shipment['orderNumber'] ?? null;

        if (!$orderNumber) {
            return;
        }

        // Find the order
        $order = ChannelOrder::where('shop_id', $this->shop->id)
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            Log::debug("Order not found for shipment", [
                'order_number' => $orderNumber,
                'shipment_id' => $shipment['shipmentId'] ?? null,
            ]);
            return;
        }

        // Update fulfillment data
        $fulfillmentData = $order->fulfillment_data ?? [];
        $fulfillmentData['shipstation'] = array_merge(
            $fulfillmentData['shipstation'] ?? [],
            [
                'shipment_id' => $shipment['shipmentId'] ?? null,
                'tracking_number' => $shipment['trackingNumber'] ?? null,
                'carrier_code' => $shipment['carrierCode'] ?? null,
                'service_code' => $shipment['serviceCode'] ?? null,
                'ship_date' => $shipment['shipDate'] ?? null,
                'shipping_cost' => $shipment['shipmentCost'] ?? null,
                'insurance_cost' => $shipment['insuranceCost'] ?? null,
                'voided' => $shipment['voided'] ?? false,
                'void_date' => $shipment['voidDate'] ?? null,
                'last_synced' => now()->toIso8601String(),
            ]
        );

        // Update order
        $updateData = [
            'fulfillment_data' => $fulfillmentData,
        ];

        // Only update if not voided
        if (empty($shipment['voided'])) {
            $updateData['fulfillment_status'] = 'fulfilled';
            $updateData['tracking_number'] = $shipment['trackingNumber'] ?? $order->tracking_number;
            $updateData['tracking_company'] = $this->mapCarrierName($shipment['carrierCode'] ?? null);
        } else {
            // Label was voided
            $updateData['fulfillment_status'] = 'unfulfilled';
            Log::info("Shipment voided", [
                'order_id' => $order->id,
                'shipment_id' => $shipment['shipmentId'] ?? null,
            ]);
        }

        $order->update($updateData);

        Log::info("Updated order with shipment data", [
            'order_id' => $order->id,
            'shipment_id' => $shipment['shipmentId'] ?? null,
            'tracking_number' => $shipment['trackingNumber'] ?? null,
        ]);
    }

    /**
     * Map carrier code to friendly name
     */
    protected function mapCarrierName(?string $carrierCode): ?string
    {
        return match ($carrierCode) {
            'stamps_com', 'usps' => 'USPS',
            'fedex' => 'FedEx',
            'ups', 'ups_walleted' => 'UPS',
            'dhl_express', 'dhl_global_mail' => 'DHL',
            'canada_post' => 'Canada Post',
            'endicia' => 'Endicia',
            'australia_post' => 'Australia Post',
            'royal_mail' => 'Royal Mail',
            default => $carrierCode ? strtoupper($carrierCode) : null,
        };
    }
}
