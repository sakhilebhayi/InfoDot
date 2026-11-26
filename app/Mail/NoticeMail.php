<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * R-72 (E-03): the email for a notice that has no message of its own, such as a business's verification, standing or ownership, or an
 * identity warning. Its words are the notice's own, as the bell shows them to this person, so it says nothing they could not already
 * read. Sent by App\Jobs\SendNoticeEmail, which decides whether it goes at all.
 */
class NoticeMail extends Mailable
{
    public function __construct(
        public readonly User $recipient,
        public readonly string $text,
        public readonly ?string $url,
        public readonly bool $security,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->security
            ? 'A security warning on your '.config('app.name').' account'
            : MailText::safe($this->text, 'News from '.config('app.name'), 120));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.notice', with: [
            'name' => MailText::safe((string) $this->recipient->name, ''),
            // The words can carry a company name someone typed: plain words only, never Markdown or a link (R-72, E-05).
            'text' => MailText::safe($this->text, '', 600),
            'url' => $this->url,
            'security' => $this->security,
            'settingsUrl' => route('notices.preferences'),
        ]);
    }
}
