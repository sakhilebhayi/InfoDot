<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Models\User;
use App\Models\UserNotice;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Something arrived in the InfoDot Inbox (R-33). Says who and about what, and links to the
 * conversation; never carries the message itself, so nothing private sits in a mailbox.
 */
class EnquiryActivity extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** R-72 (E-06): sent by the queue worker, so the page never waits on the mail server. Three tries, then failed() records it. */
    public int $tries = 3;

    /** A booking, enquiry or person deleted before the job runs means there is nothing to say. */
    public bool $deleteWhenMissingModels = true;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(\Throwable $e): void
    {
        // R-72 (E-09): at error level, so the ops alert counts it; the class only, never the message (it can hold the address).
        Log::error('enquiry.mail_failed', ['category' => 'mail', 'mail' => self::class, 'exception' => $e::class]);
    }

    public function __construct(
        public readonly string $kind,
        public readonly Enquiry $enquiry,
        public readonly User $recipient,
        public readonly bool $toCustomer,
    ) {}

    public function envelope(): Envelope
    {
        $business = $this->enquiry->team->name;

        return new Envelope(subject: match (true) {
            $this->toCustomer => $business.' replied to your enquiry',
            $this->kind === UserNotice::ENQUIRY_RECEIVED => 'New enquiry for '.$business,
            $this->kind === UserNotice::ENQUIRY_ASSIGNED => 'An enquiry for '.$business.' is now yours',
            default => 'A reply on an enquiry for '.$business,
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.enquiry-activity', with: [
            'settingsUrl' => route('notices.preferences'),
            // R-72 (E-05): every name and subject here is typed by someone else; it is shown as plain words, never as Markdown or a link.
            'name' => MailText::safe((string) $this->recipient->name, 'there'),
            'business' => MailText::safe((string) $this->enquiry->team->name, 'the business', 120),
            'subject' => MailText::safe((string) $this->enquiry->subject, 'An enquiry', 160),
            'customer' => $this->enquiry->customer ? MailText::safe((string) $this->enquiry->customer->name, 'A customer') : null,
            'toCustomer' => $this->toCustomer,
            'kind' => $this->kind,
            'url' => $this->toCustomer ? route('inbox.show', $this->enquiry) : route('business.inbox.show', [$this->enquiry->team, $this->enquiry]),
        ]);
    }
}
