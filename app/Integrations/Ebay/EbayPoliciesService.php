<?php

namespace App\Integrations\Ebay;

class EbayPoliciesService
{
    public function __construct(protected EbayClient $client) {}

    public function listFulfillmentPolicies(string $marketplaceId): array
    {
        $r = $this->client->request('GET', '/sell/account/v1/fulfillment_policy', ['query' => ['marketplace_id' => $marketplaceId]]);
        return json_decode($r->getBody(), true)['fulfillmentPolicies'] ?? [];
    }


    public function listPaymentPolicies(string $marketplaceId): array
    {
        $r = $this->client->request('GET', '/sell/account/v1/payment_policy', ['query' => ['marketplace_id' => $marketplaceId]]);
        return json_decode($r->getBody(), true)['paymentPolicies'] ?? [];
    }


    public function listReturnPolicies(string $marketplaceId): array
    {
        $r = $this->client->request('GET', '/sell/account/v1/return_policy', ['query' => ['marketplace_id' => $marketplaceId]]);
        return json_decode($r->getBody(), true)['returnPolicies'] ?? [];
    }
}
