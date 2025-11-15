<?php

namespace App\Integrations\Amazon;

use App\Models\{Channel, FeedBatch, FeedBatchItem};
use App\Support\Http\HttpFactory;

class AmazonFeedsService
{
    public function __construct(protected AmazonClient $client, protected Channel $channel) {}


    public function createFeedDocument(string $contentType = 'application/json; charset=UTF-8'): array
    {
        $r = $this->client->request('POST', '/feeds/2021-06-30/documents', [ 'json' => [ 'contentType' => $contentType ] ]);
        return json_decode($r->getBody(), true); // feedDocumentId, url, encryptionDetails
    }

    /** Encrypt (AES-256-CBC) and upload to S3 pre-signed URL */
    public function uploadEncrypted(string $url, string $contentType, string $keyB64, string $ivB64, string $plain): void
    {
        $key = base64_decode($keyB64); $iv = base64_decode($ivB64);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $http = HttpFactory::make([ 'headers' => [ 'Content-Type' => $contentType ] ]);
        $http->put($url, [ 'body' => $cipher ]);
    }

    public function createFeed(string $feedType, string $inputFeedDocumentId, array $marketplaceIds): array
    {
        $r = $this->client->request('POST', '/feeds/2021-06-30/feeds', [
            'json' => [ 'feedType' => $feedType, 'inputFeedDocumentId' => $inputFeedDocumentId, 'marketplaceIds' => $marketplaceIds ]
        ]);
        return json_decode($r->getBody(), true); // feedId
    }

    public function getFeed(string $feedId): array
    {
        $r = $this->client->request('GET', '/feeds/2021-06-30/feeds/'.$feedId);
        return json_decode($r->getBody(), true);
    }


    public function getFeedDocument(string $feedDocumentId): array
    {
        $r = $this->client->request('GET', '/feeds/2021-06-30/documents/'.$feedDocumentId);
        return json_decode($r->getBody(), true); // url, encryptionDetails, compressionAlgorithm?
    }

    /** Download, decrypt (and decompress if GZIP) */
    public function downloadReport(string $url, array $enc, ?string $compression = null): string
    {
        $raw = HttpFactory::make()->get($url)->getBody()->getContents();
        $key = base64_decode($enc['key']); $iv = base64_decode($enc['initializationVector']);
        $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if (strtoupper((string)$compression) === 'GZIP') { $plain = gzdecode($plain); }
        return $plain;
    }
}
