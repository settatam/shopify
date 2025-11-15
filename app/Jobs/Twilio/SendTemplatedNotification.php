<?php

namespace App\Jobs\Twilio;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTemplatedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Shop $shop,
        public string $to,
        public string $templateKey,
        public array $variables = [],
        public string $channel = 'sms',
        public ?string $relatedType = null,
        public ?int $relatedId = null
    ) {}

    public function handle(): void
    {
        // Get template from shop settings
        $templates = $this->shop->settings['notification_templates'] ?? [];
        $template = $templates[$this->templateKey] ?? null;

        if (!$template) {
            throw new \Exception("Notification template '{$this->templateKey}' not found");
        }

        // Check if template is enabled
        if (isset($template['enabled']) && !$template['enabled']) {
            // Template is disabled, skip
            return;
        }

        // Replace variables in template
        $message = $template['message'] ?? '';
        foreach ($this->variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        // Dispatch send notification job
        SendNotification::dispatch(
            $this->shop,
            $this->to,
            $message,
            $this->channel,
            $this->templateKey,
            $this->variables,
            $this->relatedType,
            $this->relatedId
        );
    }
}
