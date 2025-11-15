<?php

namespace App\Integrations\Amazon\Xml;
use App\Models\{Product, Channel};
class RelationshipFeedBuilder
{
    public static function build(Channel $channel, Product $p, string $parentSku, array $childSkus): string
    {
        $merchant = $channel->auth_json['seller_id'] ?? 'UNKNOWN';
        $w = new AmazonXmlWriter($merchant);
        $w->startMessageType('Relationship');
        $w->message(function($x) use ($parentSku, $childSkus){
            $x->elem('Relationship', function($x) use ($parentSku, $childSkus){
                $x->elem('ParentSKU', null, $parentSku);
                foreach ($childSkus as $sku) {
                    $x->elem('Relation', function($x) use ($sku){
                        $x->elem('SKU', null, $sku);
                        $x->elem('Type', null, 'Variation');
                    });
                }
            });
        });
        return $w->end();
    }
}
