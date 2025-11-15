<?php

namespace App\Jobs;

use App\Models\TeamInvitation;
use App\Mail\TeamInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTeamInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public TeamInvitation $invitation
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if invitation is still valid
        if ($invitation->status !== 'pending') {
            Log::info('Skipping invitation email - not pending', [
                'invitation_id' => $this->invitation->id,
                'status' => $this->invitation->status,
            ]);
            return;
        }

        try {
            Mail::to($this->invitation->email)
                ->send(new TeamInvitationMail($this->invitation));

            Log::info('Team invitation email sent', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send team invitation email', [
                'invitation_id' => $this->invitation->id,
                'email' => $this->invitation->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Team invitation email job failed permanently', [
            'invitation_id' => $this->invitation->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
