<?php

namespace App\Integrations\Amazon\Xml;
use App\Models\{Channel, Product, ProductVariant};
use App\Services\MediaService;

class ImageFeedBuilder
{
    public static function build(Channel $channel, Product $p, array $variants): string
    {
        $merchant = $channel->auth_json['seller_id'] ?? 'UNKNOWN';
        $w = new AmazonXmlWriter($merchant);
        $w->startMessageType('ProductImage');


        $imgs = MediaService::productImages($p, 12);
        $main = $imgs[0] ?? null; $others = array_slice($imgs, 1);
        $typeMap = ['PT1','PT2','PT3','PT4','PT5','PT6','PT7','PT8'];


        foreach ($variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            if ($main) {
                $w->message(function($x) use ($sku, $main){
                    $x->elem('ProductImage', function($x) use ($sku, $main){
                        $x->elem('SKU', null, $sku);
                        $x->elem('ImageType', null, 'Main');
                        $x->elem('ImageLocation', null, $main);
                    });
                });
            }
            $i = 0;
            foreach ($others as $u) {
                if ($i >= count($typeMap)) break;
                $label = $typeMap[$i++];
                $w->message(function($x) use ($sku, $u, $label){
                    $x->elem('ProductImage', function($x) use ($sku, $u, $label){
                        $x->elem('SKU', null, $sku);
                        $x->elem('ImageType', null, $label);
                        $x->elem('ImageLocation', null, $u);
                    });
                });
            }
        }
        return $w->end();
    }
}
