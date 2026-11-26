<?php

namespace App\Mail;

use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Log;

/**
 * R-72 (E-04): the confirmation that an account was deleted, sent to the address it had. It carries plain strings only: by the time it
 * is sent the account no longer exists. It names what went and what to do if the person did not ask for it.
 */
class AccountClosed extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $name, public readonly string $at) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('app.name').' account was deleted');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.account-closed', with: [
            'name' => MailText::safe($this->name, ''),
            'at' => $this->at,
            'contactUrl' => route('contact'),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('account.mail_failed', ['category' => 'mail', 'mail' => self::class, 'exception' => $e::class]);
    }
}
