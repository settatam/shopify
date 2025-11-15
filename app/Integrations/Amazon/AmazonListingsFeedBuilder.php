<?php

namespace App\Integrations\Amazon;

use App\Models\{Channel, Product, ProductVariant};

class AmazonListingsFeedBuilder
{
    public static function pricePatch(float $price, string $currency = 'USD'): array
    {
        return [ 'op' => 'replace', 'path' => '/attributes/standardPrice', 'value' => [[ 'currency' => $currency, 'amount' => (float) number_format($price, 2, '.', '') ]] ];
    }
    public static function qtyPatch(int $qty): array
    {
        return [ 'op' => 'replace', 'path' => '/attributes/fulfillmentAvailability', 'value' => [[ 'fulfillmentChannelCode' => 'MFN', 'quantity' => max(0,$qty) ]] ];
    }

    /** Build a batch payload for many variants */
    public static function buildRequests(Channel $channel, array $variants, ?string $productType = null): array
    {
        $seller = $channel->auth_json['seller_id'] ?? '';
        $mids = $channel->auth_json['marketplace_ids'] ?? [];
        $curr = $channel->auth_json['currency'] ?? 'USD';


        $reqs = [];
        foreach ($variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            $patches = [];
            if ($v->price !== null) $patches[] = self::pricePatch((float)$v->price, $curr);
            if ($v->quantity !== null) $patches[] = self::qtyPatch((int)$v->quantity);
            if (!$patches) continue;
            $reqs[] = [
                'uri' => '/listings/2021-08-01/items/'.$seller.'/'.$sku,
                'method' => 'PATCH',
                'marketplaceIds' => $mids,
                'body' => array_filter(['productType' => $productType, 'patches' => $patches])
            ];
        }
        return [ 'requests' => $reqs ];
    }
}
