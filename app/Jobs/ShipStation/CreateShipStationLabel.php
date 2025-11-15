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
use Illuminate\Support\Facades\Storage;

class CreateShipStationLabel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected array $options;

    /**
     * Create a new job instance
     */
    public function __construct(
        public Shop $shop,
        public ChannelOrder $order,
        array $options = []
    ) {
        $this->options = $options;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        try {
            $client = new ShipStationClient($this->shop);
            $settings = $this->shop->settings['shipstation'] ?? [];

            // First, ensure the order exists in ShipStation
            $shipstationOrderId = $this->order->fulfillment_data['shipstation']['order_id'] ?? null;

            if (!$shipstationOrderId) {
                // Create the order first
                CreateShipStationOrder::dispatchSync($this->shop, $this->order);
                $this->order->refresh();
                $shipstationOrderId = $this->order->fulfillment_data['shipstation']['order_id'] ?? null;
            }

            if (!$shipstationOrderId) {
                throw new \Exception('Failed to get ShipStation order ID');
            }

            // Build label data
            $labelData = [
                'orderId' => $shipstationOrderId,
                'carrierCode' => $this->options['carrier_code'] ?? $settings['default_carrier'] ?? null,
                'serviceCode' => $this->options['service_code'] ?? $settings['default_service'] ?? null,
                'packageCode' => $this->options['package_code'] ?? $settings['default_package'] ?? 'package',
                'confirmation' => $this->options['confirmation'] ?? $settings['default_confirmation'] ?? 'none',
                'shipDate' => now()->format('Y-m-d'),
                'weight' => [
                    'value' => 16, // Default 1 lb in ounces
                    'units' => 'ounces',
                ],
                'testLabel' => $this->options['test_label'] ?? false,
            ];

            // Add warehouse if configured
            if (!empty($settings['default_warehouse_id'])) {
                $labelData['warehouseId'] = $settings['default_warehouse_id'];
            }

            Log::info('Creating ShipStation label', [
                'order_id' => $this->order->id,
                'shipstation_order_id' => $shipstationOrderId,
                'label_data' => $labelData,
            ]);

            // Create the label
            $response = $client->createLabel($labelData);

            // Save label data
            $this->saveLabelData($response);

            // Update order fulfillment status
            if ($settings['auto_fulfill_orders'] ?? true) {
                $this->order->update([
                    'fulfillment_status' => 'fulfilled',
                ]);
            }

            Log::info('ShipStation label created successfully', [
                'order_id' => $this->order->id,
                'shipment_id' => $response['shipmentId'] ?? null,
                'tracking_number' => $response['trackingNumber'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create ShipStation label: ' . $e->getMessage(), [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Save label data to order
     */
    protected function saveLabelData(array $response): void
    {
        $fulfillmentData = $this->order->fulfillment_data ?? [];

        // Store label information
        $fulfillmentData['shipstation']['shipment_id'] = $response['shipmentId'] ?? null;
        $fulfillmentData['shipstation']['tracking_number'] = $response['trackingNumber'] ?? null;
        $fulfillmentData['shipstation']['carrier_code'] = $response['carrierCode'] ?? null;
        $fulfillmentData['shipstation']['service_code'] = $response['serviceCode'] ?? null;
        $fulfillmentData['shipstation']['label_created_at'] = now()->toIso8601String();
        $fulfillmentData['shipstation']['shipping_cost'] = $response['shipmentCost'] ?? null;
        $fulfillmentData['shipstation']['insurance_cost'] = $response['insuranceCost'] ?? null;

        // Save label PDF to storage if provided
        if (!empty($response['labelData'])) {
            $labelPath = "labels/shipstation/{$this->order->id}_{$response['shipmentId']}.pdf";

            // Decode base64 label data and save
            $labelPdf = base64_decode($response['labelData']);
            Storage::disk('local')->put($labelPath, $labelPdf);

            $fulfillmentData['shipstation']['label_path'] = $labelPath;
        }

        // Save form data (customs forms, etc.) if provided
        if (!empty($response['formData'])) {
            $formPath = "labels/shipstation/{$this->order->id}_{$response['shipmentId']}_form.pdf";

            $formPdf = base64_decode($response['formData']);
            Storage::disk('local')->put($formPath, $formPdf);

            $fulfillmentData['shipstation']['form_path'] = $formPath;
        }

        // Update order
        $this->order->update([
            'fulfillment_data' => $fulfillmentData,
            'tracking_number' => $response['trackingNumber'] ?? $this->order->tracking_number,
            'tracking_company' => $this->mapCarrierName($response['carrierCode'] ?? null),
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
