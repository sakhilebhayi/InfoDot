<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * The one email InfoDot sends about Dot Points that are about to lapse. Plain on purpose: how many, when, where to
 * look, and how to stop these emails. No countdown, no pressure, no "act now".
 */
class PointsExpiryReminder extends Mailable
{
    public function __construct(
        public readonly string $recipientName,
        public readonly int $points,
        public readonly Carbon $expiresOn,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: number_format($this->points).' of your Dot Points '.($this->points === 1 ? 'lapses' : 'lapse').' on '.$this->expiresOn->format('j F Y'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.points-expiry',
            with: [
                'name' => $this->recipientName,
                'count' => number_format($this->points),
                'verb' => $this->points === 1 ? 'lapses' : 'lapse',
                'date' => $this->expiresOn->format('j F Y'),
                'pointsUrl' => route('points'),
                'preferenceUrl' => route('points').'#expiry-emails',
            ],
        );
    }
}
