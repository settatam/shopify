<?php

namespace App\Services\Square;

use App\Models\Channel;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SquareClient
{
    protected Client $http;
    protected string $baseUrl;
    protected Channel $channel;
    protected ?string $accessToken = null;
    protected ?string $locationId = null;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;

        // Use sandbox or production
        $sandbox = config('services.square.sandbox', false);
        $this->baseUrl = $sandbox
            ? 'https://connect.squareupsandbox.com/v2'
            : 'https://connect.squareup.com/v2';

        $this->http = HttpFactory::make([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->locationId = $channel->auth_json['location_id'] ?? null;
    }

    /**
     * Get access token from channel
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $authJson = $this->channel->auth_json ?? [];
        $token = $authJson['access_token'] ?? null;

        if (!$token) {
            throw new \Exception('No Square access token found. Please reconnect your Square account.');
        }

        // Check if token is expired and refresh if needed
        $expiresAt = $authJson['expires_at'] ?? null;
        if ($expiresAt && now()->timestamp >= $expiresAt) {
            $token = $this->refreshAccessToken();
        }

        $this->accessToken = $token;
        return $token;
    }

    /**
     * Refresh access token
     */
    protected function refreshAccessToken(): string
    {
        $authJson = $this->channel->auth_json ?? [];
        $refreshToken = $authJson['refresh_token'] ?? null;

        if (!$refreshToken) {
            throw new \Exception('No refresh token available. Please reconnect your Square account.');
        }

        try {
            $client = new Client();
            $response = $client->post('https://connect.squareup.com/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'client_id' => config('services.square.application_id'),
                    'client_secret' => config('services.square.application_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            // Update channel with new tokens
            $this->channel->update([
                'auth_json' => array_merge($authJson, [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $refreshToken,
                    'expires_at' => now()->addSeconds($data['expires_in'])->timestamp,
                ]),
            ]);

            return $data['access_token'];
        } catch (RequestException $e) {
            Log::error('Square token refresh failed: ' . $e->getMessage());
            throw new \Exception('Failed to refresh Square access token');
        }
    }

    /**
     * Make authenticated API request
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $token = $this->getAccessToken();

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $token,
            'Square-Version' => '2024-10-17', // API version
        ]);

        try {
            $response = $this->http->request($method, $uri, $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error("Square API error [{$method} {$uri}]: " . $e->getMessage() . " - " . $body);
            throw $e;
        }
    }

    // ==================== LOCATION METHODS ====================

    /**
     * Get all locations
     */
    public function getLocations(): array
    {
        return $this->request('GET', 'locations');
    }

    /**
     * Get specific location
     */
    public function getLocation(string $locationId): array
    {
        return $this->request('GET', "locations/{$locationId}");
    }

    // ==================== CATALOG METHODS ====================

    /**
     * List catalog objects
     */
    public function listCatalog(string $types = 'ITEM', ?string $cursor = null): array
    {
        $params = ['types' => $types];
        if ($cursor) {
            $params['cursor'] = $cursor;
        }

        return $this->request('GET', 'catalog/list', [
            'query' => $params,
        ]);
    }

    /**
     * Create catalog object
     */
    public function createCatalogObject(array $catalogObject): array
    {
        return $this->request('POST', 'catalog/object', [
            'json' => [
                'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
                'object' => $catalogObject,
            ],
        ]);
    }

    /**
     * Update catalog object
     */
    public function updateCatalogObject(string $objectId, array $catalogObject): array
    {
        return $this->request('POST', 'catalog/object', [
            'json' => [
                'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
                'object' => array_merge($catalogObject, ['id' => $objectId]),
            ],
        ]);
    }

    /**
     * Delete catalog object
     */
    public function deleteCatalogObject(string $objectId): array
    {
        return $this->request('DELETE', "catalog/object/{$objectId}");
    }

    /**
     * Search catalog objects
     */
    public function searchCatalog(array $query): array
    {
        return $this->request('POST', 'catalog/search', [
            'json' => $query,
        ]);
    }

    // ==================== INVENTORY METHODS ====================

    /**
     * Retrieve inventory counts
     */
    public function getInventoryCounts(array $catalogObjectIds, ?array $locationIds = null): array
    {
        $body = ['catalog_object_ids' => $catalogObjectIds];

        if ($locationIds) {
            $body['location_ids'] = $locationIds;
        } elseif ($this->locationId) {
            $body['location_ids'] = [$this->locationId];
        }

        return $this->request('POST', 'inventory/counts/batch-retrieve', [
            'json' => $body,
        ]);
    }

    /**
     * Update inventory count
     */
    public function updateInventory(string $catalogObjectId, string $locationId, int $quantity, string $state = 'IN_STOCK'): array
    {
        return $this->request('POST', 'inventory/changes/batch-create', [
            'json' => [
                'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
                'changes' => [
                    [
                        'type' => 'PHYSICAL_COUNT',
                        'physical_count' => [
                            'catalog_object_id' => $catalogObjectId,
                            'state' => $state,
                            'location_id' => $locationId,
                            'quantity' => (string) $quantity,
                            'occurred_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);
    }

    // ==================== ORDER METHODS ====================

    /**
     * Search orders
     */
    public function searchOrders(array $query): array
    {
        return $this->request('POST', 'orders/search', [
            'json' => $query,
        ]);
    }

    /**
     * Get order by ID
     */
    public function getOrder(string $orderId): array
    {
        return $this->request('GET', "orders/{$orderId}");
    }

    /**
     * Create order
     */
    public function createOrder(array $orderData): array
    {
        return $this->request('POST', 'orders', [
            'json' => [
                'idempotency_key' => \Illuminate\Support\Str::uuid()->toString(),
                'order' => $orderData,
            ],
        ]);
    }

    // ==================== PAYMENT METHODS ====================

    /**
     * List payments
     */
    public function listPayments(?string $beginTime = null, ?string $endTime = null, ?string $locationId = null): array
    {
        $params = [];
        if ($beginTime) $params['begin_time'] = $beginTime;
        if ($endTime) $params['end_time'] = $endTime;
        if ($locationId) $params['location_id'] = $locationId;
        elseif ($this->locationId) $params['location_id'] = $this->locationId;

        return $this->request('GET', 'payments', [
            'query' => $params,
        ]);
    }

    /**
     * Get payment by ID
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', "payments/{$paymentId}");
    }

    // ==================== HELPER METHODS ====================

    /**
     * Build catalog item from product
     */
    public function buildCatalogItem(array $productData): array
    {
        $variations = [];

        foreach ($productData['variants'] as $variant) {
            $variations[] = [
                'type' => 'ITEM_VARIATION',
                'id' => '#' . $variant['sku'],
                'item_variation_data' => [
                    'name' => $variant['title'],
                    'sku' => $variant['sku'],
                    'pricing_type' => 'FIXED_PRICING',
                    'price_money' => [
                        'amount' => (int) ($variant['price'] * 100), // Convert to cents
                        'currency' => 'USD',
                    ],
                    'track_inventory' => true,
                ],
            ];
        }

        return [
            'type' => 'ITEM',
            'id' => '#' . ($productData['sku'] ?? $productData['id']),
            'item_data' => [
                'name' => $productData['title'],
                'description' => $productData['description'] ?? '',
                'variations' => $variations,
            ],
        ];
    }

    /**
     * Get merchant/business info
     */
    public function getMerchant(): array
    {
        return $this->request('GET', 'merchants');
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        try {
            $locations = $this->getLocations();

            return [
                'success' => true,
                'locations' => $locations['locations'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get configured location
     */
    public function getConfiguredLocation(): ?string
    {
        return $this->locationId;
    }
}
