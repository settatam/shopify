<?php

namespace App\Integrations\ChannelAdapters;

use App\Integrations\Contracts\ChannelAdapter;
use App\Integrations\Contracts\SyncResult;
use App\Models\Product;
use App\Models\ProductVariant;
use DateTimeInterface;

class WalmartAdapter implements ChannelAdapter
{

    public function boot(array $auth, array $options = []): static
    {
        // TODO: Implement boot() method.
    }

    /**
     * @inheritDoc
     */
    public function getCategories(): iterable
    {
        // TODO: Implement getCategories() method.
    }

    public function upsertProduct(Product $p, array $mapping): SyncResult
    {
        // TODO: Implement upsertProduct() method.
    }

    public function upsertVariant(ProductVariant $v, array $mapping): SyncResult
    {
        // TODO: Implement upsertVariant() method.
    }

    public function updateInventory(string $sku, int $qty): SyncResult
    {
        // TODO: Implement updateInventory() method.
    }

    public function updatePrice(string $sku, float $price): SyncResult
    {
        // TODO: Implement updatePrice() method.
    }

    /**
     * @inheritDoc
     */
    public function fetchOrders(DateTimeInterface $since): iterable
    {
        // TODO: Implement fetchOrders() method.
    }

    public function acknowledgeOrder(string $externalId): void
    {
        // TODO: Implement acknowledgeOrder() method.
    }
}
