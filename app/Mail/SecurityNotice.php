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
 * Something changed on the person's account that protects it: the password, two-factor sign-in. Sent to the address on the account whether or not it is
 * confirmed, always (account protection cannot be switched off), and says what changed, when, and what to do if it was not them. It never carries the
 * old or new secret, and never a link that signs anyone in: the only link goes to the ordinary password reset page.
 *
 * Queued when a queue is configured (QUEUE_CONNECTION), otherwise sent inline like every other message.
 */
class SecurityNotice extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public const PASSWORD_CHANGED = 'password_changed';

    public const TWO_FACTOR_ON = 'two_factor_on';

    public const TWO_FACTOR_OFF = 'two_factor_off';

    public int $tries = 3;

    /** An account deleted before the job runs means there is nobody to tell. */
    public bool $deleteWhenMissingModels = true;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public readonly string $kind, public readonly User $recipient, public readonly string $at) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            self::PASSWORD_CHANGED => 'Your '.config('app.name').' password was changed',
            self::TWO_FACTOR_ON => 'Two-factor sign-in was turned on for your '.config('app.name').' account',
            default => 'Two-factor sign-in was turned off for your '.config('app.name').' account',
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('account.mail_failed', ['mail' => self::class, 'exception' => $e::class]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.security-notice', with: [
            // An address nobody has confirmed may belong to someone else, so it is greeted without the name the account holder typed.
            'name' => $this->recipient->email_verified_at === null ? '' : MailText::safe((string) $this->recipient->name),
            'what' => match ($this->kind) {
                self::PASSWORD_CHANGED => 'The password for your account was changed',
                self::TWO_FACTOR_ON => 'Two-factor sign-in was turned on for your account',
                default => 'Two-factor sign-in was turned off for your account',
            },
            'at' => $this->at,
            'resetUrl' => route('password.request'),
            'wasPassword' => $this->kind === self::PASSWORD_CHANGED,
        ]);
    }
}
