<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * The daily summary (R-64 phase 8): what a person has not read, in the categories they chose to get as a summary, in one message. It lists what happened in
 * words the notifications page uses (so nothing is shown that the person could not see there), links to the notifications page behind a sign-in, and
 * carries a one-click unsubscribe (also as the List-Unsubscribe header). It never carries private message bodies.
 */
class NoticeDigest extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  list<array{name: string, total: int, items: list<string>}>  $sections  each category with how many unread, and the first few in words
     */
    public function __construct(public readonly User $recipient, public readonly array $sections, public readonly int $total) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('notices.digest_failed', ['exception' => $e::class]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('app.name').' summary: '.$this->total.' new '.($this->total === 1 ? 'notification' : 'notifications'));
    }

    public function headers(): Headers
    {
        $url = $this->unsubscribeUrl();

        return new Headers(text: ['List-Unsubscribe' => '<'.$url.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.notice-digest', with: [
            'name' => $this->recipient->email_verified_at === null ? '' : MailText::safe((string) $this->recipient->name),
            'groups' => array_map(fn (array $s) => ['name' => $s['name'], 'total' => $s['total'], 'items' => array_map(fn (string $i) => MailText::safe($i, '', 140), $s['items']), 'more' => max(0, $s['total'] - count($s['items']))], $this->sections),
            'count' => $this->total,
            'url' => route('notices'),
            'settingsUrl' => route('notices.preferences'),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    /** A link that works without signing in: it is signed, so only a message sent by us can use it. */
    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('notices.unsubscribe', ['user' => $this->recipient->getKey()]);
    }
}
