<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamLicence;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who holds a seat in a company, how many it has, and the one place that decides whether one more person fits.
 *
 * A seat is used by the owner and by every member. A pending invitation reserves a seat (config licensing.pending_invitations_reserve_a_seat),
 * so two people inviting at the same moment cannot both be promised the last seat: every change that adds someone runs inside `guard()`, which
 * takes a row lock on the company inside a transaction, counts, and only then lets the change through.
 *
 * Nothing here ever removes a person or blocks a profile. A company that is over its capacity (for example one that grew before seats existed)
 * keeps everyone; it just cannot add more until it has room.
 */
class SeatLedger
{
    /** Personal workspaces are one person and are not licensed. */
    public function applies(Team $team): bool
    {
        return ! $team->personal_team;
    }

    /**
     * The number of people a company may have for free: THE one place this is decided (R-53 owner decision: one free licence, the owner's).
     * A company-level override wins (`licence:grandfather` sets it for companies that existed before the rule), but only while its
     * `grandfathered_until` date has not passed; an override with no date is a deliberate manual setting and is kept.
     */
    public function freeAllowance(?TeamLicence $licence = null): int
    {
        $base = max(0, (int) config('licensing.included_seats'));

        if ($licence === null || $licence->included_seats === null) {
            return $base;
        }

        if ($licence->grandfathered_until !== null && ! $licence->grandfathered_until->isFuture()) {
            return $base; // the transition has ended: the frozen headcount is no longer free
        }

        return (int) $licence->included_seats;
    }

    public function licenceFor(Team $team): TeamLicence
    {
        return TeamLicence::firstOrCreate(['team_id' => $team->getKey()], ['payment_status' => 'free']);
    }

    /**
     * @return array{applies: bool, included: int, purchased: int, batch_seats: int, capacity: int, people: int, members: int, reserved: int, used: int, available: int, over_by: int, extra_in_use: int, status: string, grace_until: mixed, grandfathered: bool, grandfathered_until: mixed, price: ?array{amount_minor_units: int, annual_amount_minor_units: ?int, currency: string}, can_buy: bool}
     */
    public function summary(Team $team): array
    {
        $licence = $this->licenceFor($team);
        $included = $this->freeAllowance($licence);
        $members = $team->users()->count();
        $people = $members + 1; // the owner holds a seat
        $reserved = config('licensing.pending_invitations_reserve_a_seat', true) ? TeamInvitation::live()->where('team_id', $team->getKey())->count() : 0;   // an expired invitation holds no seat
        $batchSeats = $this->batchSeats($team);
        $purchased = $licence->purchased_seats + $batchSeats;   // bought licences: the paid batches that count today, plus any the older Finance read recorded
        $capacity = $included + $purchased;
        $used = $people + $reserved;
        $price = $this->price();

        return [
            'applies' => $this->applies($team),
            'included' => $included,
            'purchased' => $purchased,
            'batch_seats' => $batchSeats,
            'capacity' => $capacity,
            'people' => $people,
            'members' => $members,
            'reserved' => $reserved,
            'used' => $used,
            'available' => max(0, $capacity - $used),
            'over_by' => max(0, $people - $capacity),
            'extra_in_use' => max(0, $people - $included),
            'status' => $licence->payment_status,
            'grace_until' => $licence->grace_until,
            'grandfathered' => $licence->isGrandfathered(),
            'grandfathered_until' => $licence->grandfathered_until,
            'price' => $price,
            'can_buy' => (bool) config('licensing.finance_connected', false),
        ];
    }

    /** The licences in this company's paid batches that count right now (active, or inside their grace). Never includes a batch that is only pending. */
    public function batchSeats(Team $team): int
    {
        return (int) LicenceBatch::where('team_id', $team->getKey())->whereIn('status', [LicenceBatch::ACTIVE, LicenceBatch::GRACE, LicenceBatch::REFUNDED])->get()->filter->covers()->sum('quantity');
    }

    /** The approved price of one extra licence (ex VAT), or null when no price has been decided: it comes from configuration only and is never defaulted. */
    public function price(): ?array
    {
        $amount = config('licensing.price.amount_minor_units');
        $annual = config('licensing.price.annual_amount_minor_units');
        $currency = config('licensing.price.currency');

        return filled($amount) && filled($currency)
            ? ['amount_minor_units' => (int) $amount, 'annual_amount_minor_units' => filled($annual) ? (int) $annual : null, 'currency' => (string) $currency]
            : null;
    }

    /** "99.00 ZAR" from minor units, or null when there is nothing to show. */
    public static function formatMoney(?int $minor, ?string $currency): ?string
    {
        return $minor === null || blank($currency) ? null : number_format($minor / 100, 2, '.', ' ').' '.$currency;
    }

    /**
     * Runs $change only if the company has room for one more person, inside a transaction that holds a lock on the company, so the count and the
     * change cannot be interleaved with another invitation. $exceptEmail is an invitation that is being turned into a membership: its reservation
     * is the seat it is about to use, so it is not counted twice.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    public function guard(Team $team, Closure $change, ?string $exceptEmail = null, ?User $actor = null, string $type = 'invited', ?string $subject = null)
    {
        $refusal = null;

        // Fresh purchased-seat numbers come first, outside the transaction (it makes an HTTP request; failure changes nothing).
        app(FinanceEntitlements::class)->sync($team);

        try {
            return DB::transaction(function () use ($team, $change, $exceptEmail, $actor, $type, $subject, &$refusal) {
                $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();

                if ($this->applies($locked)) {
                    $s = $this->summary($locked);
                    $holdsThisSeat = $exceptEmail !== null
                        && config('licensing.pending_invitations_reserve_a_seat', true)
                        && TeamInvitation::live()->where('team_id', $locked->getKey())->whereRaw('lower(email) = ?', [mb_strtolower($exceptEmail)])->exists();
                    $reserved = $s['reserved'] - ($holdsThisSeat ? 1 : 0);

                    if ($s['capacity'] < $s['people'] + $reserved + 1) {
                        $refusal = ['capacity' => $s['capacity'], 'people' => $s['people'], 'reserved' => $reserved];

                        throw SeatLimitReached::withMessages(['email' => $this->message($locked, $s)])->errorBag('addTeamMember');
                    }
                }

                $result = $change();
                $this->record($locked, $actor, $type, $subject ?? $exceptEmail);

                return $result;
            });
        } catch (SeatLimitReached $e) {
            // Written after the rollback, so the refusal is kept even though the attempted change was not.
            $this->record($team, $actor, 'blocked', $subject ?? $exceptEmail, $refusal ?? []);

            throw $e;
        }
    }

    public function record(Team $team, ?User $actor, string $type, ?string $subject = null, array $details = []): void
    {
        TeamLicenceEvent::create([
            'team_id' => $team->getKey(), 'actor_id' => $actor?->getKey(), 'type' => $type,
            'subject' => $subject !== null ? mb_substr($subject, 0, 120) : null, 'details' => $details ?: null, 'created_at' => now(),
        ]);
    }

    /** @return Collection<int, TeamLicenceEvent> */
    public function history(Team $team, int $limit = 8)
    {
        return TeamLicenceEvent::where('team_id', $team->getKey())->orderByDesc('id')->limit($limit)->get();
    }

    /** What a person is told when the company is full: what is true, what happens next, and what is not happening (nobody is removed). */
    private function message(Team $team, array $s): string
    {
        $noun = fn (int $n) => $n === 1 ? 'licence' : 'licences';
        $base = "{$team->name} has no licence left: {$s['capacity']} ".$noun($s['capacity']).", {$s['people']} in use".($s['reserved'] > 0 ? " and {$s['reserved']} held by invitations that have not been answered" : '').'.';
        $price = $s['price'] ? self::formatMoney($s['price']['amount_minor_units'], $s['price']['currency']) : null;

        if ($s['can_buy']) {
            $next = ' Buy a licence to invite someone else'.($price ? " ({$price} per person per month, excluding VAT)" : '').'.';
        } else {
            $next = ' The owner holds the one free licence, and every other person needs a paid licence'.($price ? " ({$price} per person per month, excluding VAT)" : '').'. Paid licences cannot be bought just now: nothing has been charged and nobody has been removed. You can cancel an unanswered invitation to free its licence.';
        }

        if ($s['grandfathered']) {
            $next .= ' This company keeps its current people free until '.$s['grandfathered_until']->format('j F Y').'; adding anyone beyond them needs a paid licence.';
        }

        return $base.$next;
    }
}
