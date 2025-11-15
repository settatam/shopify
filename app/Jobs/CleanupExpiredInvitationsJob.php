<?php

namespace App\Jobs;

use App\Services\TeamInvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(TeamInvitationService $invitationService): void
    {
        $count = $invitationService->markExpiredInvitations();

        Log::info('Cleaned up expired team invitations', [
            'count' => $count,
        ]);
    }
}
