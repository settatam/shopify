<?php

namespace App\Services\Shopify;

use App\Models\Shop;
use App\Support\Http\HttpFactory;

class WebhookRegistrar
{
    public static array $topics = [
        'products/create','products/update','products/delete','inventory_levels/update','orders/fulfilled'
    ];


    public static function registerAll(Shop $shop, string $callbackBase): void
    {
        $http = HttpFactory::make([
            'headers' => [ 'X-Shopify-Access-Token' => $shop->access_token, 'Content-Type' => 'application/json' ]
        ]);
        foreach (self::$topics as $t) {
            $http->post("https://{$shop->shopify_domain}/admin/api/2024-10/webhooks.json", [
                'json' => ['webhook' => ['topic' => $t, 'format' => 'json', 'address' => rtrim($callbackBase,'/').'/api/shopify/webhooks']]
            ]);
        }
    }
}
