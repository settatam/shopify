<?php

namespace App\Jobs\AI;

use App\Models\Channel;
use App\Services\AI\AICategoryMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateCategoryMappingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // Don't retry AI calls
    public $timeout = 600; // 10 minutes for batch

    protected array $productIds;
    protected Channel $channel;
    protected array $categories;

    /**
     * Create a new job instance.
     */
    public function __construct(array $productIds, Channel $channel, array $categories)
    {
        $this->productIds = $productIds;
        $this->channel = $channel;
        $this->categories = $categories;
    }

    /**
     * Execute the job.
     */
    public function handle(AICategoryMapper $mapper): void
    {
        try {
            Log::info('Starting batch category mapping generation', [
                'channel_id' => $this->channel->id,
                'channel_type' => $this->channel->channel_type,
                'product_count' => count($this->productIds),
            ]);

            $results = $mapper->batchMapProducts(
                $this->productIds,
                $this->channel,
                $this->categories
            );

            $successCount = collect($results)->where('success', true)->count();
            $failureCount = collect($results)->where('success', false)->count();

            Log::info('Batch category mapping generation completed', [
                'channel_id' => $this->channel->id,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Batch category mapping generation failed', [
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
