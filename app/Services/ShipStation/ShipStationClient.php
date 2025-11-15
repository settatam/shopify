<?php

namespace App\Services\ShipStation;

use App\Models\Shop;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ShipStationClient
{
    protected Client $http;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected Shop $shop;

    /**
     * Create a new ShipStation client instance
     *
     * @param Shop $shop
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;

        // Get ShipStation credentials from shop settings
        $shipstationSettings = $shop->settings['shipstation'] ?? [];
        $this->apiKey = $shipstationSettings['api_key'] ?? '';
        $this->apiSecret = $shipstationSettings['api_secret'] ?? '';

        $this->baseUrl = 'https://ssapi.shipstation.com';

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'auth' => [$this->apiKey, $this->apiSecret],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Make an API request
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $endpoint, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            return $data ?? [];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = $e->getResponse()->getBody()->getContents();

            Log::error("ShipStation API error ({$statusCode}): {$errorBody}", [
                'endpoint' => $endpoint,
                'method' => $method,
            ]);

            throw new \Exception("ShipStation API error: {$errorBody}", $statusCode);
        } catch (\Exception $e) {
            Log::error('ShipStation request failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test the connection
     */
    public function testConnection(): array
    {
        try {
            $stores = $this->getStores();

            return [
                'success' => true,
                'stores' => $stores,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get stores
     */
    public function getStores(): array
    {
        return $this->request('GET', '/stores');
    }

    /**
     * Get store by ID
     */
    public function getStore(int $storeId): array
    {
        return $this->request('GET', "/stores/{$storeId}");
    }

    /**
     * Get carriers
     */
    public function getCarriers(): array
    {
        return $this->request('GET', '/carriers');
    }

    /**
     * Get services for a carrier
     */
    public function getServices(string $carrierCode): array
    {
        return $this->request('GET', '/carriers/listservices', [
            'query' => ['carrierCode' => $carrierCode],
        ]);
    }

    /**
     * Create or update an order in ShipStation
     */
    public function createOrder(array $orderData): array
    {
        return $this->request('POST', '/orders/createorder', [
            'json' => $orderData,
        ]);
    }

    /**
     * Get an order by order number
     */
    public function getOrder(string $orderNumber): ?array
    {
        $response = $this->request('GET', '/orders', [
            'query' => ['orderNumber' => $orderNumber],
        ]);

        $orders = $response['orders'] ?? [];
        return !empty($orders) ? $orders[0] : null;
    }

    /**
     * Get orders with filters
     */
    public function getOrders(array $params = []): array
    {
        return $this->request('GET', '/orders', [
            'query' => $params,
        ]);
    }

    /**
     * Create a label for an order
     */
    public function createLabel(array $labelData): array
    {
        return $this->request('POST', '/orders/createlabelfororder', [
            'json' => $labelData,
        ]);
    }

    /**
     * Create a label from rate
     */
    public function createLabelFromRate(array $shipmentData): array
    {
        return $this->request('POST', '/shipments/createlabel', [
            'json' => $shipmentData,
        ]);
    }

    /**
     * Get shipping rates
     */
    public function getRates(array $rateOptions): array
    {
        return $this->request('POST', '/shipments/getrates', [
            'json' => $rateOptions,
        ]);
    }

    /**
     * Void a label
     */
    public function voidLabel(int $shipmentId): array
    {
        return $this->request('POST', '/shipments/voidlabel', [
            'json' => ['shipmentId' => $shipmentId],
        ]);
    }

    /**
     * Get shipments
     */
    public function getShipments(array $params = []): array
    {
        return $this->request('GET', '/shipments', [
            'query' => $params,
        ]);
    }

    /**
     * Mark an order as shipped
     */
    public function markAsShipped(array $shipmentData): array
    {
        return $this->request('POST', '/orders/markasshipped', [
            'json' => $shipmentData,
        ]);
    }

    /**
     * Get warehouses
     */
    public function getWarehouses(): array
    {
        return $this->request('GET', '/warehouses');
    }

    /**
     * Get warehouse by ID
     */
    public function getWarehouse(int $warehouseId): array
    {
        return $this->request('GET', "/warehouses/{$warehouseId}");
    }

    /**
     * Create a warehouse
     */
    public function createWarehouse(array $warehouseData): array
    {
        return $this->request('POST', '/warehouses/createwarehouse', [
            'json' => $warehouseData,
        ]);
    }

    /**
     * Get products
     */
    public function getProducts(array $params = []): array
    {
        return $this->request('GET', '/products', [
            'query' => $params,
        ]);
    }

    /**
     * Update product
     */
    public function updateProduct(int $productId, array $productData): array
    {
        $productData['productId'] = $productId;

        return $this->request('PUT', '/products', [
            'json' => $productData,
        ]);
    }

    /**
     * List tags
     */
    public function getTags(): array
    {
        return $this->request('GET', '/accounts/listtags');
    }

    /**
     * Create tag
     */
    public function createTag(string $tagName): array
    {
        return $this->request('POST', '/accounts/addtag', [
            'json' => ['name' => $tagName],
        ]);
    }

    /**
     * Get account details
     */
    public function getAccount(): array
    {
        return $this->request('GET', '/accounts');
    }

    /**
     * Build order data for ShipStation from ChannelOrder
     */
    public function buildOrderData(\App\Models\ChannelOrder $order): array
    {
        $shop = $order->shop;
        $channel = $order->channel;

        // Parse shipping address
        $shippingAddress = $this->parseAddress($order->shipping_address ?? []);
        $billingAddress = $this->parseAddress($order->billing_address ?? $shippingAddress);

        // Build line items
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'lineItemKey' => (string) $item->id,
                'sku' => $item->sku ?? '',
                'name' => $item->title,
                'quantity' => $item->quantity,
                'unitPrice' => (float) $item->price,
                'taxAmount' => null,
                'shippingAmount' => null,
                'warehouseLocation' => null,
                'options' => [],
                'productId' => $item->product_id,
                'fulfillmentSku' => $item->sku,
                'weight' => null,
                'weightUnits' => 'ounces',
            ];
        }

        return [
            'orderNumber' => $order->order_number,
            'orderKey' => $order->channel_order_id,
            'orderDate' => $order->placed_at?->toIso8601String(),
            'paymentDate' => $order->placed_at?->toIso8601String(),
            'shipByDate' => null,
            'orderStatus' => $this->mapOrderStatus($order->fulfillment_status),
            'customerUsername' => $order->customer_email ?? '',
            'customerEmail' => $order->customer_email ?? '',
            'billTo' => $billingAddress,
            'shipTo' => $shippingAddress,
            'items' => $items,
            'amountPaid' => (float) $order->total_price,
            'taxAmount' => 0,
            'shippingAmount' => 0,
            'customerNotes' => $order->notes ?? '',
            'internalNotes' => "Channel: {$channel->name}",
            'gift' => false,
            'giftMessage' => null,
            'paymentMethod' => $order->financial_status ?? 'Unknown',
            'requestedShippingService' => null,
            'carrierCode' => null,
            'serviceCode' => null,
            'packageCode' => 'package',
            'confirmation' => 'none',
            'shipDate' => null,
            'weight' => [
                'value' => 0,
                'units' => 'ounces',
            ],
            'dimensions' => null,
            'insuranceOptions' => null,
            'internationalOptions' => null,
            'advancedOptions' => [
                'warehouseId' => null,
                'nonMachinable' => false,
                'saturdayDelivery' => false,
                'containsAlcohol' => false,
                'storeId' => null,
                'customField1' => $order->id,
                'customField2' => $channel->type,
                'customField3' => null,
                'source' => 'Multichannel Platform',
            ],
            'tagIds' => [],
        ];
    }

    /**
     * Parse address array into ShipStation format
     */
    protected function parseAddress(array $address): array
    {
        return [
            'name' => $address['name'] ?? '',
            'company' => $address['company'] ?? null,
            'street1' => $address['address1'] ?? '',
            'street2' => $address['address2'] ?? null,
            'street3' => null,
            'city' => $address['city'] ?? '',
            'state' => $address['province'] ?? $address['state'] ?? '',
            'postalCode' => $address['zip'] ?? $address['postal_code'] ?? '',
            'country' => $address['country_code'] ?? $address['country'] ?? 'US',
            'phone' => $address['phone'] ?? '',
            'residential' => null,
        ];
    }

    /**
     * Map internal fulfillment status to ShipStation order status
     */
    protected function mapOrderStatus(?string $fulfillmentStatus): string
    {
        return match ($fulfillmentStatus) {
            'fulfilled' => 'shipped',
            'partial' => 'awaiting_shipment',
            'unfulfilled' => 'awaiting_shipment',
            default => 'awaiting_payment',
        };
    }

    /**
     * Parse ShipStation webhook data
     */
    public function parseWebhookData(array $data): array
    {
        $resource_type = $data['resource_type'] ?? null;
        $resource_url = $data['resource_url'] ?? null;

        if (!$resource_url) {
            throw new \Exception('No resource_url in webhook');
        }

        // Extract resource ID from URL
        // e.g., "https://ssapi.shipstation.com/orders/123456"
        $parts = explode('/', trim($resource_url, '/'));
        $resourceId = end($parts);

        return [
            'resource_type' => $resource_type,
            'resource_id' => $resourceId,
            'resource_url' => $resource_url,
        ];
    }
}
