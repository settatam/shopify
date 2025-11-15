<?php

namespace App\Integrations\Amazon\Xml;

use App\Models\{Channel, ProductVariant};

class InventoryFeedBuilder
{
    public static function build(Channel $channel, array $variants, int $latency = 1): string
    {
        $merchant = $channel->auth_json['seller_id'] ?? 'UNKNOWN';
        $w = new AmazonXmlWriter($merchant);
        $w->startMessageType('Inventory');
        foreach ($variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            $qty = max(0, (int)($v->quantity ?? 0));
            $w->message(function($x) use ($sku, $qty, $latency){
                $x->elem('Inventory', function($x) use ($sku, $qty, $latency){
                    $x->elem('SKU', null, $sku);
                    $x->elem('Quantity', null, (string)$qty);
                    $x->elem('FulfillmentLatency', null, (string)$latency);
                });
            });
        }
        return $w->end();
    }
}
