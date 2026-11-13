<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The operations alert `ops:alert` sends to OPS_ALERT_ADDRESS. Plain text, counts and masked first lines only: it is written so
 * that it is safe in an ordinary mailbox (no stack trace, no request data, nothing that names a person).
 */
class OpsAlert extends Mailable
{
    /**
     * @param  int  $total  error lines since the last alert
     * @param  int  $held  how many of those were found while alerts were held back (one an hour)
     * @param  ?string  $lastAlertAt  the time of the previous alert (HH:MM), or null for the first
     * @param  array<string, int>  $classes  error lines by exception class
     * @param  list<string>  $samples  the masked first line of up to five distinct errors
     * @param  array<string, int>  $checks  the operations checks that are not ok
     */
    public function __construct(public int $total, public int $held, public ?string $lastAlertAt, public array $classes, public array $samples, public array $checks) {}

    public function envelope(): Envelope
    {
        $parts = array_filter([
            $this->total > 0 ? $this->total.' '.($this->total === 1 ? 'error' : 'errors') : null,
            $this->checks !== [] ? count($this->checks).' failing '.(count($this->checks) === 1 ? 'check' : 'checks') : null,
        ]);

        return new Envelope(subject: 'InfoDot alert: '.implode(', ', $parts));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.ops-alert');
    }
}
