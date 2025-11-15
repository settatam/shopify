<?php

namespace App\Integrations\Ebay;

use App\Services\MediaService;
use App\Models\{Product, ProductVariant, Channel, ChannelLocation};
use App\Services\TokenMapper;
use Illuminate\Support\Arr;

class EbayListingService {
    public function __construct(
        protected EbayClient $client,
    ) {}

    public function putInventoryItem(Product $p, ProductVariant $v, array $mapping): void
    {
        $sku = $v->sku ?: (string)$v->shopify_variant_id;
        $ctx = [ 'product' => $p->toArray(), 'variant' => $v->toArray() ];
        $payload = [
            'sku' => $sku,
            'product' => [
                'title' => TokenMapper::resolve($mapping['title'] ?? '{product.title}', $ctx),
                'description' => strip_tags(TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', $ctx)),
// Optionally add aspects here if you have mapping for item specifics
            ],
            'condition' => 'NEW',
            'availability' => [ 'shipToLocationAvailability' => [ 'quantity' => (int)($v->quantity ?? 0) ] ]
        ];
        $this->client->request('PUT', '/sell/inventory/v1/inventory_item/'.$sku, ['json' => $payload]);
    }

    public function getOffersBySku(string $sku): array
    {
        $r = $this->client->request('GET', '/sell/inventory/v1/offer', ['query' => ['sku' => $sku]]);
        return json_decode($r->getBody(), true)['offers'] ?? [];
    }

    public function createOrUpdateOffer(Channel $channel, Product $p, ProductVariant $v, array $mapping, ChannelLocation $loc): array
    {
        $sku = $v->sku ?: (string)$v->shopify_variant_id;
        $auth = $channel->auth_json;
        $pol = $auth['listing_policies'] ?? [];
        $price = number_format((float)($v->price ?? 0), 2, '.', '');


        $body = [
            'sku' => $sku,
            'marketplaceId' => $auth['marketplace_id'] ?? 'EBAY_US',
            'format' => 'FIXED_PRICE',
            'availableQuantity' => (int)($v->quantity ?? 0),
            'categoryId' => $mapping['category'] ?? null,
            'listingDescription' => strip_tags($mapping['description'] ?? ''),
            'merchantLocationKey' => $loc->merchant_location_key,
            'pricingSummary' => [ 'price' => [ 'value' => $price, 'currency' => $auth['currency'] ?? 'USD' ] ],
            'listingPolicies' => [
                'fulfillmentPolicyId' => $pol['fulfillment_policy_id'] ?? null,
                'paymentPolicyId' => $pol['payment_policy_id'] ?? null,
                'returnPolicyId' => $pol['return_policy_id'] ?? null,
            ],
        ];


        $offers = $this->getOffersBySku($sku);
        if ($offers) {
            $id = $offers[0]['offerId'];
            $r = $this->client->request('PUT', '/sell/inventory/v1/offer/'.$id, ['json' => array_replace_recursive($offers[0], $body)]);
            return json_decode($r->getBody(), true);
        }
        $r = $this->client->request('POST', '/sell/inventory/v1/offer', ['json' => $body]);
        return json_decode($r->getBody(), true);
    }

    public function publishOffer(string $offerId): void
    {
        $this->client->request('POST', '/sell/inventory/v1/offer/'.$offerId.'/publish', ['json' => new \stdClass]);
    }

    public function putInventoryItemGroup(Channel $channel, Product $p, array $mapping, string $groupKey): void
    {
        $images = MediaService::productImages($p, 12);
        $ctx = ['product' => $p->toArray()];
        $title = TokenMapper::resolve($mapping['title'] ?? '{product.title}', $ctx);
        $desc = strip_tags(TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', $ctx));


        $theme = $mapping['variation_theme'] ?? 'SizeColor';
        $built = EbayVariationBuilder::build($p, $theme);


        $payload = [
            'title' => $title,
            'description' => $desc,
            'imageUrls' => $images,
            'variesBy' => $built['variesBy'],
            'variantSKUs' => $built['variantSKUs'],
// Common, non‑pivoting aspects across all variations (optional, fill from mapping if needed)
            'aspects' => $mapping['common_aspects'] ?? []
        ];


        $this->client->request('PUT', '/sell/inventory/v1/inventory_item_group/'.$groupKey, ['json' => $payload]);
    }

    /** Create multiple offers for each SKU in the group (max 25 per call) */
    public function bulkCreateOffers(Channel $channel, Product $p, array $mapping, ChannelLocation $loc): array
    {
        $market = $channel->auth_json['marketplace_id'] ?? 'EBAY_US';
        $currency = $channel->auth_json['currency'] ?? 'USD';
        $theme = $mapping['variation_theme'] ?? 'SizeColor';


        $offers = [];
        foreach ($p->variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            $price = number_format((float)($v->price ?? 0), 2, '.', '');
            $offers[] = [
                'sku' => $sku,
                'marketplaceId' => $market,
                'format' => 'FIXED_PRICE',
                'availableQuantity' => (int)($v->quantity ?? 0),
                'categoryId' => $mapping['category'] ?? null,
                'listingDescription' => strip_tags($mapping['description'] ?? ''),
                'merchantLocationKey' => $loc->merchant_location_key,
                'pricingSummary' => [ 'price' => [ 'value' => $price, 'currency' => $currency ] ],
                'listingPolicies' => [
                    'fulfillmentPolicyId' => $channel->auth_json['listing_policies']['fulfillment_policy_id'] ?? null,
                    'paymentPolicyId' => $channel->auth_json['listing_policies']['payment_policy_id'] ?? null,
                    'returnPolicyId' => $channel->auth_json['listing_policies']['return_policy_id'] ?? null,
                ],
            ];
        }


        $chunks = array_chunk($offers, 25);
        $created = [];
        foreach ($chunks as $chunk) {
            $res = $this->client->request('POST', '/sell/inventory/v1/offer/bulk_create', ['json' => ['requests' => array_map(fn($o)=>['offer' => $o], $chunk)]]);
            $data = json_decode($res->getBody(), true);
            foreach ($data['responses'] ?? [] as $r) {
                if (!empty($r['offerId'])) $created[] = $r['offerId'];
            }
        }
        return $created;
    }

    public function publishByGroup(Channel $channel, string $groupKey): array
    {
        $market = $channel->auth_json['marketplace_id'] ?? 'EBAY_US';
        $res = $this->client->request('POST', '/sell/inventory/v1/offer/publish_by_inventory_item_group', [
            'json' => [ 'inventoryItemGroupKey' => $groupKey, 'marketplaceId' => $market ]
        ]);
        return json_decode($res->getBody(), true);
    }
}
