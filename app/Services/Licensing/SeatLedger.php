<?php

namespace App\Services\Licensing;

use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use Closure;
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

    public function licenceFor(Team $team): TeamLicence
    {
        return TeamLicence::firstOrCreate(['team_id' => $team->getKey()], ['billing_status' => 'free']);
    }

    /**
     * @return array{applies: bool, included: int, purchased: int, capacity: int, people: int, members: int, reserved: int, used: int, available: int, over_by: int, extra_in_use: int, status: string, grace_until: mixed, price: ?array{amount_minor_units: int, currency: string}, can_buy: bool}
     */
    public function summary(Team $team): array
    {
        $licence = $this->licenceFor($team);
        $included = $licence->included_seats ?? (int) config('licensing.included_seats', 3);
        $members = $team->users()->count();
        $people = $members + 1; // the owner holds a seat
        $reserved = config('licensing.pending_invitations_reserve_a_seat', true) ? $team->teamInvitations()->count() : 0;
        $capacity = $included + $licence->purchased_seats;
        $used = $people + $reserved;
        $price = $this->price();

        return [
            'applies' => $this->applies($team),
            'included' => $included,
            'purchased' => $licence->purchased_seats,
            'capacity' => $capacity,
            'people' => $people,
            'members' => $members,
            'reserved' => $reserved,
            'used' => $used,
            'available' => max(0, $capacity - $used),
            'over_by' => max(0, $people - $capacity),
            'extra_in_use' => max(0, $people - $included),
            'status' => $licence->billing_status,
            'grace_until' => $licence->grace_until,
            'price' => $price,
            'can_buy' => $price !== null && (bool) config('licensing.billing_connected', false),
        ];
    }

    /** The approved price of one extra seat, or null when no price has been decided (it is never defaulted). */
    public function price(): ?array
    {
        $amount = config('licensing.price.amount_minor_units');
        $currency = config('licensing.price.currency');

        return filled($amount) && filled($currency) ? ['amount_minor_units' => (int) $amount, 'currency' => (string) $currency] : null;
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
        app(BillingEntitlements::class)->sync($team);

        try {
            return DB::transaction(function () use ($team, $change, $exceptEmail, $actor, $type, $subject, &$refusal) {
                $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();

                if ($this->applies($locked)) {
                    $s = $this->summary($locked);
                    $holdsThisSeat = $exceptEmail !== null
                        && config('licensing.pending_invitations_reserve_a_seat', true)
                        && $locked->teamInvitations()->where('email', $exceptEmail)->exists();
                    $reserved = $s['reserved'] - ($holdsThisSeat ? 1 : 0);

                    if ($s['people'] + $reserved + 1 > $s['capacity']) {
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

    /** @return \Illuminate\Support\Collection<int, TeamLicenceEvent> */
    public function history(Team $team, int $limit = 8)
    {
        return TeamLicenceEvent::where('team_id', $team->getKey())->orderByDesc('id')->limit($limit)->get();
    }

    /** What a person is told when the company is full: what is true, what happens next, and what is not happening (nobody is removed). */
    private function message(Team $team, array $s): string
    {
        $base = "{$team->name} has no seat left: {$s['capacity']} ".($s['capacity'] === 1 ? 'seat' : 'seats').", {$s['people']} in use".($s['reserved'] > 0 ? " and {$s['reserved']} held by invitations that have not been answered" : '').'.';
        $next = $s['can_buy']
            ? ' Add a seat to invite someone else.'
            : ($s['included'] <= $s['capacity'] && $s['price'] === null
                ? ' Seats beyond the free '.$s['included'].' are paid, and paid seats are not available yet: nothing has been charged and nobody has been removed. You can cancel an unanswered invitation to free its seat.'
                : ' Paid seats cannot be bought just now: nothing has been charged and nobody has been removed. You can cancel an unanswered invitation to free its seat.');

        return $base.$next;
    }
}
