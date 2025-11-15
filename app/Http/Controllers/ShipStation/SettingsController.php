<?php

namespace App\Http\Controllers\ShipStation;

use App\Http\Controllers\Controller;
use App\Services\ShipStation\ShipStationClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get ShipStation settings
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;
        $settings = $shop->settings['shipstation'] ?? [];

        return response()->json([
            'connected' => !empty($settings['api_key']) && !empty($settings['api_secret']),
            'api_key' => $settings['api_key'] ?? null,
            'default_carrier' => $settings['default_carrier'] ?? null,
            'default_service' => $settings['default_service'] ?? null,
            'default_package' => $settings['default_package'] ?? 'package',
            'default_confirmation' => $settings['default_confirmation'] ?? 'none',
            'default_warehouse_id' => $settings['default_warehouse_id'] ?? null,
            'auto_create_orders' => $settings['auto_create_orders'] ?? false,
            'auto_create_labels' => $settings['auto_create_labels'] ?? false,
            'auto_fulfill_orders' => $settings['auto_fulfill_orders'] ?? true,
        ]);
    }

    /**
     * Update ShipStation credentials
     */
    public function updateCredentials(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        $shop = $request->user()->shop;
        $settings = $shop->settings;

        $settings['shipstation'] = array_merge($settings['shipstation'] ?? [], [
            'api_key' => $request->input('api_key'),
            'api_secret' => $request->input('api_secret'),
        ]);

        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'ShipStation credentials updated successfully',
        ]);
    }

    /**
     * Update ShipStation settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'default_carrier' => 'nullable|string',
            'default_service' => 'nullable|string',
            'default_package' => 'nullable|string',
            'default_confirmation' => 'nullable|string|in:none,delivery,signature,adult_signature,direct_signature',
            'default_warehouse_id' => 'nullable|integer',
            'auto_create_orders' => 'boolean',
            'auto_create_labels' => 'boolean',
            'auto_fulfill_orders' => 'boolean',
        ]);

        $shop = $request->user()->shop;
        $settings = $shop->settings;

        $shipstationSettings = $settings['shipstation'] ?? [];
        $shipstationSettings = array_merge($shipstationSettings, $request->only([
            'default_carrier',
            'default_service',
            'default_package',
            'default_confirmation',
            'default_warehouse_id',
            'auto_create_orders',
            'auto_create_labels',
            'auto_fulfill_orders',
        ]));

        $settings['shipstation'] = $shipstationSettings;
        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'ShipStation settings updated successfully',
        ]);
    }

    /**
     * Test ShipStation connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $shop = $request->user()->shop;
            $client = new ShipStationClient($shop);

            $result = $client->testConnection();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'stores' => $result['stores'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . ($result['error'] ?? 'Unknown error'),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available carriers
     */
    public function getCarriers(Request $request): JsonResponse
    {
        try {
            $shop = $request->user()->shop;
            $client = new ShipStationClient($shop);

            $carriers = $client->getCarriers();

            return response()->json([
                'success' => true,
                'carriers' => $carriers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch carriers: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get services for a carrier
     */
    public function getServices(Request $request): JsonResponse
    {
        $request->validate([
            'carrier_code' => 'required|string',
        ]);

        try {
            $shop = $request->user()->shop;
            $client = new ShipStationClient($shop);

            $services = $client->getServices($request->input('carrier_code'));

            return response()->json([
                'success' => true,
                'services' => $services,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch services: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get warehouses
     */
    public function getWarehouses(Request $request): JsonResponse
    {
        try {
            $shop = $request->user()->shop;
            $client = new ShipStationClient($shop);

            $warehouses = $client->getWarehouses();

            return response()->json([
                'success' => true,
                'warehouses' => $warehouses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get stores
     */
    public function getStores(Request $request): JsonResponse
    {
        try {
            $shop = $request->user()->shop;
            $client = new ShipStationClient($shop);

            $stores = $client->getStores();

            return response()->json([
                'success' => true,
                'stores' => $stores,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stores: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Disconnect ShipStation
     */
    public function disconnect(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;
        $settings = $shop->settings;

        unset($settings['shipstation']);
        $shop->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'ShipStation disconnected successfully',
        ]);
    }

    /**
     * Get shipping rates for an order
     */
    public function getRates(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:channel_orders,id',
            'carrier_code' => 'nullable|string',
            'service_code' => 'nullable|string',
        ]);

        try {
            $shop = $request->user()->shop;
            $client = new ShipStationClient($shop);

            $order = \App\Models\ChannelOrder::findOrFail($request->input('order_id'));

            // Build rate request
            $rateOptions = $this->buildRateOptions($order, $request);

            $rates = $client->getRates($rateOptions);

            return response()->json([
                'success' => true,
                'rates' => $rates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get rates: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create a shipping label
     */
    public function createLabel(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:channel_orders,id',
            'carrier_code' => 'required|string',
            'service_code' => 'required|string',
            'package_code' => 'nullable|string',
            'confirmation' => 'nullable|string|in:none,delivery,signature,adult_signature,direct_signature',
            'test_label' => 'boolean',
        ]);

        try {
            $shop = $request->user()->shop;
            $order = \App\Models\ChannelOrder::findOrFail($request->input('order_id'));

            \App\Jobs\ShipStation\CreateShipStationLabel::dispatch(
                $shop,
                $order,
                $request->only(['carrier_code', 'service_code', 'package_code', 'confirmation', 'test_label'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Label creation queued',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue label creation: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Build rate options from order
     */
    protected function buildRateOptions(\App\Models\ChannelOrder $order, Request $request): array
    {
        $shippingAddress = $order->shipping_address ?? [];

        return [
            'carrierCode' => $request->input('carrier_code'),
            'serviceCode' => $request->input('service_code'),
            'packageCode' => $request->input('package_code', 'package'),
            'fromPostalCode' => '10001', // Default or from settings
            'toState' => $shippingAddress['province'] ?? $shippingAddress['state'] ?? null,
            'toCountry' => $shippingAddress['country_code'] ?? $shippingAddress['country'] ?? 'US',
            'toPostalCode' => $shippingAddress['zip'] ?? $shippingAddress['postal_code'] ?? null,
            'toCity' => $shippingAddress['city'] ?? null,
            'weight' => [
                'value' => 16, // Default weight in ounces
                'units' => 'ounces',
            ],
            'dimensions' => null,
            'confirmation' => $request->input('confirmation', 'none'),
            'residential' => true,
        ];
    }
}
