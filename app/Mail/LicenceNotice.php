<?php

namespace App\Mail;

use App\Models\Team;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A plain licence reminder to the owner or an administrator (R-53 phase 4). The subject and the lines are written by LicenceReminders from the
 * ledger; this only carries them, with one link to the licences screen. Never carries a price that is not in configuration.
 */
class LicenceNotice extends Mailable
{
    /** @param  list<string>  $lines */
    public function __construct(public readonly string $heading, public readonly array $lines, public readonly Team $team, public readonly User $recipient) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading.' ('.$this->team->name.')');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.licence-notice', with: [
            'name' => $this->recipient->name, 'heading' => $this->heading, 'lines' => $this->lines, 'company' => $this->team->name,
            'url' => route('teams.show', $this->team).'#seats', 'isOwner' => $this->recipient->ownsTeam($this->team),
        ]);
    }
}
