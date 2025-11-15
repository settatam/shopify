<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;


class ShopifyClient
{
    protected Client $http; protected string $base;


    public function __construct(protected Shop $shop)
    {
        $this->base = "https://{$shop->shopify_domain}/admin/api/2024-10"; // set version as needed
        $this->http = HttpFactory::make([
            'headers' => [
                'X-Shopify-Access-Token' => $shop->access_token,
                'Content-Type' => 'application/json'
            ]
        ]);
    }


    public function products(array $params = []): array
    {
        $res = $this->http->get($this->base.'/products.json', ['query' => $params]);
        return json_decode($res->getBody(), true)['products'] ?? [];
    }


    public function createOrder(array $body): array
    {
        $res = $this->http->post($this->base.'/orders.json', ['json' => ['order' => $body]]);
        return json_decode($res->getBody(), true)['order'] ?? [];
    }
}
