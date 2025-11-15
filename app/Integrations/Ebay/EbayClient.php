<?php

namespace App\Integrations\Ebay;

use App\Models\Channel;
use App\Support\Http\HttpFactory;
use GuzzleHttp\Client;
use Throwable;

class EbayClient
{
    public function __construct(protected Channel $channel, protected EbayAuthService $auth) {}


    public function base(): string { return ($this->channel->sandbox ?? true) ? 'https://api.sandbox.ebay.com' : 'https://api.ebay.com'; }


    protected function client(?string $token = null): Client
    {
        $auth = $this->channel->auth_json;
        $token = $token ?: ($auth['access_token'] ?? '');
        return HttpFactory::make(['headers' => [ 'Authorization' => 'Bearer '.$token, 'Content-Type' => 'application/json' ]]);
    }


    public function request(string $method, string $path, array $options = [])
    {
        $http = $this->client();
        try {
            return $http->request($method, $this->base().$path, $options);
        } catch (Throwable $e) {
// If 401, try one refresh then retry once
            if (method_exists($e, 'getResponse') && $e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                $auth = $this->auth->refresh($this->channel);
                $http = $this->client($auth['access_token'] ?? null);
                return $http->request($method, $this->base().$path, $options);
            }
            throw $e;
        }
    }
}
