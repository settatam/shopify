<?php

namespace App\Integrations\Amazon\Xml;

use App\Models\{Product, ProductVariant, Channel};
use App\Services\TokenMapper;

class ProductFeedBuilder
{
    public static function productTypeNode(string $productType): string
    {
// Map productType name to ProductData node
        return match (strtoupper($productType)) {
            'CLOTHING' => 'Clothing',
            'SHOES' => 'Shoes',
            'SPORTS' => 'Sports',
            'HOME' => 'Home',
            default => 'Miscellaneous'
        };
    }

    public static function parentSku(Product $p, Channel $ch): string { return 'PARENT-'.$ch->id.'-'.$p->id; }

    public static function build(Channel $channel, Product $p, array $mapping): string
    {
        $merchant = $channel->auth_json['seller_id'] ?? 'UNKNOWN';
        $type = (string)($mapping['category'] ?? 'MISC');
        $node = self::productTypeNode($type);
        $theme = $mapping['variation_theme'] ?? 'SizeColor';
        $ax = $mapping['amazon'] ?? [];
        $sizeAttr = $ax['size_attr'] ?? 'size_name';
        $colorAttr = $ax['color_attr'] ?? 'color_name';


        $w = new AmazonXmlWriter($merchant);
        $w->startMessageType('Product');


// Parent message (no price/qty)
        $parentSku = self::parentSku($p, $channel);
        $title = TokenMapper::resolve($mapping['title'] ?? '{product.title}', ['product'=>$p->toArray()]);
        $desc = strip_tags(TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', ['product'=>$p->toArray()]));

        $w->message(function($x) use ($parentSku, $title, $desc, $node, $theme) {
            $x->elem('Product', function($x) use ($parentSku, $title, $desc, $node, $theme){
                $x->elem('SKU', null, $parentSku);
                $x->elem('DescriptionData', function($x) use ($title, $desc){
                    $x->elem('Title', null, $title);
                    $x->elem('Description', null, $desc);
                });
                $x->elem('ProductData', function($x) use ($node, $theme){
                    $x->elem($node, function($x) use ($theme){
                        $x->elem('VariationData', function($x) use ($theme){
                            $x->elem('Parentage', null, 'parent');
                            $x->elem('VariationTheme', null, $theme);
                        });
                    });
                });
            });
        }, 'PartialUpdate');

// Child messages
        foreach ($p->variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            $ctx = ['product'=>$p->toArray(),'variant'=>$v->toArray()];
            $ctitle = TokenMapper::resolve($mapping['title'] ?? '{product.title}', $ctx);
            $cdesc = strip_tags(TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', $ctx));
            $idType = IdGuess::type($v->barcode);


            $sizeVal = $v->option1; $colorVal = $v->option2; // adjust per theme if needed
            if (strtoupper($theme)==='COLORSIZE') { $colorVal=$v->option1; $sizeVal=$v->option2; }


            $w->message(function($x) use ($sku, $ctitle, $cdesc, $idType, $v, $node, $sizeAttr, $sizeVal, $colorAttr, $colorVal, $theme){
                $x->elem('Product', function($x) use ($sku, $ctitle, $cdesc, $idType, $v, $node, $sizeAttr, $sizeVal, $colorAttr, $colorVal, $theme){
                    $x->elem('SKU', null, $sku);
                    if ($idType) {
                        $x->elem('StandardProductID', function($x) use ($idType, $v){
                            $x->elem('Type', null, $idType);
                            $x->elem('Value', null, preg_replace('/\D/','', (string)$v->barcode));
                        });
                    }
                    $x->elem('DescriptionData', function($x) use ($ctitle, $cdesc){
                        $x->elem('Title', null, $ctitle);
                        $x->elem('Description', null, $cdesc);
                    });
                    $x->elem('ProductData', function($x) use ($node, $theme, $sizeAttr, $sizeVal, $colorAttr, $colorVal){
                        $x->elem($node, function($x) use ($theme, $sizeAttr, $sizeVal, $colorAttr, $colorVal){
                            $x->elem('VariationData', function($x) use ($theme, $sizeAttr, $sizeVal, $colorAttr, $colorVal){
                                $x->elem('Parentage', null, 'child');
                                $x->elem('VariationTheme', null, $theme);
                                if ($sizeVal) $x->elem('Size', null, (string)$sizeVal);
                                if ($colorVal) $x->elem('Color', null, (string)$colorVal);
                            });
                        });
                    });
                });
            }, 'PartialUpdate');
        }


        return $w->end();
    }
}
