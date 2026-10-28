<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A message from the public contact form. Plain text; the sender is the reply-to, never the from address. */
class ContactMessage extends Mailable
{
    public function __construct(public string $senderName, public string $senderEmail, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'InfoDot contact form: '.mb_substr(preg_replace('/\s+/', ' ', $this->senderName), 0, 60),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.contact-message');
    }
}
