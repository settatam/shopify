<?php

namespace App\Jobs\Feeds;

use App\Models\{FeedBatch, Channel, FeedBatchItem};
use App\Integrations\Amazon\{AmazonClient, AmazonAuthService, AmazonFeedsService};
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels;

class AmazonPollFeedStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $feedBatchId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batch = FeedBatch::findOrFail($this->feedBatchId);
        $channel = $batch->channel; if(!$channel || !$batch->feed_id) return;


        $client = app()->makeWith(AmazonClient::class, ['channel' => $channel, 'authSvc' => app(AmazonAuthService::class)]);
        $svc = app()->makeWith(AmazonFeedsService::class, ['client' => $client, 'channel' => $channel]);


        $resp = $svc->getFeed($batch->feed_id);
        $status = $resp['processingStatus'] ?? 'UNKNOWN';
        if (in_array($status, ['IN_QUEUE','IN_PROGRESS','CANCELLED'])) {
            $batch->update(['status' => $status]);
            if ($status !== 'CANCELLED') self::dispatch($batch->id)->delay(now()->addSeconds(30));
            return;
        }
        if ($status === 'DONE') {
            $docId = $resp['resultFeedDocumentId'] ?? null;
            $batch->update(['status' => 'DONE', 'result_feed_document_id' => $docId]);
            if ($docId) AmazonProcessFeedResult::dispatch($batch->id);
            return;
        }
        $batch->update(['status' => 'ERROR', 'error' => json_encode($resp)]);
    }
}
