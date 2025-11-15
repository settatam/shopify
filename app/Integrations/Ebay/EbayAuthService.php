<?php

namespace App\Integrations\Ebay;

use App\Models\Channel;
use App\Support\Http\HttpFactory;

class EbayAuthService
{
    public function __construct(protected string $clientId, protected string $clientSecret) {}


    protected function tokenEndpoint(Channel $ch): string { return 'https://api.ebay.com/identity/v1/oauth2/token'; }


    public function refresh(Channel $channel): array
    {
        $auth = $channel->auth_json;
        if (empty($auth['refresh_token'])) return $auth; // app grant or already valid


        $http = HttpFactory::make([ 'auth' => [$this->clientId, $this->clientSecret] ]);
        $res = $http->post($this->tokenEndpoint($channel), [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $auth['refresh_token'],
                'scope' => implode(' ', $auth['scopes'] ?? []),
            ]
        ]);
        $data = json_decode($res->getBody(), true);
        $auth['access_token'] = $data['access_token'] ?? $auth['access_token'] ?? null;
        $auth['access_token_expires_in'] = $data['expires_in'] ?? null;
        $channel->update(['auth_json' => $auth]);
        return $auth;
    }
}
