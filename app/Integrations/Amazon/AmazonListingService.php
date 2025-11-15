<?php

namespace App\Integrations\Amazon;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\MediaService;
use App\Services\TokenMapper;

class AmazonListingService
{
    public function __construct(protected AmazonClient $client, protected Channel $channel) {}

    protected function mids(): array { return $this->channel->auth_json['marketplace_ids'] ?? []; }
    protected function sellerId(): string { return $this->channel->auth_json['seller_id'] ?? ''; }

    public function putListing(Product $p, ProductVariant $v, array $mapping): array
    {
        $sku = $v->sku ?: (string)$v->shopify_variant_id;
        $ctx = ['product' => $p->toArray(), 'variant' => $v->toArray()];
        $title = TokenMapper::resolve($mapping['title'] ?? '{product.title}', $ctx);
        $desc = strip_tags(TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', $ctx));
        $ptype = $mapping['category'] ?? null; // productType name


        $body = [
            'productType' => $ptype,
            'requirements' => 'LISTING',
            'attributes' => [
                'item_name' => [ ['value' => $title] ],
                'brand' => isset($p->brand) ? [ ['value' => $p->brand] ] : null,
                'description' => [ ['value' => $desc] ],
// Add more mapped attributes here according to productType schema
            ],
        ];
// Clean nulls
        $body['attributes'] = array_filter($body['attributes']);


        $r = $this->client->request('PUT', '/listings/2021-08-01/items/'.$this->sellerId().'/'.$sku, [
            'query' => ['marketplaceIds' => implode(',', $this->mids())],
            'json' => $body,
        ]);
        return json_decode($r->getBody(), true);
    }

    /** PATCH selective updates: price/quantity/images/etc. */
    public function patchListing(ProductVariant $v, array $ops): array
    {
        $sku = $v->sku ?: (string)$v->shopify_variant_id;
        $r = $this->client->request('PATCH', '/listings/2021-08-01/items/'.$this->sellerId().'/'.$sku, [
            'query' => ['marketplaceIds' => implode(',', $this->mids())],
            'json' => [ 'patches' => $ops ],
        ]);
        return json_decode($r->getBody(), true);
    }

    public function updateQuantity(ProductVariant $v, int $qty): array
    {
        return $this->patchListing($v, [[
            'op' => 'replace',
            'path' => '/attributes/fulfillmentAvailability',
            'value' => [[ 'fulfillmentChannelCode' => 'MFN', 'quantity' => max(0,$qty) ]],
        ]]);
    }

    public function updatePrice(ProductVariant $v, float $price, string $currency = 'USD'): array
    {
        return $this->patchListing($v, [[
            'op' => 'replace',
            'path' => '/attributes/standardPrice',
            'value' => [[ 'currency' => $currency, 'amount' => (float) number_format($price, 2, '.', '') ]],
        ]]);
    }

    public function putParent(Product $p, array $mapping, string $parentSku): array
    {
        $ptype = $mapping['category'] ?? null; // productType name
        $theme = $mapping['variation_theme'] ?? 'SizeColor';
        $ax = $mapping['amazon'] ?? [];
        $attrVariationTheme = $ax['variation_theme_attr'] ?? 'variationTheme';
        $attrParentage = $ax['parentage_attr'] ?? 'parentage';

        $title = \App\Services\TokenMapper::resolve($mapping['title'] ?? '{product.title}', ['product' => $p->toArray()]);
        $desc = strip_tags(\App\Services\TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', ['product' => $p->toArray()]));

        $body = [
            'productType' => $ptype,
            'requirements' => 'LISTING',
            'attributes' => array_filter([
                'item_name' => [ ['value' => $title] ],
                'description' => [ ['value' => $desc] ],
                $attrParentage => [ ['value' => 'parent'] ],
                $attrVariationTheme => [ ['value' => $theme] ],
// add brand, bullets, images etc. as needed
            ])
        ];

        $r = $this->client->request('PUT', '/listings/2021-08-01/items/'.$this->sellerId().'/'.$parentSku, [
            'query' => ['marketplaceIds' => implode(',', $this->mids())],
            'json' => $body,
        ]);
        return json_decode($r->getBody(), true);
    }

    public function putChild(Product $p, ProductVariant $v, array $mapping, string $parentSku): array
    {
        $ptype = $mapping['category'] ?? null;
        $theme = $mapping['variation_theme'] ?? 'SizeColor';
        $ax = $mapping['amazon'] ?? [];
        $attrVariationTheme = $ax['variation_theme_attr'] ?? 'variationTheme';
        $attrParentage = $ax['parentage_attr'] ?? 'parentage';
        $attrRelType = $ax['relationship_type_attr'] ?? 'relationship_type';
        $attrParentSku = $ax['parent_sku_attr'] ?? 'parent_sku';
        $attrSize = $ax['size_attr'] ?? 'size_name';
        $attrColor = $ax['color_attr'] ?? 'color_name';


        $sku = $v->sku ?: (string)$v->shopify_variant_id;
        $ctx = ['product' => $p->toArray(), 'variant' => $v->toArray()];
        $title = \App\Services\TokenMapper::resolve($mapping['title'] ?? '{product.title}', $ctx);
        $desc = strip_tags(\App\Services\TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', $ctx));


        $opts = \App\Integrations\Amazon\AmazonVariationBuilder::extractOptionValues($v, $theme);


        $qty = (int)($v->quantity ?? 0);
        $price = (float) number_format((float)($v->price ?? 0), 2, '.', '');
        $currency = $this->channel->auth_json['currency'] ?? 'USD';


        $attributes = array_filter([
            'item_name' => [ ['value' => $title] ],
            'description' => [ ['value' => $desc] ],
            $attrParentage => [ ['value' => 'child'] ],
            $attrVariationTheme => [ ['value' => $theme] ],
            $attrRelType => [ ['value' => 'variation'] ],
            $attrParentSku => [ ['value' => $parentSku] ],
// pivot attributes
            $attrSize => $opts['size'] ? [ ['value' => (string)$opts['size'] ] ] : null,
            $attrColor => $opts['color'] ? [ ['value' => (string)$opts['color'] ] ] : null,
// price & availability live as attributes in Listings Items patch model
            'standardPrice' => [ [ 'currency' => $currency, 'amount' => $price ] ],
            'fulfillmentAvailability' => [ [ 'fulfillmentChannelCode' => 'MFN', 'quantity' => max(0,$qty) ] ],
        ]);

        $extra = $mapping['amazon']['attributes'] ?? [];

        foreach ($extra as $k => $tpl) {
            if($tpl === null || $tpl === '') continue;
            $val = \App\Services\TokenMapper::resolve((string)$tpl, ['product' => $p->toArray(), 'variant' => isset($v) ? $v->toArray() : null]);
            // If attribute already set, do not override (images / price / qty)
            if(!array_key_exists($k, $attributes)) {
                $attributes[$k] = [[ 'value' => $val ]];
            }
        }


        $body = [ 'productType' => $ptype, 'requirements' => 'LISTING', 'attributes' => $attributes ];

        $this->applyImages($p, $attributes, $mapping['amazon'] ?? []);


        $r = $this->client->request('PUT', '/listings/2021-08-01/items/'.$this->sellerId().'/'.$sku, [
            'query' => ['marketplaceIds' => implode(',', $this->mids())],
            'json' => $body,
        ]);
        return json_decode($r->getBody(), true);
    }

    protected function applyImages(\App\Models\Product $p, array &$attributes, array $amazon): void
    {
        $imgs = MediaService::productImages($p, 12);
        if (!$imgs) return;
        $mainKey = $amazon['image']['main'] ?? 'main_image';
        $otherPrefix = $amazon['image']['other_prefix'] ?? 'other_image_url';
        $maxOthers = (int)($amazon['image']['max_others'] ?? 8);


        $main = array_shift($imgs);
        $attributes[$mainKey] = [[ 'value' => $main ]];
        $i = 1;
        foreach ($imgs as $u) {
            if ($i > $maxOthers) break;
            $attributes[$otherPrefix . $i] = [[ 'value' => $u ]];
            $i++;
        }
    }


}
