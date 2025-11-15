<?php

namespace App\Services;

use App\Models\EmailProviderSetting;
use App\Models\Shop;
use SendGrid;
use SendGrid\Mail\Mail;
use Illuminate\Support\Facades\Log;

class SendGridService
{
    /**
     * Send a transactional email via SendGrid.
     */
    public function send(
        Shop $shop,
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?array $attachments = null,
        ?array $customArgs = null
    ): bool {
        $settings = $this->getSettings($shop);

        if (!$settings || !$settings->isReadyToSend()) {
            Log::error('SendGrid not configured for shop', ['shop_id' => $shop->id]);
            return false;
        }

        try {
            $sendgrid = new SendGrid($settings->getDecryptedApiKey());

            $mail = new Mail();

            // From
            $mail->setFrom(
                $fromEmail ?? $settings->from_email,
                $fromName ?? $settings->from_name
            );

            // To
            $mail->addTo($to);

            // Subject
            $mail->setSubject($subject);

            // Content
            $mail->addContent("text/html", $htmlContent);

            if ($textContent) {
                $mail->addContent("text/plain", $textContent);
            }

            // Reply-to
            if ($settings->reply_to) {
                $mail->setReplyTo($settings->reply_to);
            }

            // Attachments
            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $mail->addAttachment(
                        base64_encode($attachment['content']),
                        $attachment['type'],
                        $attachment['filename']
                    );
                }
            }

            // Custom arguments (metadata)
            if ($customArgs) {
                $mail->addCustomArgs($customArgs);
            }

            // Send
            $response = $sendgrid->send($mail);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                // Increment counter
                $settings->incrementEmailsSent();

                Log::info('SendGrid email sent successfully', [
                    'shop_id' => $shop->id,
                    'to' => $to,
                    'subject' => $subject,
                    'status_code' => $response->statusCode(),
                ]);

                return true;
            }

            Log::error('SendGrid API error', [
                'shop_id' => $shop->id,
                'status_code' => $response->statusCode(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('SendGrid exception', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send bulk emails (up to 1000 recipients).
     */
    public function sendBulk(
        Shop $shop,
        array $recipients, // ['email' => 'name']
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        ?string $fromEmail = null,
        ?string $fromName = null
    ): array {
        $settings = $this->getSettings($shop);

        if (!$settings || !$settings->isReadyToSend()) {
            return [
                'success' => false,
                'message' => 'SendGrid not configured',
            ];
        }

        try {
            $sendgrid = new SendGrid($settings->getDecryptedApiKey());

            $mail = new Mail();

            // From
            $mail->setFrom(
                $fromEmail ?? $settings->from_email,
                $fromName ?? $settings->from_name
            );

            // Add all recipients
            foreach ($recipients as $email => $name) {
                $mail->addTo($email, $name);
            }

            // Subject
            $mail->setSubject($subject);

            // Content
            $mail->addContent("text/html", $htmlContent);

            if ($textContent) {
                $mail->addContent("text/plain", $textContent);
            }

            // Reply-to
            if ($settings->reply_to) {
                $mail->setReplyTo($settings->reply_to);
            }

            // Send
            $response = $sendgrid->send($mail);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                // Increment counter
                $settings->incrementEmailsSent(count($recipients));

                return [
                    'success' => true,
                    'sent' => count($recipients),
                    'message' => 'Bulk email sent successfully',
                ];
            }

            return [
                'success' => false,
                'message' => 'SendGrid API error: ' . $response->statusCode(),
            ];

        } catch (\Exception $e) {
            Log::error('SendGrid bulk email exception', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify API key.
     */
    public function verifyApiKey(string $apiKey): bool
    {
        try {
            $sendgrid = new SendGrid($apiKey);

            // Make a simple API call to verify the key
            $response = $sendgrid->client->user()->get();

            return $response->statusCode() === 200;

        } catch (\Exception $e) {
            Log::error('SendGrid API key verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get email statistics from SendGrid.
     */
    public function getStatistics(Shop $shop, ?string $startDate = null, ?string $endDate = null): ?array
    {
        $settings = $this->getSettings($shop);

        if (!$settings || !$settings->isReadyToSend()) {
            return null;
        }

        try {
            $sendgrid = new SendGrid($settings->getDecryptedApiKey());

            $params = [
                'start_date' => $startDate ?? now()->subDays(30)->format('Y-m-d'),
                'end_date' => $endDate ?? now()->format('Y-m-d'),
            ];

            $response = $sendgrid->client->stats()->get(null, $params);

            if ($response->statusCode() === 200) {
                return json_decode($response->body(), true);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch SendGrid statistics', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get SendGrid settings for shop.
     */
    protected function getSettings(Shop $shop): ?EmailProviderSetting
    {
        return EmailProviderSetting::byProvider($shop->id, 'sendgrid');
    }

    /**
     * Configure SendGrid for shop.
     */
    public function configure(
        Shop $shop,
        string $apiKey,
        string $fromEmail,
        string $fromName,
        ?string $replyTo = null,
        bool $isPrimary = false
    ): EmailProviderSetting {
        $settings = $this->getSettings($shop) ?? new EmailProviderSetting([
            'shop_id' => $shop->id,
            'provider' => 'sendgrid',
        ]);

        $settings->setApiKey($apiKey);
        $settings->from_email = $fromEmail;
        $settings->from_name = $fromName;
        $settings->reply_to = $replyTo;

        // Verify API key before activating
        if ($this->verifyApiKey($apiKey)) {
            $settings->is_active = true;
            $settings->is_primary = $isPrimary;
        } else {
            $settings->is_active = false;
            $settings->is_primary = false;
            throw new \Exception('Invalid SendGrid API key');
        }

        $settings->save();

        return $settings;
    }

    /**
     * Test email configuration.
     */
    public function testConfiguration(Shop $shop, string $testEmail): bool
    {
        return $this->send(
            shop: $shop,
            to: $testEmail,
            subject: 'Test Email from ' . $shop->shop_name,
            htmlContent: '<p>This is a test email to verify your SendGrid configuration is working correctly.</p>',
            textContent: 'This is a test email to verify your SendGrid configuration is working correctly.'
        );
    }
}
