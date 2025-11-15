<?php

namespace App\Services\Etsy;

use App\Models\Channel;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EtsyClient
{
    protected Client $http;
    protected string $baseUrl;
    protected Channel $channel;
    protected ?string $accessToken = null;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
        $this->baseUrl = 'https://openapi.etsy.com/v3';

        $this->http = HttpFactory::make([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
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
            throw new \Exception('No Etsy access token found. Please reconnect your Etsy account.');
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
            throw new \Exception('No refresh token available. Please reconnect your Etsy account.');
        }

        try {
            $response = $this->http->post('https://api.etsy.com/v3/public/oauth/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('services.etsy.client_id'),
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
            Log::error('Etsy token refresh failed: ' . $e->getMessage());
            throw new \Exception('Failed to refresh Etsy access token');
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
            'x-api-key' => config('services.etsy.client_id'),
        ]);

        try {
            $response = $this->http->request($method, $uri, $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error("Etsy API error [{$method} {$uri}]: " . $e->getMessage() . " - " . $body);
            throw $e;
        }
    }

    /**
     * Get shop details
     */
    public function getShop(int $shopId): array
    {
        return $this->request('GET', "/application/shops/{$shopId}");
    }

    /**
     * Get user shops
     */
    public function getUserShops(int $userId): array
    {
        return $this->request('GET', "/application/users/{$userId}/shops");
    }

    /**
     * Get shop listings (active)
     */
    public function getShopListings(int $shopId, array $params = []): array
    {
        $defaultParams = [
            'limit' => 100,
            'offset' => 0,
            'state' => 'active',
        ];

        $params = array_merge($defaultParams, $params);

        return $this->request('GET', "/application/shops/{$shopId}/listings/active", [
            'query' => $params,
        ]);
    }

    /**
     * Get listing by ID
     */
    public function getListing(int $listingId): array
    {
        return $this->request('GET', "/application/listings/{$listingId}");
    }

    /**
     * Create listing
     */
    public function createListing(int $shopId, array $listingData): array
    {
        return $this->request('POST', "/application/shops/{$shopId}/listings", [
            'json' => $listingData,
        ]);
    }

    /**
     * Update listing
     */
    public function updateListing(int $listingId, array $listingData): array
    {
        return $this->request('PATCH', "/application/listings/{$listingId}", [
            'json' => $listingData,
        ]);
    }

    /**
     * Delete listing
     */
    public function deleteListing(int $listingId): array
    {
        return $this->request('DELETE', "/application/listings/{$listingId}");
    }

    /**
     * Get listing inventory
     */
    public function getListingInventory(int $listingId): array
    {
        return $this->request('GET', "/application/listings/{$listingId}/inventory");
    }

    /**
     * Update listing inventory
     */
    public function updateListingInventory(int $listingId, array $inventoryData): array
    {
        return $this->request('PUT', "/application/listings/{$listingId}/inventory", [
            'json' => $inventoryData,
        ]);
    }

    /**
     * Update listing quantity
     */
    public function updateListingQuantity(int $listingId, int $quantity): array
    {
        // Get current inventory to update it
        $inventory = $this->getListingInventory($listingId);

        // Update quantity for all products
        foreach ($inventory['products'] as &$product) {
            foreach ($product['offerings'] as &$offering) {
                $offering['quantity'] = $quantity;
            }
        }

        return $this->updateListingInventory($listingId, [
            'products' => $inventory['products'],
        ]);
    }

    /**
     * Update listing price
     */
    public function updateListingPrice(int $listingId, float $price): array
    {
        // Get current inventory to update pricing
        $inventory = $this->getListingInventory($listingId);

        // Update price for all offerings
        foreach ($inventory['products'] as &$product) {
            foreach ($product['offerings'] as &$offering) {
                $offering['price'] = $price;
            }
        }

        return $this->updateListingInventory($listingId, [
            'products' => $inventory['products'],
            'price_on_property' => [],
            'quantity_on_property' => [],
            'sku_on_property' => [],
        ]);
    }

    /**
     * Upload listing image
     */
    public function uploadListingImage(int $shopId, int $listingId, string $imageUrl): array
    {
        // Download image first
        $imageContent = file_get_contents($imageUrl);
        $tempFile = tempnam(sys_get_temp_dir(), 'etsy_img_');
        file_put_contents($tempFile, $imageContent);

        try {
            $response = $this->request('POST', "/application/shops/{$shopId}/listings/{$listingId}/images", [
                'multipart' => [
                    [
                        'name' => 'image',
                        'contents' => fopen($tempFile, 'r'),
                    ],
                ],
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
            ]);

            return $response;
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Get shop receipts (orders)
     */
    public function getShopReceipts(int $shopId, array $params = []): array
    {
        $defaultParams = [
            'limit' => 100,
            'offset' => 0,
            'min_created' => now()->subDays(30)->timestamp,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->request('GET', "/application/shops/{$shopId}/receipts", [
            'query' => $params,
        ]);
    }

    /**
     * Get receipt by ID
     */
    public function getReceipt(int $shopId, int $receiptId): array
    {
        return $this->request('GET', "/application/shops/{$shopId}/receipts/{$receiptId}");
    }

    /**
     * Get receipt transactions (line items)
     */
    public function getReceiptTransactions(int $shopId, int $receiptId): array
    {
        return $this->request('GET', "/application/shops/{$shopId}/receipts/{$receiptId}/transactions");
    }

    /**
     * Create receipt shipment
     */
    public function createReceiptShipment(int $shopId, int $receiptId, array $shipmentData): array
    {
        return $this->request('POST', "/application/shops/{$shopId}/receipts/{$receiptId}/tracking", [
            'json' => $shipmentData,
        ]);
    }

    /**
     * Get seller taxonomy (categories)
     */
    public function getSellerTaxonomy(): array
    {
        return $this->request('GET', '/application/seller-taxonomy/nodes');
    }

    /**
     * Get taxonomy node properties
     */
    public function getTaxonomyNodeProperties(int $taxonomyId): array
    {
        return $this->request('GET', "/application/seller-taxonomy/nodes/{$taxonomyId}/properties");
    }

    /**
     * Get shipping carriers
     */
    public function getShippingCarriers(string $originCountryIso = 'US'): array
    {
        return $this->request('GET', '/application/shipping-carriers', [
            'query' => ['origin_country_iso' => $originCountryIso],
        ]);
    }

    /**
     * Build listing data array
     */
    public function buildListingData(array $productData): array
    {
        return [
            'quantity' => $productData['quantity'] ?? 1,
            'title' => substr($productData['title'] ?? '', 0, 140), // Etsy max 140 chars
            'description' => $productData['description'] ?? '',
            'price' => $productData['price'] ?? 0,
            'who_made' => $productData['who_made'] ?? 'i_did', // i_did, someone_else, collective
            'when_made' => $productData['when_made'] ?? '2020_2024', // made_to_order, 2020_2024, etc
            'taxonomy_id' => $productData['taxonomy_id'] ?? null,
            'shipping_profile_id' => $productData['shipping_profile_id'] ?? null,
            'return_policy_id' => $productData['return_policy_id'] ?? null,
            'materials' => $productData['materials'] ?? [],
            'shop_section_id' => $productData['shop_section_id'] ?? null,
            'processing_min' => $productData['processing_min'] ?? 1,
            'processing_max' => $productData['processing_max'] ?? 3,
            'tags' => array_slice($productData['tags'] ?? [], 0, 13), // Max 13 tags
            'styles' => array_slice($productData['styles'] ?? [], 0, 2), // Max 2 styles
            'item_weight' => $productData['item_weight'] ?? null,
            'item_length' => $productData['item_length'] ?? null,
            'item_width' => $productData['item_width'] ?? null,
            'item_height' => $productData['item_height'] ?? null,
            'item_weight_unit' => $productData['item_weight_unit'] ?? 'oz',
            'item_dimensions_unit' => $productData['item_dimensions_unit'] ?? 'in',
            'is_personalizable' => $productData['is_personalizable'] ?? false,
            'personalization_is_required' => $productData['personalization_is_required'] ?? false,
            'personalization_char_count_max' => $productData['personalization_char_count_max'] ?? null,
            'personalization_instructions' => $productData['personalization_instructions'] ?? null,
            'production_partner_ids' => $productData['production_partner_ids'] ?? [],
            'image_ids' => $productData['image_ids'] ?? [],
            'is_supply' => $productData['is_supply'] ?? false,
            'is_customizable' => $productData['is_customizable'] ?? false,
            'should_auto_renew' => $productData['should_auto_renew'] ?? true,
            'is_taxable' => $productData['is_taxable'] ?? true,
            'type' => $productData['type'] ?? 'physical', // physical or download
        ];
    }
}
