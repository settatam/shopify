<?php

namespace App\Mail;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlBody;
    public string $textBody;
    public array $variables;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public NotificationLog $log,
        public ?NotificationTemplate $template = null
    ) {
        $this->htmlBody = $log->body;
        $this->textBody = strip_tags($log->body);
        $this->variables = $log->variables ?? [];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromName = $this->template->from_name ?? config('mail.from.name');
        $fromEmail = $this->template->from_email ?? config('mail.from.address');
        $replyTo = $this->template->reply_to ?? null;

        $envelope = Envelope::new()
            ->from(new Address($fromEmail, $fromName))
            ->subject($this->log->subject);

        if ($replyTo) {
            $envelope->replyTo(new Address($replyTo));
        }

        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->addTrackingPixel($this->htmlBody),
            text: 'emails.notification-text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Add tracking pixel to HTML body.
     */
    protected function addTrackingPixel(string $html): string
    {
        // Add tracking pixel before closing body tag
        $trackingUrl = route('notifications.track.open', ['id' => $this->log->id]);
        $trackingPixel = '<img src="' . $trackingUrl . '" width="1" height="1" alt="" />';

        if (str_contains($html, '</body>')) {
            $html = str_replace('</body>', $trackingPixel . '</body>', $html);
        } else {
            $html .= $trackingPixel;
        }

        // Add link tracking to all links
        $html = $this->addLinkTracking($html);

        return $html;
    }

    /**
     * Add tracking to links in HTML.
     */
    protected function addLinkTracking(string $html): string
    {
        // Replace href attributes with tracking URLs
        $html = preg_replace_callback(
            '/href=["\']([^"\']+)["\']/i',
            function ($matches) {
                $originalUrl = $matches[1];

                // Skip anchor links, mailto, tel, etc.
                if (str_starts_with($originalUrl, '#') ||
                    str_starts_with($originalUrl, 'mailto:') ||
                    str_starts_with($originalUrl, 'tel:')) {
                    return $matches[0];
                }

                $trackingUrl = route('notifications.track.click', [
                    'id' => $this->log->id,
                    'url' => urlencode($originalUrl),
                ]);

                return 'href="' . $trackingUrl . '"';
            },
            $html
        );

        return $html;
    }
}
