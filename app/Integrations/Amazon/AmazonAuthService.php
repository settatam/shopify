<?php

namespace App\Integrations\Amazon;

use App\Models\Channel;
use App\Support\Http\HttpFactory;

class AmazonAuthService
{
    public function refresh(Channel $channel): array
    {
        //auth_json will look something like:
//        {
//            "region": "us-east-1",
//            "host": "sellingpartnerapi-na.amazon.com",
//            "marketplace_ids": ["ATVPDKIKX0DER"],
//            "seller_id": "A1ABCDEF...",
//            "refresh_token": "Atzr|...",
//            "access_token": "", // filled by refresh
//            "access_token_expires_in": 0,
//            "currency": "USD",
//            "fulfillment": "MFN"
//        }
        $auth = $channel->auth_json;
        if (empty($auth['refresh_token'])) return $auth;


        $http = HttpFactory::make(['headers' => ['Content-Type' => 'application/x-www-form-urlencoded']]);
        $res = $http->post('https://api.amazon.com/auth/o2/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $auth['refresh_token'],
                'client_id' => config('services.amazon.lwa_client_id'),
                'client_secret' => config('services.amazon.lwa_client_secret'),
            ]
        ]);
        $data = json_decode($res->getBody(), true);
        $auth['access_token'] = $data['access_token'] ?? null;
        $auth['access_token_expires_in'] = $data['expires_in'] ?? null;
        $channel->update(['auth_json' => $auth]);
        return $auth;
    }
}
