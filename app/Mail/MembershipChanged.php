<?php

namespace App\Mail;

use App\Models\Team;
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
 * Someone else added the person to a company, or took them out of one (a person who joined or left by their own hand is not mailed about it). Goes
 * to a confirmed address only. Says which company and what that changes for them; the company name is written as plain text, never as markup.
 */
class MembershipChanged extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public const ADDED = 'added';

    public const REMOVED = 'removed';

    /** R-72 (E-04): the person's role in the company changed, or the company itself was deleted. */
    public const ROLE_CHANGED = 'role_changed';

    public const COMPANY_DELETED = 'company_deleted';

    public int $tries = 3;

    /** An account or company deleted before the job runs means there is nobody to tell. */
    public bool $deleteWhenMissingModels = true;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public readonly string $kind, public readonly User $recipient, public readonly string $companyName, public readonly string $detail = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            self::ADDED => 'You were added to '.$this->companyName,
            self::ROLE_CHANGED => 'Your role in '.$this->companyName.' changed',
            self::COMPANY_DELETED => $this->companyName.' was deleted from InfoDot',
            default => 'You were removed from '.$this->companyName,
        });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('account.mail_failed', ['category' => 'mail', 'mail' => self::class, 'exception' => $e::class]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.membership-changed', with: [
            'name' => MailText::safe((string) $this->recipient->name),
            'company' => MailText::safe($this->companyName, 'a company'),
            'added' => $this->kind === self::ADDED,
            'kind' => $this->kind,
            'role' => MailText::safe($this->detail, 'a new role', 60),
            'url' => route('dashboard'),
        ]);
    }

    /** A company name as one plain line: no markup, no line breaks, a sensible length. Used as it is in the subject. */
    public static function plainName(Team $team): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $team->name))), 0, 80);
    }
}
