<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class EbayPoliciesController extends Controller
{
    //
    protected function polSvc(Channel $channel): EbayPoliciesService
    {
        $auth = app(EbayAuthService::class);
        $client = app()->makeWith(EbayClient::class, ['channel' => $channel, 'auth' => $auth]);
        return app()->makeWith(EbayPoliciesService::class, ['client' => $client]);
    }


    public function list(Channel $channel)
    {
        $market = $channel->auth_json['marketplace_id'] ?? 'EBAY_US';
        $svc = $this->polSvc($channel);
        return response()->json([
            'fulfillment' => $svc->listFulfillmentPolicies($market),
            'payment' => $svc->listPaymentPolicies($market),
            'return' => $svc->listReturnPolicies($market),
        ]);
    }

    public function saveSelection(Request $r, Channel $channel)
    {
        $data = $r->validate([
            'marketplace_id' => 'required|string',
            'fulfillment_policy_id' => 'required|string',
            'payment_policy_id' => 'required|string',
            'return_policy_id' => 'required|string',
        ]);
        $auth = $channel->auth_json;
        $auth['marketplace_id'] = $data['marketplace_id'];
        $auth['listing_policies'] = $data;
        $channel->update(['auth_json' => $auth]);
        return response()->json(['ok' => true]);
    }

    public function upsertLocation(Request $r, Channel $channel)
    {
        $data = $r->validate([
            'merchant_location_key' => 'required|string',
            'name' => 'nullable|string',
            'address_json' => 'required|array',
            'is_default' => 'boolean'
        ]);
        if (!empty($data['is_default'])) {
            $channel->locations()->update(['is_default' => false]);
        }
        $loc = $channel->locations()->updateOrCreate(
            ['merchant_location_key' => $data['merchant_location_key']],
            [
                'name' => $data['name'] ?? $data['merchant_location_key'],
                'address_json' => $data['address_json'],
                'is_default' => (bool)($data['is_default'] ?? false)
            ]
        );
// Push to eBay
        $invSvc = app()->makeWith(\App\Integrations\Ebay\EbayInventoryService::class, ['client' => app()->makeWith(\App\Integrations\Ebay\EbayClient::class, ['channel' => $channel, 'auth' => app(\App\Integrations\Ebay\EbayAuthService::class)])]);
        $invSvc->putLocation($channel, $loc);
        return response()->json($loc);
    }
}
