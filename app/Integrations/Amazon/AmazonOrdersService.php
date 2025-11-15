<?php

namespace App\Integrations\Amazon;

use App\Models\Channel;
use DateTimeInterface;

class AmazonOrdersService
{
    public function __construct(protected AmazonClient $client, protected Channel $channel) {}


    protected function mids(): array { return $this->channel->auth_json['marketplace_ids'] ?? []; }


    public function listOrders(DateTimeInterface $since): array
    {
        $r = $this->client->request('GET', '/orders/v0/orders', [
            'query' => [
                'MarketplaceIds' => implode(',', $this->mids()),
                'CreatedAfter' => $since->format('c'),
                'OrderStatuses' => 'Unshipped,PartiallyShipped,Shipped'
            ]
        ]);
        return json_decode($r->getBody(), true)['Orders'] ?? [];
    }

    public function listOrderItems(string $amazonOrderId): array
    {
        $r = $this->client->request('GET', '/orders/v0/orders/'.$amazonOrderId.'/orderItems');
        return json_decode($r->getBody(), true)['OrderItems'] ?? [];
    }

}
