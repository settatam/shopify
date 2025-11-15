<?php

namespace App\Jobs\Feeds;

use App\Integrations\Amazon\{AmazonAuthService, AmazonClient, AmazonFeedsService};
use App\Models\{Channel, FeedBatch};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AmazonSubmitXmlFeed implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $channelId, public string $feedType, public string $xml) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $channel = Channel::findOrFail($this->channelId);
        $client = app()->makeWith(AmazonClient::class, ['channel'=>$channel, 'authSvc'=>app(AmazonAuthService::class)]);
        $svc = app()->makeWith(AmazonFeedsService::class, ['client'=>$client, 'channel'=>$channel]);


        $batch = FeedBatch::create([
            'channel_id' => $channel->id,
            'feed_type' => $this->feedType,
            'marketplace_ids' => $channel->auth_json['marketplace_ids'] ?? [],
            'content_type' => 'text/xml; charset=UTF-8',
            'status' => 'NEW',
            'submitted_count' => substr_count($this->xml, '<Message>')
        ]);


        $doc = $svc->createFeedDocument($batch->content_type);
        $svc->uploadEncrypted($doc['url'], $batch->content_type, $doc['encryptionDetails']['key'], $doc['encryptionDetails']['initializationVector'], $this->xml);
        $batch->update(['feed_document_id'=>$doc['feedDocumentId'], 'status'=>'UPLOADED']);


        $feed = $svc->createFeed($this->feedType, $doc['feedDocumentId'], $batch->marketplace_ids);
        $batch->update(['feed_id'=>$feed['feedId'] ?? null, 'status'=>'SUBMITTED']);


        AmazonPollFeedStatus::dispatch($batch->id)->delay(now()->addSeconds(20));
    }
}
