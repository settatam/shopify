<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public TeamInvitation $invitation
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return Envelope::new()
            ->from(new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ))
            ->replyTo(new Address($this->invitation->inviter->email, $this->invitation->inviter->name))
            ->subject('You\'ve been invited to join ' . $this->invitation->shop->name);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            text: 'emails.team-invitation-text',
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
}
