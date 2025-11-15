<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of days to keep notification logs.
     */
    protected int $retentionDays;

    /**
     * Create a new job instance.
     */
    public function __construct(int $retentionDays = 90)
    {
        $this->retentionDays = $retentionDays;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffDate = now()->subDays($this->retentionDays);

        $deleted = NotificationLog::where('created_at', '<', $cutoffDate)
            ->whereNotIn('status', ['queued', 'sending']) // Don't delete pending notifications
            ->delete();

        Log::info('Cleaned up old notification logs', [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoffDate->toDateString(),
            'deleted_count' => $deleted,
        ]);
    }
}
