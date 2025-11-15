<?php

namespace App\Services\ZohoInventory;

use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ZohoInventoryClient
{
    protected Channel $channel;
    protected string $organizationId;
    protected string $apiUrl;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
        $authData = $channel->auth_json ?? [];
        $this->organizationId = $authData['organization_id'] ?? '';

        // Zoho has different data centers
        $datacenter = $authData['datacenter'] ?? 'com';
        $this->apiUrl = "https://inventory.zoho.{$datacenter}/api/v1";
    }

    /**
     * Get valid access token (refresh if needed)
     */
    protected function getAccessToken(): string
    {
        $authData = $this->channel->auth_json;

        // Check if token needs refresh (expires in less than 5 minutes)
        $expiresAt = isset($authData['expires_at'])
            ? Carbon::parse($authData['expires_at'])
            : Carbon::now();

        if ($expiresAt->subMinutes(5)->isPast()) {
            $this->refreshAccessToken();
            $authData = $this->channel->fresh()->auth_json;
        }

        return $authData['access_token'] ?? '';
    }

    /**
     * Refresh the access token
     */
    protected function refreshAccessToken(): void
    {
        $authData = $this->channel->auth_json;
        $refreshToken = $authData['refresh_token'] ?? '';

        if (!$refreshToken) {
            throw new \Exception('No refresh token available');
        }

        $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
            'refresh_token' => $refreshToken,
            'client_id' => config('services.zoho_inventory.client_id'),
            'client_secret' => config('services.zoho_inventory.client_secret'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::error('Zoho token refresh failed', [
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to refresh Zoho access token');
        }

        $data = $response->json();

        // Update stored tokens
        $authData['access_token'] = $data['access_token'];
        $authData['expires_at'] = Carbon::now()->addSeconds($data['expires_in'])->toDateTimeString();

        $this->channel->update(['auth_json' => $authData]);
    }

    /**
     * Make an authenticated request to Zoho Inventory API
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();

        $url = $this->apiUrl . $endpoint;

        // Add organization_id to query params
        $queryParams = array_merge(
            $data['query'] ?? [],
            ['organization_id' => $this->organizationId]
        );

        $options = [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($method === 'GET') {
            $options['query'] = $queryParams;
        } else {
            $options['query'] = ['organization_id' => $this->organizationId];
            if (isset($data['json'])) {
                $options['json'] = $data['json'];
            }
        }

        $response = Http::withOptions($options)->$method($url);

        if ($response->failed()) {
            Log::error('Zoho Inventory API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
            throw new \Exception('Zoho Inventory API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get all items (products)
     */
    public function getItems(int $page = 1, int $perPage = 200): array
    {
        return $this->request('GET', '/items', [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Get a single item by ID
     */
    public function getItem(string $itemId): array
    {
        return $this->request('GET', "/items/{$itemId}");
    }

    /**
     * Create an item
     */
    public function createItem(array $itemData): array
    {
        return $this->request('POST', '/items', [
            'json' => $itemData,
        ]);
    }

    /**
     * Update an item
     */
    public function updateItem(string $itemId, array $itemData): array
    {
        return $this->request('PUT', "/items/{$itemId}", [
            'json' => $itemData,
        ]);
    }

    /**
     * Build item data for Zoho from product
     */
    public function buildItemData(array $productData): array
    {
        $item = [
            'name' => $productData['title'],
            'sku' => $productData['sku'],
            'description' => $productData['description'] ?? '',
            'rate' => (float) $productData['price'],
            'product_type' => 'goods',
            'item_type' => 'sales',
        ];

        // Add category if available
        if (!empty($productData['category'])) {
            $item['category_name'] = $productData['category'];
        }

        // Add dimensions and weight if available
        if (!empty($productData['weight'])) {
            $item['weight'] = (float) $productData['weight'];
            $item['weight_unit'] = $productData['weight_unit'] ?? 'lb';
        }

        // Add custom fields for variants if this is a variant product
        if (!empty($productData['is_variant'])) {
            $item['custom_fields'] = [
                ['label' => 'Variant Title', 'value' => $productData['variant_title'] ?? ''],
                ['label' => 'Barcode', 'value' => $productData['barcode'] ?? ''],
            ];
        }

        return $item;
    }

    /**
     * Adjust inventory for an item
     */
    public function adjustInventory(string $itemId, int $quantity, string $reason = 'Stock adjustment'): array
    {
        return $this->request('POST', '/inventory-adjustments', [
            'json' => [
                'reason' => $reason,
                'line_items' => [
                    [
                        'item_id' => $itemId,
                        'quantity_adjusted' => $quantity,
                    ],
                ],
            ],
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
     * Get inventory by warehouse
     */
    public function getItemStock(string $itemId): array
    {
        return $this->request('GET', "/items/{$itemId}/inventory");
    }

    /**
     * Get sales orders
     */
    public function getSalesOrders(int $page = 1, array $filters = []): array
    {
        $query = [
            'page' => $page,
            'per_page' => 200,
        ];

        // Add filters
        if (isset($filters['status'])) {
            $query['status'] = $filters['status'];
        }
        if (isset($filters['date'])) {
            $query['date'] = $filters['date'];
        }

        return $this->request('GET', '/salesorders', ['query' => $query]);
    }

    /**
     * Get a single sales order
     */
    public function getSalesOrder(string $orderId): array
    {
        return $this->request('GET', "/salesorders/{$orderId}");
    }

    /**
     * Create a sales order
     */
    public function createSalesOrder(array $orderData): array
    {
        return $this->request('POST', '/salesorders', [
            'json' => $orderData,
        ]);
    }

    /**
     * Update a sales order
     */
    public function updateSalesOrder(string $orderId, array $orderData): array
    {
        return $this->request('PUT', "/salesorders/{$orderId}", [
            'json' => $orderData,
        ]);
    }

    /**
     * Mark sales order as confirmed
     */
    public function confirmSalesOrder(string $orderId): array
    {
        return $this->request('POST', "/salesorders/{$orderId}/status/confirmed");
    }

    /**
     * Build sales order data from channel order
     */
    public function buildSalesOrderData(array $orderData): array
    {
        $salesOrder = [
            'customer_name' => $orderData['customer_name'] ?? 'Guest Customer',
            'date' => Carbon::parse($orderData['created_at'])->format('Y-m-d'),
            'reference_number' => $orderData['order_number'],
            'line_items' => [],
        ];

        // Add customer email if available
        if (!empty($orderData['customer_email'])) {
            $salesOrder['email'] = $orderData['customer_email'];
        }

        // Add customer phone
        if (!empty($orderData['customer_phone'])) {
            $salesOrder['phone'] = $orderData['customer_phone'];
        }

        // Add shipping address
        if (!empty($orderData['shipping_address'])) {
            $address = $orderData['shipping_address'];
            $salesOrder['shipping_address'] = [
                'address' => $address['address1'] ?? '',
                'city' => $address['city'] ?? '',
                'state' => $address['province'] ?? '',
                'zip' => $address['zip'] ?? '',
                'country' => $address['country'] ?? '',
            ];
        }

        // Add line items
        foreach ($orderData['items'] as $item) {
            $lineItem = [
                'name' => $item['title'],
                'rate' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ];

            // Add SKU if available (Zoho will match to existing items)
            if (!empty($item['sku'])) {
                $lineItem['sku'] = $item['sku'];
            }

            $salesOrder['line_items'][] = $lineItem;
        }

        // Add notes
        if (!empty($orderData['notes'])) {
            $salesOrder['notes'] = $orderData['notes'];
        }

        return $salesOrder;
    }

    /**
     * Get organizations (for initial setup)
     */
    public function getOrganizations(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
        ])->get('https://inventory.zoho.com/api/v1/organizations');

        if ($response->failed()) {
            throw new \Exception('Failed to fetch Zoho organizations');
        }

        return $response->json();
    }

    /**
     * Create a package/shipment
     */
    public function createPackage(string $salesOrderId, array $packageData): array
    {
        return $this->request('POST', "/salesorders/{$salesOrderId}/packages", [
            'json' => $packageData,
        ]);
    }

    /**
     * Update package tracking
     */
    public function updatePackageTracking(string $packageId, string $trackingNumber, string $carrier = ''): array
    {
        $data = [
            'tracking_number' => $trackingNumber,
        ];

        if ($carrier) {
            $data['carrier'] = $carrier;
        }

        return $this->request('PUT', "/packages/{$packageId}", [
            'json' => $data,
        ]);
    }

    /**
     * Get contacts (customers)
     */
    public function getContacts(int $page = 1): array
    {
        return $this->request('GET', '/contacts', [
            'query' => [
                'page' => $page,
                'per_page' => 200,
            ],
        ]);
    }

    /**
     * Create a contact
     */
    public function createContact(array $contactData): array
    {
        return $this->request('POST', '/contacts', [
            'json' => $contactData,
        ]);
    }

    /**
     * Test the connection
     */
    public function testConnection(): bool
    {
        try {
            $this->request('GET', '/items', ['query' => ['per_page' => 1]]);
            return true;
        } catch (\Exception $e) {
            Log::error('Zoho Inventory connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
