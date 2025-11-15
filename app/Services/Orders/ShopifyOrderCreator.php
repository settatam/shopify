<?php

namespace App\Services\Orders;

use App\Models\ChannelOrder;
use App\Services\Shopify\ShopifyClient;

class ShopifyOrderCreator
{
    public static function push(ChannelOrder $co): ?int
    {
        $shop = $co->channel->shop;
        $client = new ShopifyClient($shop);
        $body = [
            'email' => $co->raw_json['buyer'] ['email'] ?? null,
            'line_items' => array_map(function($it){
                return [ 'sku' => $it->sku, 'quantity' => $it->qty, 'price' => (string) $it->price ];
            }, $co->items()->get()->all()),
            'financial_status' => 'paid',
        ];
        $order = $client->createOrder($body);
        return $order['id'] ?? null;
    }
}
