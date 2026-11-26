<?php

namespace App\Services\Licensing;

use App\Mail\LicenceNotice;
use App\Models\LicenceBatch;
use App\Models\LicenceNoticeSent;
use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The reminder emails of R-53 (phase 4): a renewal coming up, a failed payment or a shortfall inside its 14-day grace (at 14, 7 and 1 days
 * left), and the end of the free period for companies frozen by `licence:grandfather` (day 0, 30, 60, 80 and 89).
 *
 * Safe to run any number of times: each reminder is recorded in `licence_notices_sent` (unique per company, kind and reference) BEFORE it is
 * sent, and the record is removed again if the send fails so the next run tries again. A run that starts late sends only the reminder that is
 * current, never a backlog. Every query is per company; every message goes to that company's owner and administrators only.
 */
class LicenceReminders
{
    public function __construct(private SeatLedger $ledger, private LicenceAssignments $assignments, private LicencePricing $pricing) {}

    /**
     * @return array{renewal: int, payment: int, shortfall: int, grandfather: int}
     */
    public function run(): array
    {
        return ['renewal' => $this->renewals(), 'payment' => $this->failedPayments(), 'shortfall' => $this->shortfalls(), 'grandfather' => $this->grandfathering()];
    }

    private function renewals(): int
    {
        $sent = 0;
        $windows = (array) config('licensing.reminders.renewal_days', []);

        LicenceBatch::where('status', LicenceBatch::ACTIVE)->whereNull('cancelled_at')->whereNotNull('period_end')->where('period_end', '>', now())->each(function (LicenceBatch $b) use ($windows, &$sent) {
            $days = (array) ($windows[$b->interval] ?? []);
            $left = $this->daysLeft($b->period_end);
            $bucket = $this->bucket($days, $left);

            if ($bucket === null || ! ($team = $b->team)) {
                return;
            }

            $on = $b->period_end->timezone(config('app.timezone'))->format('j F Y');
            $amount = $b->quantity_next ? null : SeatLedger::formatMoney($b->total_minor, $b->currency);
            $sent += $this->notify($team, 'renewal', "batch{$b->id}:{$b->period_end->format('Ymd')}:{$bucket}", 'Your licences renew soon', [
                "{$b->quantity} ".($b->quantity === 1 ? 'licence renews' : 'licences renew')." on {$on}".($amount ? " ({$amount} excluding VAT)." : '.'),
                $b->quantity_next ? "You asked for this purchase to renew with {$b->quantity_next} licences instead." : 'Nothing needs to be done to keep them.',
            ], false);
        });

        return $sent;
    }

    private function failedPayments(): int
    {
        $sent = 0;

        LicenceBatch::whereIn('status', [LicenceBatch::GRACE, LicenceBatch::REFUNDED])->whereNotNull('grace_until')->where('grace_until', '>', now())->each(function (LicenceBatch $b) use (&$sent) {
            $bucket = $this->bucket((array) config('licensing.reminders.grace_days_left', [14, 7, 1]), $this->daysLeft($b->grace_until));

            if ($bucket === null || ! ($team = $b->team)) {
                return;
            }

            $until = $b->grace_until->timezone(config('app.timezone'))->format('j F Y');
            $refunded = $b->status === LicenceBatch::REFUNDED;
            $sent += $this->notify($team, 'payment', "batch{$b->id}:{$b->grace_until->format('Ymd')}:{$bucket}", $refunded ? 'A licence purchase was refunded' : 'A licence payment failed', [
                ($refunded ? 'A payment for ' : 'The payment for ')."{$b->quantity} ".($b->quantity === 1 ? 'licence' : 'licences').($refunded ? ' was refunded.' : ' did not go through.'),
                "The licences keep working until {$until}. After that, the people beyond your licences lose access to {$team->name}'s platforms. The owner and administrators never do, and nothing is deleted.",
                $refunded ? 'Buy the licences again to keep everyone covered.' : 'Update the payment with the payment service, or buy the licences again, to keep everyone covered.',
            ], true);
        });

        return $sent;
    }

    /** A company that holds more people than licences, inside its own 14 days (after a reduction or a cancellation). */
    private function shortfalls(): int
    {
        $sent = 0;

        TeamLicence::whereNotNull('grace_until')->where('grace_until', '>', now())->each(function (TeamLicence $l) use (&$sent) {
            $bucket = $this->bucket((array) config('licensing.reminders.grace_days_left', [14, 7, 1]), $this->daysLeft($l->grace_until));
            $team = Team::find($l->team_id);

            if ($bucket === null || $team === null) {
                return;
            }

            $over = max(0, $this->assignments->licensedCount($team) - $this->ledger->summary($team)['capacity']);
            $names = $this->assignments->lapseCandidates($team)->take($over)->pluck('name')->all();

            if ($over < 1 || $names === []) {
                return;
            }

            $until = $l->grace_until->timezone(config('app.timezone'))->format('j F Y');
            $sent += $this->notify($team, 'shortfall', "grace{$l->grace_until->format('Ymd')}:{$bucket}", 'You have more people than licences', [
                "{$team->name} has {$over} more ".($over === 1 ? 'person' : 'people').' than licences.',
                "On {$until}, ".implode(', ', $names).' will lose access to the company on InfoDot and its platforms unless you buy licences or take one back from someone yourself. Their accounts and content stay.',
            ], true);
        });

        return $sent;
    }

    private function grandfathering(): int
    {
        $sent = 0;
        $offsets = (array) config('licensing.reminders.grandfather_offsets', [0, 30, 60, 80, 89]);

        TeamLicence::whereNotNull('grandfathered_until')->where('grandfathered_until', '>', now())->each(function (TeamLicence $l) use ($offsets, &$sent) {
            $team = Team::find($l->team_id);

            if ($team === null || $team->personal_team) {
                return;
            }

            $start = isset($l->grandfather_snapshot['applied_at']) ? Carbon::parse($l->grandfather_snapshot['applied_at']) : $l->grandfathered_until->copy()->subDays((int) config('licensing.grandfather_days', 90));
            $elapsed = max(0, (int) floor($start->diffInDays(now(), false)));
            $chosen = collect($offsets)->map(fn ($o) => (int) $o)->filter(fn ($o) => $o <= $elapsed)->max();

            if ($chosen === null) {
                return;
            }

            $until = $l->grandfathered_until->timezone(config('app.timezone'))->format('j F Y');
            $extras = max(0, $this->ledger->summary($team)['people'] - 1);
            $month = $this->pricing->costOf($extras, 'month');
            $year = $this->pricing->costOf($extras, 'year');
            $cost = $extras > 0 && $month ? ' For your '.($extras === 1 ? 'one extra person' : "{$extras} extra people").', that is '.SeatLedger::formatMoney($month['total_minor'], $month['currency']).' a month'.($year ? ' or '.SeatLedger::formatMoney($year['total_minor'], $year['currency']).' a year' : '').', excluding VAT.' : '';

            $sent += $this->notify($team, 'grandfather', "gf{$l->grandfathered_until->format('Ymd')}:{$chosen}", 'The free period for your team ends on '.$until, [
                "Every person already in {$team->name} keeps their place free until {$until}. After that each person other than the owner needs a licence.".$cost,
                'Nobody is removed on that day: anyone left without a licence gets a further 14 days, then loses access to the platforms. The owner and administrators never do, and nothing is deleted.',
            ], true);
        });

        return $sent;
    }

    /**
     * Records the reminder, then emails the owner (and administrators when $toAdmins). Returns 1 when something was sent, 0 when it had already
     * been sent. A failed send removes the record so the next run tries again.
     *
     * @param  list<string>  $lines
     */
    private function notify(Team $team, string $kind, string $ref, string $heading, array $lines, bool $toAdmins): int
    {
        try {
            $row = LicenceNoticeSent::create(['team_id' => $team->getKey(), 'kind' => $kind, 'ref' => $ref, 'sent_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return 0;
        }

        /** @var array<int, User> $to */
        $to = [];

        if ($team->owner instanceof User) {
            $to[$team->owner->getKey()] = $team->owner;
        }

        if ($toAdmins) {
            foreach ($team->users as $member) {
                /** @var User $member */
                if (TeamRoles::normalise($member->membership->role ?? null) === TeamRoles::ADMIN) {
                    $to[$member->getKey()] = $member;
                }
            }
        }

        // R-72 (E-12): each person is sent to on their own. One address that fails no longer makes the whole reminder go again to
        // everyone who already had it: only when nobody could be reached is the reminder left to be tried on the next run.
        $sent = 0;
        $failed = 0;
        foreach ($to as $user) {
            if (blank($user->email)) {
                continue;
            }

            try {
                Mail::to($user->email)->send(new LicenceNotice($heading, $lines, $team, $user));
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                // R-72 (E-09): at error level, so the ops alert counts it.
                Log::error('licence.reminder_failed', ['category' => 'mail', 'team' => $team->getKey(), 'kind' => $kind, 'user_id' => $user->getKey(), 'reason' => class_basename($e)]);
            }
        }

        if ($sent === 0 && $failed > 0) {
            $row->delete();

            return 0;
        }

        $this->ledger->record($team, null, 'reminder_sent', $kind, ['ref' => $ref]);

        return 1;
    }

    private function daysLeft(Carbon $end): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($end, false) / 86400));
    }

    /** The smallest configured threshold that the days left have reached (14, 7, 1: 6 days left is the 7 reminder), or null if none has. @param list<int> $thresholds */
    private function bucket(array $thresholds, int $left): ?int
    {
        $reached = collect($thresholds)->map(fn ($t) => (int) $t)->filter(fn ($t) => $left <= $t)->min();

        return $reached === null ? null : (int) $reached;
    }
}
