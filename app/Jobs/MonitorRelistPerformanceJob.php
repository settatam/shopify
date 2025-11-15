<?php

namespace App\Jobs;

use App\Models\AutoRelistAction;
use App\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorRelistPerformanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public array $backoff = [60, 120, 300]; // 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AutoRelistAction $action,
        public int $daysToMonitor = 7
    ) {
        $this->onQueue('analytics');
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analytics): void
    {
        Log::info('Starting auto-relist performance monitoring', [
            'action_id' => $this->action->id,
            'product_id' => $this->action->product_id,
            'channel_id' => $this->action->channel_id,
            'days_to_monitor' => $this->daysToMonitor,
        ]);

        // Only monitor completed relists
        if ($this->action->status !== 'completed') {
            Log::info('Action is not completed, skipping monitoring', [
                'action_id' => $this->action->id,
                'status' => $this->action->status,
            ]);
            return;
        }

        // Ensure we have a relisted_at timestamp
        if (!$this->action->relisted_at) {
            Log::warning('No relisted_at timestamp, cannot monitor', [
                'action_id' => $this->action->id,
            ]);
            return;
        }

        try {
            $product = $this->action->product;
            $channel = $this->action->channel;

            // Get performance metrics after relist
            $metrics = $analytics->getProductChannelMetrics(
                $product,
                $channel,
                $this->action->relisted_at,
                now()
            );

            // Update performance data
            $this->action->update([
                'views_after' => $metrics['views'] ?? 0,
                'sales_after' => $metrics['sales'] ?? 0,
                'conversion_rate_after' => $metrics['conversion_rate'] ?? 0.0,
            ]);

            Log::info('Auto-relist performance metrics updated', [
                'action_id' => $this->action->id,
                'views_before' => $this->action->views_before,
                'views_after' => $this->action->views_after,
                'sales_before' => $this->action->sales_before,
                'sales_after' => $this->action->sales_after,
                'improvement' => $this->action->getImprovementPercentage(),
            ]);

            // Continue monitoring if configured
            if ($this->shouldContinueMonitoring()) {
                static::dispatch($this->action, $this->daysToMonitor)
                    ->delay(now()->addDays($this->daysToMonitor));

                Log::info('Scheduled continued performance monitoring', [
                    'action_id' => $this->action->id,
                    'next_check' => now()->addDays($this->daysToMonitor)->toDateTimeString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Auto-relist performance monitoring failed', [
                'action_id' => $this->action->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Determine if we should continue monitoring this action.
     */
    protected function shouldContinueMonitoring(): bool
    {
        // Stop monitoring after 30 days
        if ($this->action->relisted_at->diffInDays(now()) >= 30) {
            return false;
        }

        // Stop if we have clear results (sales or significant views)
        if ($this->action->sales_after > 0 || $this->action->views_after > 100) {
            return false;
        }

        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Auto-relist performance monitoring failed permanently', [
            'action_id' => $this->action->id,
            'error' => $exception->getMessage(),
        ]);

        // Don't block the relist action, just log the monitoring failure
    }
}
