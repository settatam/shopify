<?php

namespace App\Jobs\Feeds;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\{Channel, FeedBatch, FeedBatchItem, ProductVariant};
use App\Integrations\Amazon\{AmazonClient, AmazonAuthService, AmazonFeedsService, AmazonListingsFeedBuilder};

class AmazonBuildAndSubmitJsonListingsFeed implements ShouldQueue
{
    use Queueable;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $channelId, public array $variantIds) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $channel = Channel::findOrFail($this->channelId);
        $client = app()->makeWith(AmazonClient::class, ['channel' => $channel, 'authSvc' => app(AmazonAuthService::class)]);
        $svc = app()->makeWith(AmazonFeedsService::class, ['client' => $client, 'channel' => $channel]);


        $variants = ProductVariant::whereIn('id', $this->variantIds)->get();
        $payload = AmazonListingsFeedBuilder::buildRequests($channel, $variants->all(), null);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);


        $batch = FeedBatch::create([
            'channel_id' => $channel->id,
            'feed_type' => 'JSON_LISTINGS_FEED',
            'marketplace_ids' => $channel->auth_json['marketplace_ids'] ?? [],
            'content_type' => 'application/json; charset=UTF-8',
            'status' => 'NEW',
            'submitted_count' => count($payload['requests'] ?? [])
        ]);
        foreach ($variants as $v) {
            $sku = $v->sku ?: (string)$v->shopify_variant_id;
            FeedBatchItem::create(['feed_batch_id' => $batch->id, 'sku' => $sku, 'operation' => 'price+qty', 'payload_json' => ['variant_id'=>$v->id]]);
        }


// Create doc, upload, submit
        $doc = $svc->createFeedDocument($batch->content_type);
        $svc->uploadEncrypted($doc['url'], $batch->content_type, $doc['encryptionDetails']['key'], $doc['encryptionDetails']['initializationVector'], $json);
        $batch->update(['feed_document_id' => $doc['feedDocumentId'], 'status' => 'UPLOADED']);


        $feed = $svc->createFeed($batch->feed_type, $doc['feedDocumentId'], $batch->marketplace_ids);
        $batch->update(['feed_id' => $feed['feedId'] ?? null, 'status' => 'SUBMITTED']);


        AmazonPollFeedStatus::dispatch($batch->id)->delay(now()->addSeconds(20));
    }
}
