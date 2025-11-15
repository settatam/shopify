<?php

namespace App\Http\Controllers\ShipStation;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ChannelOrder;
use App\Services\ShipStation\ShipStationClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle ShipStation webhook events
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('ShipStation webhook received', $payload);

            $resourceType = $payload['resource_type'] ?? null;
            $resourceUrl = $payload['resource_url'] ?? null;

            if (!$resourceType || !$resourceUrl) {
                Log::warning('ShipStation webhook missing required fields');
                return response()->json(['success' => false], 400);
            }

            // Route to appropriate handler based on resource type
            match ($resourceType) {
                'SHIP_NOTIFY' => $this->handleShipmentNotification($payload),
                'ITEM_ORDER_NOTIFY' => $this->handleOrderNotification($payload),
                'ITEM_SHIP_NOTIFY' => $this->handleItemShipped($payload),
                default => Log::info("Unhandled ShipStation webhook type: {$resourceType}"),
            };

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('ShipStation webhook processing error: ' . $e->getMessage(), [
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle shipment notification (tracking update)
     */
    protected function handleShipmentNotification(array $payload): void
    {
        $resourceUrl = $payload['resource_url'] ?? null;

        if (!$resourceUrl) {
            return;
        }

        // Extract shipment ID from URL
        $parts = explode('/', trim($resourceUrl, '/'));
        $shipmentId = end($parts);

        Log::info("Processing shipment notification for shipment: {$shipmentId}");

        // We need to fetch the shipment details from ShipStation
        // Since we don't know which shop this belongs to, we'll need to try all shops
        // In production, you might want to include shop ID in webhook URL
        $shops = Shop::whereNotNull('settings->shipstation->api_key')->get();

        foreach ($shops as $shop) {
            try {
                $client = new ShipStationClient($shop);
                $shipments = $client->getShipments(['shipmentId' => $shipmentId]);

                if (!empty($shipments['shipments'])) {
                    $shipment = $shipments['shipments'][0];
                    $this->processShipment($shop, $shipment);
                    break;
                }
            } catch (\Exception $e) {
                Log::debug("Failed to fetch shipment from shop {$shop->id}: " . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Handle order notification
     */
    protected function handleOrderNotification(array $payload): void
    {
        Log::info('Processing order notification', $payload);
        // Handle order created/updated in ShipStation
    }

    /**
     * Handle item shipped notification
     */
    protected function handleItemShipped(array $payload): void
    {
        Log::info('Processing item shipped notification', $payload);
        // Handle when items are shipped
    }

    /**
     * Process shipment and update local order
     */
    protected function processShipment(Shop $shop, array $shipment): void
    {
        $orderNumber = $shipment['orderNumber'] ?? null;
        $trackingNumber = $shipment['trackingNumber'] ?? null;
        $carrierCode = $shipment['carrierCode'] ?? null;
        $shipDate = $shipment['shipDate'] ?? null;

        if (!$orderNumber) {
            Log::warning('Shipment missing order number', $shipment);
            return;
        }

        // Find the order
        $order = ChannelOrder::where('shop_id', $shop->id)
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            Log::warning("Order not found for order number: {$orderNumber}");
            return;
        }

        // Update order with tracking information
        $fulfillmentData = $order->fulfillment_data ?? [];
        $fulfillmentData['shipstation'] = [
            'shipment_id' => $shipment['shipmentId'] ?? null,
            'tracking_number' => $trackingNumber,
            'carrier_code' => $carrierCode,
            'service_code' => $shipment['serviceCode'] ?? null,
            'ship_date' => $shipDate,
            'label_data' => $shipment['labelData'] ?? null,
            'form_data' => $shipment['formData'] ?? null,
        ];

        $order->update([
            'fulfillment_status' => 'fulfilled',
            'fulfillment_data' => $fulfillmentData,
            'tracking_number' => $trackingNumber,
            'tracking_company' => $this->mapCarrierName($carrierCode),
        ]);

        Log::info("Updated order {$order->id} with tracking: {$trackingNumber}");

        // Optionally: Update the channel order with tracking info
        // For example, if this was a Shopify order, update Shopify
        if ($order->channel) {
            $this->notifyChannel($order);
        }
    }

    /**
     * Map ShipStation carrier code to friendly name
     */
    protected function mapCarrierName(?string $carrierCode): ?string
    {
        return match ($carrierCode) {
            'stamps_com', 'usps' => 'USPS',
            'fedex' => 'FedEx',
            'ups' => 'UPS',
            'ups_walleted' => 'UPS',
            'dhl_express' => 'DHL',
            'dhl_global_mail' => 'DHL',
            'canada_post' => 'Canada Post',
            'endicia' => 'Endicia',
            'australia_post' => 'Australia Post',
            'royal_mail' => 'Royal Mail',
            default => $carrierCode ? strtoupper($carrierCode) : null,
        };
    }

    /**
     * Notify the original channel about fulfillment
     */
    protected function notifyChannel(ChannelOrder $order): void
    {
        // This could dispatch a job to update the original channel
        // For example: UpdateShopifyFulfillment, UpdateEtsyTracking, etc.
        Log::info("Would notify channel {$order->channel->type} about fulfillment for order {$order->id}");

        // Example:
        // if ($order->channel->type === 'shopify') {
        //     UpdateShopifyFulfillment::dispatch($order);
        // }
    }
}
