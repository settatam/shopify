<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Models\ReturnShipping;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReturnShippingService
{
    /**
     * Generate a return shipping label.
     */
    public function generateReturnLabel(ReturnRequest $returnRequest, User $user): ReturnShipping
    {
        $shop = $returnRequest->shop;

        // Parse addresses
        $returnAddress = json_decode($returnRequest->return_address, true);
        $customerAddress = $this->getCustomerAddress($returnRequest);

        // Calculate package dimensions and weight
        $packageDetails = $this->calculatePackageDetails($returnRequest);

        try {
            // Create label via ShipStation (or other provider)
            $labelResponse = $this->createShipStationLabel(
                $shop,
                $customerAddress,
                $returnAddress,
                $packageDetails
            );

            // Create return shipping record
            $returnShipping = ReturnShipping::create([
                'return_request_id' => $returnRequest->id,
                'shop_id' => $shop->id,
                'provider' => 'shipstation',
                'label_id' => $labelResponse['label_id'],
                'tracking_number' => $labelResponse['tracking_number'],
                'carrier_code' => $labelResponse['carrier_code'],
                'service_code' => $labelResponse['service_code'],
                'label_url' => $labelResponse['label_url'],
                'label_pdf_url' => $labelResponse['label_pdf_url'],
                'label_cost' => $labelResponse['label_cost'],
                'insurance_cost' => $labelResponse['insurance_cost'] ?? 0,
                'total_cost' => $labelResponse['total_cost'],
                'customer_pays' => $returnRequest->customer_pays_return_shipping,
                'weight_oz' => $packageDetails['weight_oz'],
                'length_in' => $packageDetails['length_in'],
                'width_in' => $packageDetails['width_in'],
                'height_in' => $packageDetails['height_in'],
                'from_address' => $customerAddress,
                'to_address' => $returnAddress,
                'status' => 'label_created',
                'label_generated_at' => now(),
                'generated_by_id' => $user->id,
                'provider_response' => $labelResponse,
            ]);

            // Update return request
            $returnRequest->update([
                'status' => 'label_generated',
                'return_tracking_number' => $labelResponse['tracking_number'],
                'return_label_url' => $labelResponse['label_url'],
                'return_carrier' => $labelResponse['carrier_code'],
                'return_shipping_cost' => $labelResponse['total_cost'],
            ]);

            Log::info('Return shipping label generated', [
                'rma_number' => $returnRequest->rma_number,
                'tracking_number' => $labelResponse['tracking_number'],
            ]);

            return $returnShipping;
        } catch (\Exception $e) {
            Log::error('Failed to generate return label', [
                'rma_number' => $returnRequest->rma_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create shipping label via ShipStation.
     */
    protected function createShipStationLabel(
        $shop,
        array $fromAddress,
        array $toAddress,
        array $packageDetails
    ): array {
        // Get ShipStation credentials from shop settings
        $apiKey = $shop->getSetting('shipstation_api_key');
        $apiSecret = $shop->getSetting('shipstation_api_secret');

        if (!$apiKey || !$apiSecret) {
            throw new \Exception('ShipStation credentials not configured');
        }

        // Create label request
        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->post('https://ssapi.shipstation.com/shipments/createlabel', [
                'carrierCode' => 'usps', // Default to USPS for returns
                'serviceCode' => 'usps_priority_mail',
                'packageCode' => 'package',
                'confirmation' => 'delivery',
                'shipDate' => now()->format('Y-m-d'),
                'weight' => [
                    'value' => $packageDetails['weight_oz'],
                    'units' => 'ounces',
                ],
                'dimensions' => [
                    'length' => $packageDetails['length_in'],
                    'width' => $packageDetails['width_in'],
                    'height' => $packageDetails['height_in'],
                    'units' => 'inches',
                ],
                'shipFrom' => [
                    'name' => $fromAddress['name'],
                    'street1' => $fromAddress['address1'],
                    'street2' => $fromAddress['address2'] ?? '',
                    'city' => $fromAddress['city'],
                    'state' => $fromAddress['state'],
                    'postalCode' => $fromAddress['zip'],
                    'country' => $fromAddress['country'] ?? 'US',
                    'phone' => $fromAddress['phone'] ?? '',
                ],
                'shipTo' => [
                    'name' => $toAddress['name'],
                    'street1' => $toAddress['address1'],
                    'street2' => $toAddress['address2'] ?? '',
                    'city' => $toAddress['city'],
                    'state' => $toAddress['state'],
                    'postalCode' => $toAddress['zip'],
                    'country' => $toAddress['country'] ?? 'US',
                ],
                'testLabel' => config('app.env') !== 'production',
            ]);

        if (!$response->successful()) {
            throw new \Exception('ShipStation API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'label_id' => $data['shipmentId'],
            'tracking_number' => $data['trackingNumber'],
            'carrier_code' => $data['carrierCode'],
            'service_code' => $data['serviceCode'],
            'label_url' => $data['labelData'],
            'label_pdf_url' => $data['labelData'], // ShipStation returns base64 PDF
            'label_cost' => $data['shipmentCost'],
            'insurance_cost' => $data['insuranceCost'] ?? 0,
            'total_cost' => $data['shipmentCost'] + ($data['insuranceCost'] ?? 0),
        ];
    }

    /**
     * Update tracking status.
     */
    public function updateTracking(ReturnShipping $returnShipping): void
    {
        try {
            $trackingData = $this->getTrackingData($returnShipping);

            if ($trackingData) {
                $returnShipping->updateTracking(
                    $trackingData['status'],
                    $trackingData['events']
                );

                // Update return request status if delivered
                if ($trackingData['status'] === 'delivered') {
                    $returnShipping->returnRequest->markReceived();
                }

                Log::info('Tracking updated', [
                    'tracking_number' => $returnShipping->tracking_number,
                    'status' => $trackingData['status'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update tracking', [
                'tracking_number' => $returnShipping->tracking_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get tracking data from carrier.
     */
    protected function getTrackingData(ReturnShipping $returnShipping): ?array
    {
        // This would integrate with carrier APIs or ShipStation
        // For now, return null (implement based on your needs)

        Log::info('Fetching tracking data', [
            'tracking_number' => $returnShipping->tracking_number,
            'carrier' => $returnShipping->carrier_code,
        ]);

        return null;
    }

    /**
     * Void a shipping label.
     */
    public function voidLabel(ReturnShipping $returnShipping, User $user, ?string $reason = null): void
    {
        if (!$returnShipping->canBeVoided()) {
            throw new \Exception('Label cannot be voided');
        }

        try {
            // Void via ShipStation
            $this->voidShipStationLabel($returnShipping);

            $returnShipping->void($user, $reason);

            Log::info('Return label voided', [
                'tracking_number' => $returnShipping->tracking_number,
                'voided_by' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to void label', [
                'tracking_number' => $returnShipping->tracking_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Void label via ShipStation.
     */
    protected function voidShipStationLabel(ReturnShipping $returnShipping): void
    {
        $shop = $returnShipping->shop;
        $apiKey = $shop->getSetting('shipstation_api_key');
        $apiSecret = $shop->getSetting('shipstation_api_secret');

        if (!$apiKey || !$apiSecret) {
            return;
        }

        Http::withBasicAuth($apiKey, $apiSecret)
            ->delete("https://ssapi.shipstation.com/shipments/{$returnShipping->label_id}");
    }

    /**
     * Get customer address from return request.
     */
    protected function getCustomerAddress(ReturnRequest $returnRequest): array
    {
        // This would typically come from the original order
        // For now, return a placeholder
        return [
            'name' => $returnRequest->customer_name,
            'address1' => '123 Customer St',
            'address2' => '',
            'city' => 'City',
            'state' => 'ST',
            'zip' => '12345',
            'country' => 'US',
            'phone' => $returnRequest->customer_phone ?? '',
        ];
    }

    /**
     * Calculate package details for return.
     */
    protected function calculatePackageDetails(ReturnRequest $returnRequest): array
    {
        // This would calculate based on products being returned
        // For now, return defaults
        return [
            'weight_oz' => 16, // 1 lb default
            'length_in' => 12,
            'width_in' => 9,
            'height_in' => 6,
        ];
    }
}
