<?php

namespace App\Jobs\Feeds;

use App\Models\{FeedBatch, FeedBatchItem};
use App\Integrations\Amazon\{AmazonClient, AmazonAuthService, AmazonFeedsService};
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Queue\SerializesModels;

class AmazonProcessFeedResult implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $feedBatchId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $batch = FeedBatch::with('channel')->findOrFail($this->feedBatchId);
        if(!$batch->result_feed_document_id) return;


        $client = app()->makeWith(AmazonClient::class, ['channel' => $batch->channel, 'authSvc' => app(AmazonAuthService::class)]);
        $svc = app()->makeWith(AmazonFeedsService::class, ['client' => $client, 'channel' => $batch->channel]);


        $doc = $svc->getFeedDocument($batch->result_feed_document_id);
        $body = $svc->downloadReport($doc['url'], $doc['encryptionDetails'], $doc['compressionAlgorithm'] ?? null);

        // Try JSON first; fall back to TSV
        $parsed = json_decode($body, true);
        if (is_array($parsed)) {
            // Expected structure: results[] with sku, status, issues
            $results = $parsed['results'] ?? ($parsed['processingReport']['result'] ?? []);
            foreach ($results as $r) {
                $sku = $r['sku'] ?? ($r['additionalInfo']['sku'] ?? null);
                if(!$sku) continue;
                $item = $batch->items()->where('sku',$sku)->first();
                if($item) $item->update([
                    'result_code' => $r['status'] ?? ($r['code'] ?? null),
                    'result_message' => $r['message'] ?? ($r['description'] ?? null),
                    'result_json' => $r,
                ]);
            }
            $batch->update(['processed_count' => $batch->items()->whereNotNull('result_code')->count()]);
            return;
        }

        // TSV fallback (headers: resultCode\tmessage\tsku...)
        $lines = preg_split("/\r?\n/", trim($body));
        $headers = array_map('trim', str_getcsv(array_shift($lines), "\t"));
        foreach ($lines as $line) {
            if ($line==='') continue;
            $cols = str_getcsv($line, "\t");
            $row = array_combine($headers, $cols);
            $sku = $row['sku'] ?? $row['SKU'] ?? null; if(!$sku) continue;
            $item = $batch->items()->where('sku',$sku)->first();
            if($item) $item->update([
                'result_code' => $row['resultCode'] ?? $row['code'] ?? null,
                'result_message' => $row['message'] ?? $row['description'] ?? null,
                'result_json' => $row,
            ]);
        }
        $batch->update(['processed_count' => $batch->items()->whereNotNull('result_code')->count()]);
    }
}
