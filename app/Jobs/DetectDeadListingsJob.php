<?php

namespace App\Jobs;

use App\Models\AutoRelistCampaign;
use App\Services\AutoRelistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectDeadListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    public array $backoff = [60, 120, 300]; // 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AutoRelistCampaign $campaign
    ) {
        $this->onQueue('auto-relist');
    }

    /**
     * Execute the job.
     */
    public function handle(AutoRelistService $service): void
    {
        Log::info('Starting auto-relist campaign detection', [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
        ]);

        // Check if campaign is still active
        if (!$this->campaign->is_active) {
            Log::info('Campaign is inactive, skipping', ['campaign_id' => $this->campaign->id]);
            return;
        }

        // Check if campaign can run more today
        if (!$this->campaign->canRelistMoreToday()) {
            Log::info('Campaign has reached daily limit', [
                'campaign_id' => $this->campaign->id,
                'max_relists_per_day' => $this->campaign->max_relists_per_day,
            ]);
            return;
        }

        try {
            $results = $service->runCampaign($this->campaign);

            Log::info('Auto-relist campaign detection completed', [
                'campaign_id' => $this->campaign->id,
                'results' => $results,
            ]);

            // Schedule next run if recurring
            if ($this->campaign->is_active && $this->campaign->check_frequency_hours > 0) {
                $nextRun = now()->addHours($this->campaign->check_frequency_hours);

                static::dispatch($this->campaign)
                    ->delay($nextRun);

                Log::info('Scheduled next campaign run', [
                    'campaign_id' => $this->campaign->id,
                    'next_run' => $nextRun->toDateTimeString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Auto-relist campaign detection failed', [
                'campaign_id' => $this->campaign->id,
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
        Log::error('Auto-relist campaign detection failed permanently', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify shop owner
        // Could send notification here
    }
}
