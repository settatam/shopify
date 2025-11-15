<?php

namespace App\Integrations\Amazon;

use App\Models\Channel;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use App\Support\Http\HttpFactory;
use Throwable;

class AmazonClient
{
    protected Client $http; protected SignatureV4 $signer; protected Credentials $creds;


    public function __construct(protected Channel $channel, protected AmazonAuthService $authSvc) {
        $this->http = HttpFactory::make();
        $this->signer = new SignatureV4('execute-api', $this->region());
        $this->creds = new Credentials(config('services.amazon.aws_key'), config('services.amazon.aws_secret'));
    }

    protected function region(): string { return $this->channel->auth_json['region'] ?? config('services.amazon.region'); }
    protected function host(): string { return $this->channel->auth_json['host'] ?? config('services.amazon.host_na'); }

    /** @param array $options ['query'=>[], 'json'=>..., 'headers'=>[]] */
    public function request(string $method, string $path, array $options = [])
    {
        $auth = $this->channel->auth_json;
        if (empty($auth['access_token'])) { $auth = $this->authSvc->refresh($this->channel); }
        $headers = array_merge(['host' => $this->host(), 'x-amz-access-token' => $auth['access_token']], $options['headers'] ?? []);


        $uri = 'https://'.$this->host().$path;
        if (!empty($options['query'])) { $qs = http_build_query($options['query']); $uri .= '?'.$qs; }
        $body = isset($options['json']) ? json_encode($options['json']) : ($options['body'] ?? null);
        if ($body !== null) $headers['content-type'] = 'application/json';


        $req = new Request($method, $uri, $headers, $body);
        $signed = $this->signer->signRequest($req, $this->creds);


        try { return $this->http->send($signed); }
        catch (Throwable $e) {
// If token expired, refresh once and retry
            $auth = $this->authSvc->refresh($this->channel);
            $headers['x-amz-access-token'] = $auth['access_token'] ?? '';
            $req = new Request($method, $uri, $headers, $body);
            $signed = $this->signer->signRequest($req, $this->creds);
            return $this->http->send($signed);
        }
    }
}
