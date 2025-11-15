<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelStateController extends Controller
{
    //
    public function show(Channel $channel)
    {
        $auth = $channel->auth_json ?? [];
        $pol = $auth['listing_policies'] ?? [];
        $loc = $channel->hasMany(\App\Models\ChannelLocation::class)->where('is_default', true)->first();
        return response()->json([
            'marketplace_id' => $auth['marketplace_id'] ?? 'EBAY_US',
            'currency' => $auth['currency'] ?? 'USD',
            'policies' => [
                'has_all' => isset($pol['fulfillment_policy_id'],$pol['payment_policy_id'],$pol['return_policy_id']),
                'fulfillment_policy_id' => $pol['fulfillment_policy_id'] ?? null,
                'payment_policy_id' => $pol['payment_policy_id'] ?? null,
                'return_policy_id' => $pol['return_policy_id'] ?? null,
            ],
            'location' => [
                'has_default' => (bool) $loc,
                'default' => $loc?->toArray()
            ]
        ]);
    }
}
