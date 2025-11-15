<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use App\Models\SmartPublishReport;
use App\Services\SmartPublishService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmartPublishJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 minutes
    public int $tries = 1; // Only try once - we track failures in the report
    public array $backoff = [];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Product $product,
        public array $channelIds,
        public User $user,
        public array $options = []
    ) {
        $this->onQueue('smart-publish');
    }

    /**
     * Execute the job.
     */
    public function handle(SmartPublishService $service): void
    {
        Log::info('Starting smart publish', [
            'product_id' => $this->product->id,
            'channels' => count($this->channelIds),
            'user_id' => $this->user->id,
        ]);

        try {
            $report = $service->publish(
                $this->product,
                $this->channelIds,
                $this->user,
                $this->options
            );

            Log::info('Smart publish completed', [
                'product_id' => $this->product->id,
                'report_id' => $report->id,
                'status' => $report->status,
                'channels_succeeded' => $report->channels_succeeded,
                'channels_failed' => $report->channels_failed,
            ]);
        } catch (\Exception $e) {
            Log::error('Smart publish job failed', [
                'product_id' => $this->product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Smart publish job failed permanently', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);

        // Try to create a failed report if one doesn't exist
        try {
            $existingReport = SmartPublishReport::where('product_id', $this->product->id)
                ->where('user_id', $this->user->id)
                ->where('status', 'processing')
                ->latest()
                ->first();

            if ($existingReport) {
                $existingReport->markFailed($exception->getMessage());
            } else {
                SmartPublishReport::create([
                    'product_id' => $this->product->id,
                    'user_id' => $this->user->id,
                    'selected_channels' => $this->channelIds,
                    'status' => 'failed',
                    'failure_reason' => $exception->getMessage(),
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create failure report', ['error' => $e->getMessage()]);
        }
    }
}
