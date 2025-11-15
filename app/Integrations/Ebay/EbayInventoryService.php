<?php

namespace App\Integrations\Ebay;
use App\Models\{Channel, ChannelLocation};


class EbayInventoryService
{
    public function __construct(protected EbayClient $client) {}


    public function putLocation(Channel $channel, ChannelLocation $loc): void
    {
        $payload = [
            'name' => $loc->name ?: $loc->merchant_location_key,
            'merchantLocationKey' => $loc->merchant_location_key,
            'location' => $loc->address_json,
            'locationTypes' => ['WAREHOUSE'],
            'merchantLocationStatus' => 'ENABLED'
        ];
        $this->client->request('PUT', '/sell/inventory/v1/location/'.$loc->merchant_location_key, ['json' => $payload]);
    }
}
