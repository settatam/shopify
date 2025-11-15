<?php

namespace App\Jobs;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyReturnStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // 2 minutes
    public int $tries = 3;
    public array $backoff = [60, 120, 300]; // 1min, 2min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReturnRequest $returnRequest,
        public string $event
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Sending return status notification', [
            'rma_number' => $this->returnRequest->rma_number,
            'event' => $this->event,
            'customer_email' => $this->returnRequest->customer_email,
        ]);

        try {
            $this->sendNotification();

            // Update notification history
            $history = $this->returnRequest->notification_history ?? [];
            $history[] = [
                'event' => $this->event,
                'sent_at' => now()->toIso8601String(),
                'email' => $this->returnRequest->customer_email,
            ];

            $this->returnRequest->update([
                'notification_history' => $history,
                'customer_notified_at' => now(),
            ]);

            Log::info('Return status notification sent', [
                'rma_number' => $this->returnRequest->rma_number,
                'event' => $this->event,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send return notification', [
                'rma_number' => $this->returnRequest->rma_number,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send the notification email.
     */
    protected function sendNotification(): void
    {
        $subject = $this->getSubject();
        $message = $this->getMessage();

        // This would integrate with your actual email service
        // For now, just log it
        Log::info('Email notification', [
            'to' => $this->returnRequest->customer_email,
            'subject' => $subject,
            'message' => $message,
        ]);

        // Example Laravel Mail usage:
        // Mail::to($this->returnRequest->customer_email)
        //     ->send(new ReturnStatusMail($this->returnRequest, $this->event));
    }

    /**
     * Get email subject based on event.
     */
    protected function getSubject(): string
    {
        return match($this->event) {
            'created' => "Return Request Received - RMA #{$this->returnRequest->rma_number}",
            'approved' => "Return Request Approved - RMA #{$this->returnRequest->rma_number}",
            'rejected' => "Return Request Update - RMA #{$this->returnRequest->rma_number}",
            'label_generated' => "Return Shipping Label Ready - RMA #{$this->returnRequest->rma_number}",
            'inspection_completed' => "Return Inspection Complete - RMA #{$this->returnRequest->rma_number}",
            'refund_processed' => "Refund Processed - RMA #{$this->returnRequest->rma_number}",
            'completed' => "Return Completed - RMA #{$this->returnRequest->rma_number}",
            default => "Return Update - RMA #{$this->returnRequest->rma_number}",
        };
    }

    /**
     * Get email message based on event.
     */
    protected function getMessage(): string
    {
        return match($this->event) {
            'created' => "We've received your return request for order #{$this->returnRequest->order_number}. Your RMA number is {$this->returnRequest->rma_number}. We'll review your request and get back to you soon.",

            'approved' => "Great news! Your return request has been approved. We're preparing your return shipping label. You'll receive it shortly.",

            'rejected' => "Unfortunately, we're unable to approve your return request for the following reason: {$this->returnRequest->rejection_reason}. If you have questions, please contact our support team.",

            'label_generated' => "Your return shipping label is ready! Please print it and attach it to your package. Tracking: {$this->returnRequest->return_tracking_number}",

            'inspection_completed' => "We've received and inspected your returned items. Your refund will be processed shortly.",

            'refund_processed' => "Your refund of \${$this->returnRequest->refund_amount} has been processed. It should appear in your account within 5-10 business days.",

            'completed' => "Your return has been completed. Thank you for your business!",

            default => "Your return status has been updated.",
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Return notification failed permanently', [
            'rma_number' => $this->returnRequest->rma_number,
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
