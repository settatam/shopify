<?php

namespace App\Integrations\ChannelAdapters;

use App\Integrations\Contracts\{ChannelAdapter, ChannelAdapters\Channel, SyncResult};
use App\Integrations\Ebay\EbayAuthService;
use App\Integrations\Ebay\EbayClient;
use App\Integrations\Ebay\EbayInventoryService;
use App\Integrations\Ebay\EbayListingService;
use App\Integrations\Ebay\EbayPoliciesService;
use App\Models\{ChannelLocation, Product, ProductVariant};
use App\Services\TokenMapper;
use DateTimeInterface;
use Illuminate\Support\Arr;

class EbayAdapter implements ChannelAdapter
{
    protected array $auth;
    protected array $opts;
    protected $http;
    protected string $base = 'https://api.ebay.com';

    public function __construct(
        protected EbayAuthService $authSvc,
        protected EbayClient $client, // bound with current Channel via factory in registry
        protected EbayPoliciesService $polSvc,
        protected EbayInventoryService $invSvc,
        protected EbayListingService $listSvc,
    ) {}

    public function boot(array $auth, array $options = []): static { return $this; }
    public function withChannel(Channel $c): static
    {
        $this->channel = $c;
// Rebind client for this channel (if you prefer factory pattern, inject differently)
        $this->client = app()->makeWith(EbayClient::class, ['channel' => $c, 'auth' => $this->authSvc]);
        $this->polSvc = app()->makeWith(EbayPoliciesService::class, ['client' => $this->client]);
        $this->invSvc = app()->makeWith(EbayInventoryService::class, ['client' => $this->client]);
        $this->listSvc = app()->makeWith(EbayListingService::class, ['client' => $this->client]);
        return $this;
    }

    public function getCategories(): iterable
    {
// Placeholder: In production, call Taxonomy API and upsert ChannelCategory
        return [];
    }

    protected function defaultLocation(): ?ChannelLocation
    {
        return $this->channel->hasMany(ChannelLocation::class)->where('is_default', true)->first() ??
            $this->channel->hasMany(ChannelLocation::class)->first();
    }


    public function upsertProduct(Product $p, array $mapping): SyncResult
    {
        $loc = $this->defaultLocation();
        if(!$loc) return new SyncResult(false, null, ['location' => 'No eBay inventory location configured']);


// Put inventory items for all variants (with aspects + images)
        foreach ($p->variants as $v) { $this->listSvc->putInventoryItem($p, $v, $mapping); }


// If multiple variants and theme selected, also create/replace group metadata
        if ($p->variants()->count() > 1) {
            $groupKey = 'group-'.$p->id; // or derive from mapping
            $this->listSvc->putInventoryItemGroup($this->channel, $p, $mapping, $groupKey);
        }
        return new SyncResult(true);
    }


    public function upsertVariant(ProductVariant $v, array $mapping): SyncResult
    {
        $loc = $this->defaultLocation();
        if(!$loc) return new SyncResult(false, null, ['location' => 'No eBay inventory location configured']);


        $p = $v->product; $multi = $p->variants()->count() > 1;


        if (!$multi) {
// Single‑SKU: create or update one offer and publish normally
            $res = $this->listSvc->createOrUpdateOffer($this->channel, $p, $v, $mapping, $loc);
            if (!empty($res['offerId'])) $this->listSvc->publishOffer($res['offerId']);
            return new SyncResult(!empty($res['offerId']), $res['offerId'] ?? null, $res['errors'] ?? [], $res);
        }


// Multi‑variation: ensure group exists, create offers for all SKUs, then publish by group
        $groupKey = 'group-'.$p->id; // ensure matches group created in upsertProduct


// Optional: category variations support check
        $cat = (string)($mapping['category'] ?? ''); $market = $this->channel->auth_json['marketplace_id'] ?? 'EBAY_US';
        if ($cat) {
            $comp = app()->makeWith(\App\Integrations\Ebay\EbayComplianceService::class, ['client' => $this->client]);
            if (!$comp->supportsVariations($market, $cat)) {
                return new SyncResult(false, null, ['category' => 'Selected category does not support variations']);
            }
        }

// Build group (idempotent)
        $this->listSvc->putInventoryItemGroup($this->channel, $p, $mapping, $groupKey);


// Create or update offers for all variants
        $offerIds = $this->listSvc->bulkCreateOffers($this->channel, $p, $mapping, $loc);


// Publish by group
        $pub = $this->listSvc->publishByGroup($this->channel, $groupKey);
        return new SyncResult(true, $pub['listingId'] ?? null, [], ['offerIds' => $offerIds, 'publish' => $pub]);
    }

    public function updateInventory(string $sku, int $qty): SyncResult
    {
        $payload = [ 'availability' => [ 'shipToLocationAvailability' => [ 'quantity' => max(0,$qty) ] ] ];
        $this->client->request('PUT', '/sell/inventory/v1/inventory_item/'.$sku, ['json' => $payload]);
        return new SyncResult(true);
    }


    public function updatePrice(string $sku, float $price): SyncResult
    {
        $offers = json_decode($this->client->request('GET','/sell/inventory/v1/offer',['query'=>['sku'=>$sku]])->getBody(), true)['offers'] ?? [];
        foreach ($offers as $off) {
            $off['pricingSummary']['price'] = [ 'value' => number_format($price, 2, '.', ''), 'currency' => $off['pricingSummary']['price']['currency'] ?? 'USD' ];
            $this->client->request('PUT', '/sell/inventory/v1/offer/'.$off['offerId'], ['json' => $off]);
        }
        return new SyncResult(true);
    }

    public function fetchOrders(DateTimeInterface $since): iterable
    {
// Use Fulfillment API to list orders
        $res = $this->http->get($this->base.'/sell/fulfillment/v1/order', [ 'query' => [ 'filter' => 'creationdate:['.$since->format('c').'..]' ] ]);
        $data = json_decode($res->getBody(), true);
        foreach ($data['orders'] ?? [] as $o) {
            yield [
                'id' => $o['orderId'],
                'status' => $o['orderFulfillmentStatus'] ?? null,
                'total' => Arr::get($o, 'pricingSummary.total.value'),
                'currency' => Arr::get($o, 'pricingSummary.total.currency'),
                'placed_at' => $o['creationDate'] ?? null,
                'items' => array_map(function($li){
                    return [
                        'id' => $li['lineItemId'] ?? null,
                        'sku' => $li['sku'] ?? null,
                        'qty' => (int)($li['quantity'] ?? 1),
                        'price' => Arr::get($li, 'lineItemCost.value')
                    ];
                }, $o['lineItems'] ?? [])
            ];
        }
    }

    public function acknowledgeOrder(string $externalId): void
    {
        // Optional: not required on eBay
    }

    protected function putInventoryItem(ProductVariant $v, Product $p, array $mapping): void
    {
        $sku = $v->sku ?: (string)$v->shopify_variant_id;
        $ctx = [
            'product' => [
                'title' => $p->title, 'description' => $p->description, 'vendor' => $p->vendor,
                'images' => [], // supply from your importer/metafields
            ],
            'variant' => [ 'option1' => $v->option1, 'option2' => $v->option2, 'option3' => $v->option3 ]
        ];

        $payload = [
            'sku' => $sku,
            'product' => [
                'title' => TokenMapper::resolve($mapping['title'] ?? '{product.title}', $ctx),
                'description' => TokenMapper::resolve($mapping['description'] ?? '{product.description|fallback:\'\'}', $ctx),
            ],
            'availability' => [ 'shipToLocationAvailability' => [ 'quantity' => 0 ] ],
            'condition' => 'NEW'
        ];

        $this->http->put($this->base.'/sell/inventory/v1/inventory_item/'.$sku, [ 'json' => $payload ]);
    }

    protected function createOrUpdateOffer(string $sku, ProductVariant $v, array $mapping): ?array
    {
        $price = number_format((float)($v->price ?? 0), 2, '.', '');
        $body = [
            'sku' => $sku,
            'marketplaceId' => $this->auth['marketplace_id'] ?? 'EBAY_US',
            'format' => 'FIXED_PRICE',
            'availableQuantity' => (int)($v->inventory_quantity ?? 0),
            'categoryId' => $mapping['category'] ?? null,
            'listingDescription' => strip_tags($mapping['description'] ?? ''),
            'pricingSummary' => [ 'price' => [ 'value' => $price, 'currency' => 'USD' ] ],
            'listingPolicies' => $this->auth['listing_policies'] ?? []
        ];


// Check existing offers by SKU
        $offers = $this->getOffersBySku($sku);
        if ($offers) {
            $offerId = $offers[0]['offerId'];
            $res = $this->http->put($this->base.'/sell/inventory/v1/offer/'.$offerId, [ 'json' => array_replace_recursive($offers[0], $body) ]);
            return json_decode($res->getBody(), true);
        }
        $res = $this->http->post($this->base.'/sell/inventory/v1/offer', [ 'json' => $body ]);
        return json_decode($res->getBody(), true);
    }

    protected function publishOffer(string $offerId): void
    {
        $this->http->post($this->base.'/sell/inventory/v1/offer/'.$offerId.'/publish', [ 'json' => new \stdClass ]);
    }


    protected function getOffersBySku(string $sku): array
    {
        $res = $this->http->get($this->base.'/sell/inventory/v1/offer?sku='.$sku);
        $data = json_decode($res->getBody(), true);
        return $data['offers'] ?? [];
    }
}
