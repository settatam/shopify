<?php

namespace App\Integrations\ChannelAdapters;

use App\Integrations\Contracts\ChannelAdapter;
use App\Integrations\Contracts\SyncResult;
use DateTimeInterface;
use App\Integrations\Amazon\{AmazonAuthService,
    AmazonClient,
    AmazonListingsService,
    AmazonDefinitionsService,
    AmazonOrdersService,
    AmazonVariationBuilder};
use App\Models\{Channel, Product, ProductVariant, ChannelCategory, ChannelOrder, ChannelOrderItem};

class AmazonAdapter implements ChannelAdapter
{
    protected Channel $channel;
    protected AmazonClient $client;
    protected AmazonListingsService $listings;
    protected AmazonDefinitionsService $defs;
    protected AmazonOrdersService $orders;

    public function __construct(protected AmazonAuthService $authSvc) {}

    public function withChannel(Channel $c): static
    {
        $this->channel = $c;
        $this->client = app()->makeWith(AmazonClient::class, ['channel' => $c, 'authSvc' => $this->authSvc]);
        $this->listings = app()->makeWith(AmazonListingsService::class, ['client' => $this->client, 'channel' => $c]);
        $this->defs = app()->makeWith(AmazonDefinitionsService::class, ['client' => $this->client, 'channel' => $c]);
        $this->orders = app()->makeWith(AmazonOrdersService::class, ['client' => $this->client, 'channel' => $c]);
        return $this;
    }

    public function boot(array $auth, array $options = []): static { return $this; }

    /**
     * @inheritDoc
     */
    public function getCategories(): iterable
    {
        $this->defs->cacheTypes();
        return $this->channel->categories()->get();
    }

    public function upsertProduct(Product $p, array $mapping): SyncResult
    {
        $varsCount = $p->variants()->count();
        if ($varsCount <= 1) {
            foreach ($p->variants as $v) { $this->listings->putListing($p, $v, $mapping); }
            return new SyncResult(true);
        }


        $parentSku = AmazonVariationBuilder::parentSku($p, $this->channel->id);
        $this->listings->putParent($p, $mapping, $parentSku);


        foreach ($p->variants as $v) {
            $this->listings->putChild($p, $v, $mapping, $parentSku);
// Optional: ensure price/qty via PATCH if your productType requires separate updates
// $this->listings->updatePrice($v, (float)$v->price, $this->channel->auth_json['currency'] ?? 'USD');
// $this->listings->updateQuantity($v, (int)$v->quantity);
        }
        return new SyncResult(true, $parentSku);
    }

    public function upsertVariant(ProductVariant $v, array $mapping): SyncResult
    {
        $p = $v->product; $varsCount = $p->variants()->count();
        if ($varsCount <= 1) {
            $res = $this->listings->putListing($p, $v, $mapping);
            return new SyncResult(true, $res['sku'] ?? null, [], $res);
        }
        $parentSku = AmazonVariationBuilder::parentSku($p, $this->channel->id);
// Idempotent: make sure parent exists
        $this->listings->putParent($p, $mapping, $parentSku);
        $res = $this->listings->putChild($p, $v, $mapping, $parentSku);
        return new SyncResult(true, $res['sku'] ?? null, [], $res);
    }

    public function updateInventory(string $sku, int $qty): SyncResult
    {
        // TODO: Implement updateInventory() method.
    }

    public function updatePrice(string $sku, float $price): SyncResult
    {
        $v = ProductVariant::where('sku', $sku)->first();
        if(!$v) $v = ProductVariant::where('shopify_variant_id', (int)$sku)->first();
        if(!$v) return new SyncResult(false, null, ['variant' => 'Not found']);
        $curr = $this->channel->auth_json['currency'] ?? 'USD';
        $res = $this->listings->updatePrice($v, $price, $curr);
        return new SyncResult(true, null, [], $res);
    }

    /**
     * @inheritDoc
     */
    public function fetchOrders(DateTimeInterface $since): iterable
    {
        foreach ($this->orders->listOrders($since) as $o) {
            $items = $this->orders->listOrderItems($o['AmazonOrderId']);
            yield [
                'id' => $o['AmazonOrderId'],
                'status' => $o['OrderStatus'] ?? null,
                'total' => (float)($o['OrderTotal']['Amount'] ?? 0),
                'currency' => $o['OrderTotal']['CurrencyCode'] ?? 'USD',
                'placed_at' => $o['PurchaseDate'] ?? null,
                'items' => array_map(fn($it) => [
                    'id' => $it['OrderItemId'] ?? null,
                    'sku' => $it['SellerSKU'] ?? null,
                    'qty' => (int)($it['QuantityOrdered'] ?? 1),
                    'price' => (float)($it['ItemPrice']['Amount'] ?? 0),
                ], $items),
            ];
        }
    }

    public function acknowledgeOrder(string $externalId): void
    {
        // TODO: Implement acknowledgeOrder() method.
    }
}
