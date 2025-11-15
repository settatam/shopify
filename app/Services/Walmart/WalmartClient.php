<?php

namespace App\Services\Walmart;

use App\Models\Channel;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WalmartClient
{
    protected Client $http;
    protected string $baseUrl;
    protected Channel $channel;
    protected ?string $accessToken = null;

    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
        $this->baseUrl = config('services.walmart.sandbox')
            ? 'https://sandbox.walmartapis.com'
            : 'https://marketplace.walmartapis.com';

        $this->http = HttpFactory::make([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Get OAuth access token
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        // Try to get from cache
        $cacheKey = "walmart_token_{$this->channel->id}";
        $token = Cache::get($cacheKey);

        if ($token) {
            $this->accessToken = $token;
            return $token;
        }

        // Get new token
        $token = $this->refreshAccessToken();
        $this->accessToken = $token;

        // Cache for 14 minutes (tokens valid for 15 minutes)
        Cache::put($cacheKey, $token, now()->addMinutes(14));

        return $token;
    }

    /**
     * Refresh access token using client credentials
     */
    protected function refreshAccessToken(): string
    {
        $authJson = $this->channel->auth_json ?? [];
        $clientId = $authJson['client_id'] ?? config('services.walmart.client_id');
        $clientSecret = $authJson['client_secret'] ?? config('services.walmart.client_secret');

        if (!$clientId || !$clientSecret) {
            throw new \Exception('Walmart API credentials not configured');
        }

        try {
            $response = $this->http->post('/v3/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$clientId}:{$clientSecret}"),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? throw new \Exception('No access token in response');
        } catch (RequestException $e) {
            Log::error('Walmart token refresh failed: ' . $e->getMessage());
            throw new \Exception('Failed to get Walmart access token: ' . $e->getMessage());
        }
    }

    /**
     * Make authenticated API request
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        $token = $this->getAccessToken();

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'WM_SEC.ACCESS_TOKEN' => $token,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
        ]);

        try {
            $response = $this->http->request($method, $uri, $options);
            return json_decode($response->getBody(), true) ?? [];
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            Log::error("Walmart API error [{$method} {$uri}]: " . $e->getMessage() . " - " . $body);
            throw $e;
        }
    }

    /**
     * Get all items
     */
    public function getItems(array $params = []): array
    {
        $defaultParams = [
            'limit' => 20,
            'offset' => 0,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->request('GET', '/v3/items', [
            'query' => $params,
        ]);
    }

    /**
     * Get item by SKU
     */
    public function getItem(string $sku): ?array
    {
        try {
            $response = $this->request('GET', "/v3/items/{$sku}");
            return $response;
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create or update item (bulk)
     */
    public function bulkItemSetup(array $items): array
    {
        $xml = $this->buildItemXml($items);

        return $this->request('POST', '/v3/feeds', [
            'query' => ['feedType' => 'item'],
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $xml,
                    'filename' => 'items.xml',
                ],
            ],
        ]);
    }

    /**
     * Update inventory for SKU
     */
    public function updateInventory(string $sku, int $quantity): array
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<InventoryFeed xmlns="http://walmart.com/">
    <InventoryHeader>
        <version>1.4</version>
    </InventoryHeader>
    <inventory>
        <sku>{$sku}</sku>
        <quantity>
            <unit>EACH</unit>
            <amount>{$quantity}</amount>
        </quantity>
    </inventory>
</InventoryFeed>
XML;

        return $this->request('POST', '/v3/feeds', [
            'query' => ['feedType' => 'inventory'],
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $xml,
                    'filename' => 'inventory.xml',
                ],
            ],
        ]);
    }

    /**
     * Update price for SKU
     */
    public function updatePrice(string $sku, float $price, ?float $compareAtPrice = null): array
    {
        $compareAtPriceXml = $compareAtPrice
            ? "<comparisonPrice><value currency=\"USD\" amount=\"{$compareAtPrice}\"/></comparisonPrice>"
            : '';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<PriceFeed xmlns="http://walmart.com/">
    <PriceHeader>
        <version>1.5</version>
    </PriceHeader>
    <Price>
        <itemIdentifier>
            <sku>{$sku}</sku>
        </itemIdentifier>
        <pricingList>
            <pricing>
                <currentPrice>
                    <value currency="USD" amount="{$price}"/>
                </currentPrice>
                {$compareAtPriceXml}
            </pricing>
        </pricingList>
    </Price>
</PriceFeed>
XML;

        return $this->request('POST', '/v3/feeds', [
            'query' => ['feedType' => 'price'],
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $xml,
                    'filename' => 'price.xml',
                ],
            ],
        ]);
    }

    /**
     * Get all orders
     */
    public function getOrders(array $params = []): array
    {
        $defaultParams = [
            'limit' => 200,
            'createdStartDate' => now()->subDays(7)->toIso8601String(),
        ];

        $params = array_merge($defaultParams, $params);

        return $this->request('GET', '/v3/orders', [
            'query' => $params,
        ]);
    }

    /**
     * Get order by ID
     */
    public function getOrder(string $purchaseOrderId): array
    {
        return $this->request('GET', "/v3/orders/{$purchaseOrderId}");
    }

    /**
     * Acknowledge order
     */
    public function acknowledgeOrder(string $purchaseOrderId): array
    {
        return $this->request('POST', "/v3/orders/{$purchaseOrderId}/acknowledge");
    }

    /**
     * Ship order
     */
    public function shipOrder(string $purchaseOrderId, array $shipmentData): array
    {
        return $this->request('POST', "/v3/orders/{$purchaseOrderId}/shipping", [
            'json' => [
                'orderShipment' => $shipmentData,
            ],
        ]);
    }

    /**
     * Cancel order line
     */
    public function cancelOrderLine(string $purchaseOrderId, string $lineNumber, array $cancellationData): array
    {
        return $this->request('POST', "/v3/orders/{$purchaseOrderId}/cancel", [
            'json' => [
                'orderCancellation' => array_merge([
                    'orderLineNumber' => $lineNumber,
                ], $cancellationData),
            ],
        ]);
    }

    /**
     * Get feed status
     */
    public function getFeedStatus(string $feedId): array
    {
        return $this->request('GET', "/v3/feeds/{$feedId}", [
            'query' => ['includeDetails' => 'true'],
        ]);
    }

    /**
     * Get taxonomy (categories)
     */
    public function getTaxonomy(): array
    {
        return $this->request('GET', '/v3/taxonomy');
    }

    /**
     * Get category attributes
     */
    public function getCategoryAttributes(string $categoryId): array
    {
        return $this->request('GET', "/v3/taxonomy/departments/{$categoryId}/attributes");
    }

    /**
     * Build item XML for feed
     */
    protected function buildItemXml(array $items): string
    {
        $itemsXml = '';

        foreach ($items as $item) {
            $itemsXml .= $this->buildSingleItemXml($item);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ItemFeed xmlns="http://walmart.com/">
    <ItemFeedHeader>
        <version>1.4</version>
    </ItemFeedHeader>
    {$itemsXml}
</ItemFeed>
XML;
    }

    /**
     * Build single item XML
     */
    protected function buildSingleItemXml(array $item): string
    {
        $sku = $item['sku'];
        $productName = htmlspecialchars($item['productName']);
        $shortDescription = htmlspecialchars($item['shortDescription'] ?? $item['productName']);
        $price = $item['price'];
        $upc = $item['upc'] ?? '';
        $gtin = $item['gtin'] ?? $upc;
        $brand = htmlspecialchars($item['brand'] ?? '');
        $category = $item['category'] ?? '';

        // Build product identifiers
        $productIdXml = $gtin ? "<productId><productIdType>GTIN</productIdType><productIdValue>{$gtin}</productIdValue></productId>" : '';

        // Build images
        $imagesXml = '';
        if (!empty($item['images'])) {
            foreach ($item['images'] as $index => $imageUrl) {
                $imagesXml .= "<mainImageUrl>{$imageUrl}</mainImageUrl>";
                if ($index === 0) break; // Walmart requires at least one main image
            }
        }

        return <<<XML
    <Item>
        <sku>{$sku}</sku>
        {$productIdXml}
        <productName>{$productName}</productName>
        <shortDescription>{$shortDescription}</shortDescription>
        <price>{$price}</price>
        <brand>{$brand}</brand>
        {$imagesXml}
    </Item>
XML;
    }
}
