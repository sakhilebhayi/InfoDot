<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The code that proves a business controls an email address at its own website domain (R-29 §10B).
 * Plain: the code, who asked, how long it lasts, and what to do if nobody here asked.
 */
class VerificationEmailCode extends Mailable
{
    public function __construct(
        public readonly string $code,
        public readonly string $businessName,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your InfoDot verification code: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.verification-code', with: [
            'code' => $this->code,
            'business' => $this->businessName,
            'minutes' => $this->minutes,
        ]);
    }
}
