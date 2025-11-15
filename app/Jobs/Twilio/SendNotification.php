<?php

namespace App\Jobs\Twilio;

use App\Models\Shop;
use App\Models\NotificationLog;
use App\Services\Twilio\TwilioClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120]; // Retry after 30s, 60s, 120s

    public function __construct(
        public Shop $shop,
        public string $to,
        public string $message,
        public string $channel = 'sms',
        public ?string $templateKey = null,
        public ?array $templateVariables = null,
        public ?string $relatedType = null,
        public ?int $relatedId = null
    ) {}

    public function handle(): void
    {
        try {
            $client = new TwilioClient($this->shop);

            // Check if configured
            if ($this->channel === 'whatsapp' && !$client->isWhatsAppConfigured()) {
                throw new \Exception('WhatsApp is not configured for this shop');
            } elseif ($this->channel === 'sms' && !$client->isConfigured()) {
                throw new \Exception('Twilio SMS is not configured for this shop');
            }

            // Create log entry
            $log = NotificationLog::create([
                'shop_id' => $this->shop->id,
                'channel' => $this->channel,
                'to' => $this->to,
                'message' => $this->message,
                'template_key' => $this->templateKey,
                'template_variables' => $this->templateVariables,
                'status' => 'queued',
                'related_type' => $this->relatedType,
                'related_id' => $this->relatedId,
            ]);

            // Send notification
            if ($this->channel === 'whatsapp') {
                $result = $client->sendWhatsApp($this->to, $this->message);
            } else {
                $result = $client->sendSMS($this->to, $this->message);
            }

            // Update log with success
            $log->markAsSent($result);

            Log::info("Notification sent successfully", [
                'shop_id' => $this->shop->id,
                'channel' => $this->channel,
                'to' => $this->to,
                'message_sid' => $result['message_sid'],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send notification", [
                'shop_id' => $this->shop->id,
                'channel' => $this->channel,
                'to' => $this->to,
                'error' => $e->getMessage(),
            ]);

            // Update log with failure
            if (isset($log)) {
                $log->markAsFailed($e->getMessage());
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Notification job failed permanently", [
            'shop_id' => $this->shop->id,
            'channel' => $this->channel,
            'to' => $this->to,
            'error' => $exception->getMessage(),
        ]);

        // Find and mark log as failed if not already done
        $log = NotificationLog::where('shop_id', $this->shop->id)
            ->where('channel', $this->channel)
            ->where('to', $this->to)
            ->where('message', $this->message)
            ->whereIn('status', ['queued', 'sent'])
            ->latest()
            ->first();

        if ($log) {
            $log->markAsFailed('All retry attempts exhausted: ' . $exception->getMessage());
        }
    }
}
