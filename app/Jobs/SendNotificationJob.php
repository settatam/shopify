<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Mail\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
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
        public NotificationLog $log
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if notification should be retried later
        if ($this->log->next_retry_at && $this->log->next_retry_at->isFuture()) {
            static::dispatch($this->log)->delay($this->log->next_retry_at);
            return;
        }

        // Mark as sending
        $this->log->markAsSending();

        try {
            // Send based on channel
            match ($this->log->channel) {
                'email' => $this->sendEmail(),
                'sms' => $this->sendSms(),
                'push' => $this->sendPush(),
                'in_app' => $this->sendInApp(),
                default => throw new \Exception('Unsupported notification channel: ' . $this->log->channel),
            };

            Log::info('Notification sent successfully', [
                'notification_id' => $this->log->id,
                'event_type' => $this->log->event_type,
                'channel' => $this->log->channel,
                'recipient' => $this->log->recipient_email ?? $this->log->recipient_phone,
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(): void
    {
        if (!$this->log->recipient_email) {
            throw new \Exception('Recipient email is required for email notifications');
        }

        $template = $this->log->template;

        // Send email
        Mail::to($this->log->recipient_email)
            ->send(new NotificationMail($this->log, $template));

        // Get message ID from sent email (if available)
        $messageId = null; // Laravel doesn't provide easy access to message ID

        // Mark as sent
        $this->log->markAsSent($messageId);

        // Set provider based on mail driver
        $this->log->update([
            'provider' => config('mail.default'),
        ]);
    }

    /**
     * Send SMS notification.
     */
    protected function sendSms(): void
    {
        // TODO: Implement SMS sending via Twilio, SNS, or other provider
        // For now, just mark as sent
        Log::warning('SMS notifications not yet implemented', [
            'notification_id' => $this->log->id,
        ]);

        throw new \Exception('SMS notifications not yet implemented');
    }

    /**
     * Send push notification.
     */
    protected function sendPush(): void
    {
        // TODO: Implement push notifications via FCM, APNS, or other provider
        Log::warning('Push notifications not yet implemented', [
            'notification_id' => $this->log->id,
        ]);

        throw new \Exception('Push notifications not yet implemented');
    }

    /**
     * Send in-app notification.
     */
    protected function sendInApp(): void
    {
        // For in-app notifications, just mark as sent
        // The notification will be displayed in the app UI
        $this->log->markAsSent();
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(\Exception $e): void
    {
        Log::error('Notification failed', [
            'notification_id' => $this->log->id,
            'event_type' => $this->log->event_type,
            'error' => $e->getMessage(),
            'retry_count' => $this->log->retry_count,
        ]);

        // Check if we should retry
        if ($this->log->canRetry($this->tries)) {
            // Calculate exponential backoff delay
            $delay = min(pow(2, $this->log->retry_count) * 5, 60); // Max 60 minutes
            $this->log->scheduleRetry($delay);

            Log::info('Notification scheduled for retry', [
                'notification_id' => $this->log->id,
                'retry_count' => $this->log->retry_count,
                'next_retry_at' => $this->log->next_retry_at,
            ]);
        } else {
            // Max retries exceeded, mark as failed permanently
            $this->log->markAsFailed($e->getMessage());

            Log::error('Notification permanently failed after max retries', [
                'notification_id' => $this->log->id,
                'retry_count' => $this->log->retry_count,
            ]);
        }

        // Re-throw to let Laravel handle job failure
        throw $e;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // This is called after all retries have been exhausted
        Log::error('Notification job failed permanently', [
            'notification_id' => $this->log->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->log->markAsFailed($exception->getMessage());
    }
}
