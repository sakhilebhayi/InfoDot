<?php

namespace App\Mail;

use App\Services\Points\PointsDigest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one email InfoDot sends about what a person earned in a closed week or month, to people who chose it. Plain on
 * purpose: what was earned and for what, anything that was taken back (shown beside it, never netted into it), the
 * balance, points that lapse in the next 30 days only if there are any, and how to stop these emails. No comparison with
 * anyone, no "you are behind", no streaks, no urgency.
 */
class PointsDigestMail extends Mailable
{
    public function __construct(
        public readonly string $recipientName,
        public readonly PointsDigest $digest,
    ) {}

    /** "12 to 18 October 2026", "28 September to 4 October 2026" or "September 2026". */
    public function periodLabel(): string
    {
        $start = $this->digest->periodStart;
        $end = $this->digest->periodEnd;

        if ($this->digest->period === 'month') {
            return $start->format('F Y');
        }
        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('j').' to '.$end->format('j F Y');
        }

        return $start->format($start->year === $end->year ? 'j F' : 'j F Y').' to '.$end->format('j F Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Dot Points for '.$this->periodLabel());
    }

    public function content(): Content
    {
        $d = $this->digest;

        return new Content(
            markdown: 'emails.points-digest',
            with: [
                'name' => $this->recipientName,
                'when' => ($d->period === 'month' ? '' : 'the week of ').$this->periodLabel(),
                'earned' => $this->points($d->pointsEarned),
                'awards' => array_map(fn (array $a) => [
                    'points' => $this->points($a['points']),
                    'times' => $a['count'] === 1 ? '1 time' : number_format($a['count']).' times',
                    'label' => self::plain($a['label']),
                ], $d->awards),
                'reversed' => $d->pointsReversed > 0 ? $this->points($d->pointsReversed) : null,
                'balance' => $this->points($d->balance),
                'expiringPoints' => $d->expiring ? number_format($d->expiring['points']) : null,
                'expiringFirst' => $d->expiring ? $d->expiring['first_at']->format('j F Y') : null,
                'summaryName' => $d->period === 'month' ? 'monthly' : 'weekly',
                'pointsUrl' => route('points'),
                'preferenceUrl' => route('points').'#digest-emails',
            ],
        );
    }

    private function points(int $n): string
    {
        return number_format($n).($n === 1 ? ' point' : ' points');
    }

    /**
     * A rule's label comes from another service, so it is shown as plain text: tags removed, one line, at most 200
     * characters, and nothing that markdown would turn into a link, emphasis or a heading.
     */
    public static function plain(string $label): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($label)));
        $text = mb_substr($text, 0, 200);

        return (string) preg_replace('/([\\\\`*_{}\[\]()#+!|<>~])/u', '\\\\$1', $text);
    }
}
