<?php

namespace App\Services\Twilio;

use App\Models\Shop;
use Twilio\Rest\Client as TwilioRestClient;
use Twilio\Exceptions\TwilioException;
use Illuminate\Support\Facades\Log;

class TwilioClient
{
    protected Shop $shop;
    protected ?TwilioRestClient $client = null;
    protected ?string $accountSid = null;
    protected ?string $authToken = null;
    protected ?string $fromNumber = null;
    protected ?string $whatsappNumber = null;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
        $this->loadCredentials();
    }

    /**
     * Load Twilio credentials from shop settings
     */
    protected function loadCredentials(): void
    {
        $twilioSettings = $this->shop->settings['twilio'] ?? [];

        $this->accountSid = $twilioSettings['account_sid'] ?? null;
        $this->authToken = $twilioSettings['auth_token'] ?? null;
        $this->fromNumber = $twilioSettings['from_number'] ?? null;
        $this->whatsappNumber = $twilioSettings['whatsapp_number'] ?? null;

        // Initialize Twilio client if credentials are available
        if ($this->accountSid && $this->authToken) {
            $this->client = new TwilioRestClient($this->accountSid, $this->authToken);
        }
    }

    /**
     * Check if Twilio is configured
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && $this->fromNumber !== null;
    }

    /**
     * Check if WhatsApp is configured
     */
    public function isWhatsAppConfigured(): bool
    {
        return $this->client !== null && $this->whatsappNumber !== null;
    }

    /**
     * Send SMS message
     */
    public function sendSMS(string $to, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Twilio is not configured for this shop. Please add your Twilio credentials.');
        }

        try {
            $messageData = [
                'from' => $this->fromNumber,
                'body' => $message,
            ];

            // Add optional parameters
            if (!empty($options['statusCallback'])) {
                $messageData['statusCallback'] = $options['statusCallback'];
            }

            if (!empty($options['mediaUrl'])) {
                $messageData['mediaUrl'] = $options['mediaUrl'];
            }

            $twilioMessage = $this->client->messages->create($to, $messageData);

            Log::info("SMS sent via Twilio", [
                'shop_id' => $this->shop->id,
                'to' => $to,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'to' => $twilioMessage->to,
                'from' => $twilioMessage->from,
                'price' => $twilioMessage->price,
                'price_unit' => $twilioMessage->priceUnit,
            ];
        } catch (TwilioException $e) {
            Log::error("Failed to send SMS via Twilio", [
                'shop_id' => $this->shop->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to send SMS: ' . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendWhatsApp(string $to, string $message, array $options = []): array
    {
        if (!$this->isWhatsAppConfigured()) {
            throw new \Exception('WhatsApp is not configured for this shop. Please add your Twilio WhatsApp number.');
        }

        try {
            // Ensure 'to' number has whatsapp: prefix
            if (!str_starts_with($to, 'whatsapp:')) {
                $to = 'whatsapp:' . $to;
            }

            $messageData = [
                'from' => $this->whatsappNumber,
                'body' => $message,
            ];

            // Add optional parameters
            if (!empty($options['statusCallback'])) {
                $messageData['statusCallback'] = $options['statusCallback'];
            }

            if (!empty($options['mediaUrl'])) {
                $messageData['mediaUrl'] = $options['mediaUrl'];
            }

            $twilioMessage = $this->client->messages->create($to, $messageData);

            Log::info("WhatsApp message sent via Twilio", [
                'shop_id' => $this->shop->id,
                'to' => $to,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'to' => $twilioMessage->to,
                'from' => $twilioMessage->from,
                'price' => $twilioMessage->price,
                'price_unit' => $twilioMessage->priceUnit,
            ];
        } catch (TwilioException $e) {
            Log::error("Failed to send WhatsApp message via Twilio", [
                'shop_id' => $this->shop->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to send WhatsApp message: ' . $e->getMessage());
        }
    }

    /**
     * Get message status
     */
    public function getMessageStatus(string $messageSid): array
    {
        if (!$this->client) {
            throw new \Exception('Twilio is not configured');
        }

        try {
            $message = $this->client->messages($messageSid)->fetch();

            return [
                'sid' => $message->sid,
                'status' => $message->status,
                'to' => $message->to,
                'from' => $message->from,
                'body' => $message->body,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'date_created' => $message->dateCreated?->format('Y-m-d H:i:s'),
                'date_sent' => $message->dateSent?->format('Y-m-d H:i:s'),
                'date_updated' => $message->dateUpdated?->format('Y-m-d H:i:s'),
            ];
        } catch (TwilioException $e) {
            throw new \Exception('Failed to get message status: ' . $e->getMessage());
        }
    }

    /**
     * Get account balance (for monitoring)
     */
    public function getBalance(): array
    {
        if (!$this->client) {
            throw new \Exception('Twilio is not configured');
        }

        try {
            $account = $this->client->api->v2010->accounts($this->accountSid)->fetch();
            $balance = $this->client->api->v2010->accounts($this->accountSid)
                ->balance
                ->fetch();

            return [
                'balance' => $balance->balance,
                'currency' => $balance->currency,
                'account_status' => $account->status,
            ];
        } catch (TwilioException $e) {
            throw new \Exception('Failed to get account balance: ' . $e->getMessage());
        }
    }

    /**
     * Validate phone number format
     */
    public static function validatePhoneNumber(string $phoneNumber): bool
    {
        // Basic E.164 format validation: +[country code][number]
        return preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber) === 1;
    }

    /**
     * Format phone number to E.164
     */
    public static function formatPhoneNumber(string $phoneNumber, string $countryCode = '+1'): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Add country code if not present
        if (!str_starts_with($phoneNumber, '+')) {
            // If number starts with country code digits (e.g., 1 for US), don't duplicate
            if (str_starts_with($cleaned, ltrim($countryCode, '+'))) {
                return '+' . $cleaned;
            }
            return $countryCode . $cleaned;
        }

        return '+' . $cleaned;
    }

    /**
     * Test connection
     */
    public function testConnection(): array
    {
        if (!$this->client) {
            throw new \Exception('Twilio is not configured');
        }

        try {
            // Fetch account details as a test
            $account = $this->client->api->v2010->accounts($this->accountSid)->fetch();

            return [
                'success' => true,
                'account_sid' => $account->sid,
                'friendly_name' => $account->friendlyName,
                'status' => $account->status,
                'type' => $account->type,
            ];
        } catch (TwilioException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send notification using template
     */
    public function sendNotification(
        string $to,
        string $templateKey,
        array $variables = [],
        string $channel = 'sms'
    ): array {
        // Get template from shop settings
        $templates = $this->shop->settings['notification_templates'] ?? [];
        $template = $templates[$templateKey] ?? null;

        if (!$template) {
            throw new \Exception("Notification template '{$templateKey}' not found");
        }

        // Replace variables in template
        $message = $template['message'] ?? '';
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        // Send via appropriate channel
        if ($channel === 'whatsapp') {
            return $this->sendWhatsApp($to, $message);
        } else {
            return $this->sendSMS($to, $message);
        }
    }

    /**
     * Get configured phone numbers
     */
    public function getPhoneNumbers(): array
    {
        return [
            'sms' => $this->fromNumber,
            'whatsapp' => $this->whatsappNumber,
        ];
    }
}
