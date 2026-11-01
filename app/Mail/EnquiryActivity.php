<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Something arrived in the InfoDot Inbox (R-33). Says who and about what, and links to the
 * conversation; never carries the message itself, so nothing private sits in a mailbox.
 */
class EnquiryActivity extends Mailable
{
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
            'name' => $this->recipient->name,
            'business' => $this->enquiry->team->name,
            'subject' => $this->enquiry->subject,
            'customer' => $this->enquiry->customer?->name,
            'toCustomer' => $this->toCustomer,
            'kind' => $this->kind,
            'url' => $this->toCustomer ? route('inbox.show', $this->enquiry) : route('business.inbox.show', [$this->enquiry->team, $this->enquiry]),
        ]);
    }
}
