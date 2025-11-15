<?php

namespace App\Jobs;

use App\Models\AutoRelistAction;
use App\Services\AutoRelistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAutoRelistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 3;
    public array $backoff = [60, 120, 300]; // 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AutoRelistAction $action
    ) {
        $this->onQueue('auto-relist');
    }

    /**
     * Execute the job.
     */
    public function handle(AutoRelistService $service): void
    {
        Log::info('Starting auto-relist action processing', [
            'action_id' => $this->action->id,
            'product_id' => $this->action->product_id,
            'channel_id' => $this->action->channel_id,
        ]);

        // Verify action is approved
        if ($this->action->status !== 'approved') {
            Log::warning('Action is not approved, skipping', [
                'action_id' => $this->action->id,
                'status' => $this->action->status,
            ]);
            return;
        }

        // Check if scheduled relist time has arrived
        if ($this->action->relist_scheduled_at && $this->action->relist_scheduled_at->isFuture()) {
            Log::info('Relist time not yet reached, rescheduling', [
                'action_id' => $this->action->id,
                'scheduled_at' => $this->action->relist_scheduled_at->toDateTimeString(),
            ]);

            static::dispatch($this->action)
                ->delay($this->action->relist_scheduled_at);

            return;
        }

        try {
            // Update status to processing
            $this->action->update(['status' => 'processing']);

            // Execute the relist
            $success = $service->relistProduct($this->action);

            if ($success) {
                $this->action->markRelisted();

                Log::info('Auto-relist action completed successfully', [
                    'action_id' => $this->action->id,
                    'product_id' => $this->action->product_id,
                    'channel_id' => $this->action->channel_id,
                ]);

                // Schedule performance monitoring
                MonitorRelistPerformanceJob::dispatch($this->action)
                    ->delay(now()->addDays(7)); // Check after 7 days
            } else {
                $this->action->markFailed('Relist operation failed');

                Log::error('Auto-relist action failed', [
                    'action_id' => $this->action->id,
                    'product_id' => $this->action->product_id,
                ]);
            }
        } catch (\Exception $e) {
            $this->action->markFailed($e->getMessage());

            Log::error('Auto-relist action processing failed', [
                'action_id' => $this->action->id,
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
        $this->action->markFailed('Job failed after all retries: ' . $exception->getMessage());

        Log::error('Auto-relist action processing failed permanently', [
            'action_id' => $this->action->id,
            'error' => $exception->getMessage(),
        ]);

        // Optionally notify shop owner
        // Could send notification here
    }
}
