<?php

namespace App\Integrations\Amazon\Xml;

use App\Models\{Channel, ProductVariant};

class PriceFeedBuilder
{
    public static function build(Channel $channel, array $variants): string
    {
        $merchant = $channel->auth_json['seller_id'] ?? 'UNKNOWN';
        $currency = $channel->auth_json['currency'] ?? 'USD';
        $w = new AmazonXmlWriter($merchant);
        $w->startMessageType('Price');
        foreach ($variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            $price = number_format((float)($v->price ?? 0), 2, '.', '');
            $w->message(function($x) use ($sku, $price, $currency){
                $x->elem('Price', function($x) use ($sku, $price, $currency){
                    $x->elem('SKU', null, $sku);
                    $x->elem('StandardPrice', null, $price, ['currency' => $currency]);
                });
            });
        }
        return $w->end();
    }
}
