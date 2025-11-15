<?php

namespace App\Integrations\Contracts;

use App\Models\{Product, ProductVariant, ChannelCategory};
use DateTimeInterface;

class SyncResult {
    public function __construct(
        public bool $ok,
        public ?string $externalId = null,
        public array $errors = [],
        public array $meta = [],
    ) {}
}

interface ChannelAdapter
{
    public function boot(array $auth, array $options = []): static; // set tokens, marketplace, etc.


    /** @return iterable<ChannelCategory> */
    public function getCategories(): iterable; // store/update ChannelCategory


    public function upsertProduct(Product $p, array $mapping): SyncResult;
    public function upsertVariant(ProductVariant $v, array $mapping): SyncResult;


    public function updateInventory(string $sku, int $qty): SyncResult;
    public function updatePrice(string $sku, float $price): SyncResult;


    /** @return iterable<array> normalized orders */
    public function fetchOrders(DateTimeInterface $since): iterable;
    public function acknowledgeOrder(string $externalId): void;
}
