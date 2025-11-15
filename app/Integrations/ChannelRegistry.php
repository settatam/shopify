<?php

namespace App\Integrations;

use App\Integrations\Contracts\ChannelAdapter;
use App\Integrations\Contracts\ChannelAdapters;
use App\Models\Channel;

class ChannelRegistry
{
    public function __construct(
        protected \App\Integrations\ChannelAdapters\EbayAdapter    $ebay,
        protected \App\Integrations\ChannelAdapters\AmazonAdapter  $amazon,
        protected \App\Integrations\ChannelAdapters\EtsyAdapter    $etsy,
        protected \App\Integrations\ChannelAdapters\WalmartAdapter $walmart,
    ) {}


    public function for(Channel $c): ChannelAdapter
    {
//        $adapter = match ($c->type) {
//            'ebay' => $this->ebay,
//            'amazon' => $this->amazon,
//            'etsy' => $this->etsy,
//            'walmart' => $this->walmart,
//        };
//        return $adapter->boot($c->auth_json, ['sandbox' => $c->sandbox]);

        $adapter = match ($c->type) {
            'ebay' => app(\App\Integrations\ChannelAdapters\EbayAdapter::class)->withChannel($c),
            // ... others
            };
            return $adapter;
    }

}
