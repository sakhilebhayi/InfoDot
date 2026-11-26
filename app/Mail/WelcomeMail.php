<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * R-72 (E-04): one welcome, once the person has confirmed their address. Three things they can do first, each a real page; no promise
 * InfoDot cannot keep, and nothing to sell.
 */
class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly User $recipient) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.welcome', with: [
            'name' => MailText::safe((string) $this->recipient->name, ''),
            'homeUrl' => route('dashboard'),
            'searchUrl' => route('solution_search_results'),
            'businessesUrl' => route('businesses.index'),
            'settingsUrl' => route('notices.preferences'),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('account.mail_failed', ['category' => 'mail', 'mail' => self::class, 'exception' => $e::class]);
    }
}
