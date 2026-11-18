<?php

namespace App\Services\Licensing;

use App\Models\LicenceAssignment;
use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Who holds a licence in a company, and the owner's actions on that (R-53, decisions D5 and D6).
 *
 * One licence is one person in one company. The owner always holds the free one, and administrators are never lapsed. Everyone else is
 * licensed unless the ledger has marked them `lapsed` (payment ended) or `released` (taken back by the owner): a person with no row is
 * licensed, so companies that existed before licences keep working without a backfill. A person without a licence loses access to the
 * company's platforms and keeps their account, their content and their place in the company; buying or assigning a licence restores it.
 *
 * Nothing here deletes a person, a membership or any content.
 */
class LicenceAssignments
{
    public function __construct(private SeatLedger $ledger) {}

    /** The `licensed` check that sits beside `belongsToTeam`: may this person use the company's platforms? */
    public function isLicensed(Team $team, User $user): bool
    {
        if ($team->personal_team || (int) $team->user_id === (int) $user->getKey()) {
            return true;
        }

        if ($user->hasTeamRole($team, TeamRoles::ADMIN)) {
            return true;   // owner and administrators never lapse
        }

        return ! LicenceAssignment::where('team_id', $team->getKey())->where('user_id', $user->getKey())->whereIn('state', LicenceAssignment::WITHOUT)->exists();
    }

    /** People who hold a licence now: the owner and every member who is not lapsed or released. */
    public function licensedCount(Team $team): int
    {
        $without = LicenceAssignment::where('team_id', $team->getKey())->whereIn('state', LicenceAssignment::WITHOUT)
            ->whereIn('user_id', DB::table('team_user')->where('team_id', $team->getKey())->select('user_id'))->count();

        return 1 + $team->users()->count() - $without;
    }

    /** Makes the person a licence holder when they join. Called inside the ledger's locked transaction. */
    public function recordJoin(Team $team, User $user): void
    {
        LicenceAssignment::updateOrCreate(
            ['team_id' => $team->getKey(), 'user_id' => $user->getKey()],
            ['state' => LicenceAssignment::ACTIVE, 'batch_id' => $this->batchWithRoom($team)?->getKey(), 'assigned_at' => now(), 'released_at' => null],
        );
    }

    /** A person leaves or is removed: their assignment goes with the membership. Their account and content are untouched. */
    public function recordLeft(Team $team, User $user): void
    {
        LicenceAssignment::where('team_id', $team->getKey())->where('user_id', $user->getKey())->delete();
    }

    /**
     * The owner takes a licence back from a member (D5: the owner chooses who is released). The person stays in the company, loses platform
     * access, and can be given the licence again. Administrators cannot be released: change their role first.
     */
    public function release(Team $team, User $owner, User $member): LicenceAssignment
    {
        Gate::forUser($owner)->authorize('changeLicence', $team);

        return DB::transaction(function () use ($team, $owner, $member) {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();
            $this->mustBeOrdinaryMember($locked, $member);

            $row = $this->mark($locked, $member, LicenceAssignment::RELEASED);
            $this->ledger->record($locked, $owner, 'licence_released', $member->email, ['by' => 'owner']);
            app(LicenceLifecycle::class)->rebalance($locked);

            return $row;
        });
    }

    /** The owner gives a licence to a member who has none, if the company has one free. */
    public function assign(Team $team, User $owner, User $member): LicenceAssignment
    {
        Gate::forUser($owner)->authorize('changeLicence', $team);

        return DB::transaction(function () use ($team, $owner, $member) {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();
            $this->mustBeOrdinaryMember($locked, $member);

            return $this->activate($locked, $owner, $member, 'owner');
        });
    }

    /** Takes a licence from one member and gives it to another in one step: both happen or neither does. */
    public function reassign(Team $team, User $owner, User $from, User $to): LicenceAssignment
    {
        Gate::forUser($owner)->authorize('changeLicence', $team);

        return DB::transaction(function () use ($team, $owner, $from, $to) {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();
            $this->mustBeOrdinaryMember($locked, $from);
            $this->mustBeOrdinaryMember($locked, $to);

            $this->mark($locked, $from, LicenceAssignment::RELEASED);
            $this->ledger->record($locked, $owner, 'licence_released', $from->email, ['by' => 'owner', 'reassigned_to' => $to->email]);

            return $this->activate($locked, $owner, $to, 'owner');
        });
    }

    /**
     * Ownership moves to a member, so the free licence moves with it (D6). The new owner always holds a licence from now on. If that costs the
     * company a licence it did not have room for, the person who was the owner is the one without (unless they are an administrator, who are
     * never lapsed: then the company shows as over by one and nobody loses access). Called inside the ownership-transfer transaction.
     */
    public function swapFreeLicence(Team $team, User $newOwner, User $formerOwner, ?string $formerOwnerRole): void
    {
        $wasWithout = LicenceAssignment::where('team_id', $team->getKey())->where('user_id', $newOwner->getKey())->whereIn('state', LicenceAssignment::WITHOUT)->exists();
        LicenceAssignment::where('team_id', $team->getKey())->where('user_id', $newOwner->getKey())->delete();   // the owner is implicit

        LicenceAssignment::updateOrCreate(
            ['team_id' => $team->getKey(), 'user_id' => $formerOwner->getKey()],
            ['state' => LicenceAssignment::ACTIVE, 'batch_id' => null, 'assigned_at' => now(), 'released_at' => null],
        );
        $this->ledger->record($team, $formerOwner, 'ownership_licence_swapped', $newOwner->email, ['new_owner_was_without' => $wasWithout]);

        $capacity = $this->ledger->summary($team)['capacity'];

        if ($wasWithout && $this->licensedCount($team) > $capacity && $formerOwnerRole !== TeamRoles::ADMIN) {
            $this->mark($team, $formerOwner, LicenceAssignment::LAPSED);
            $this->ledger->record($team, null, 'licence_lapsed', $formerOwner->email, ['reason' => 'ownership_transfer']);
        }
    }

    /**
     * Lapses up to $count licence holders who are not owner or administrators, most recently added first. Returns how many lapsed.
     * Their membership, account and content are untouched.
     */
    public function lapseSurplus(Team $team, int $count, string $reason): int
    {
        $lapsed = 0;

        foreach ($this->lapseCandidates($team)->take(max(0, $count)) as $member) {
            $this->mark($team, $member, LicenceAssignment::LAPSED);
            $this->ledger->record($team, null, 'licence_lapsed', $member->email, ['reason' => $reason]);
            $lapsed++;
        }

        return $lapsed;
    }

    /** Restores lapsed (not released) people while there is room, longest-standing member first. Returns how many came back. */
    public function reactivateUpTo(Team $team, int $room): int
    {
        if ($room < 1) {
            return 0;
        }

        $back = 0;
        $rows = LicenceAssignment::where('team_id', $team->getKey())->where('state', LicenceAssignment::LAPSED)
            ->whereIn('user_id', DB::table('team_user')->where('team_id', $team->getKey())->select('user_id'))
            ->get()->sortBy(fn (LicenceAssignment $a) => (int) DB::table('team_user')->where('team_id', $a->team_id)->where('user_id', $a->user_id)->value('id'));

        foreach ($rows->take($room) as $row) {
            $row->forceFill(['state' => LicenceAssignment::ACTIVE, 'released_at' => null, 'assigned_at' => now(), 'batch_id' => $this->batchWithRoom($team)?->getKey()])->save();
            $this->ledger->record($team, null, 'licence_reactivated', User::whereKey($row->user_id)->value('email'), ['reason' => 'capacity']);
            $back++;
        }

        return $back;
    }

    /** Members who could lapse: licence holders who are neither administrators nor already without one, newest member first. */
    public function lapseCandidates(Team $team)
    {
        $members = DB::table('team_user')->where('team_id', $team->getKey())->orderByDesc('id')->get(['user_id', 'role']);
        $without = LicenceAssignment::where('team_id', $team->getKey())->whereIn('state', LicenceAssignment::WITHOUT)->pluck('user_id')->all();

        return $members
            ->filter(fn ($m) => TeamRoles::normalise($m->role) !== TeamRoles::ADMIN && ! in_array((int) $m->user_id, array_map('intval', $without), true))
            ->map(fn ($m) => User::find($m->user_id))->filter()->values();
    }

    private function activate(Team $team, User $actor, User $member, string $by): LicenceAssignment
    {
        $summary = $this->ledger->summary($team);
        $holdsOne = ! LicenceAssignment::where('team_id', $team->getKey())->where('user_id', $member->getKey())->whereIn('state', LicenceAssignment::WITHOUT)->exists();

        if (! $holdsOne && $this->licensedCount($team) + 1 > $summary['capacity']) {
            throw new LicenceRefused("{$team->name} has no licence free for this person. Buy another, or take one back from someone first. Nothing has been changed.");
        }

        $row = LicenceAssignment::updateOrCreate(
            ['team_id' => $team->getKey(), 'user_id' => $member->getKey()],
            ['state' => LicenceAssignment::ACTIVE, 'batch_id' => $this->batchWithRoom($team)?->getKey(), 'assigned_at' => now(), 'released_at' => null],
        );
        $this->ledger->record($team, $actor, 'licence_assigned', $member->email, ['by' => $by]);

        return $row;
    }

    private function mark(Team $team, User $member, string $state): LicenceAssignment
    {
        return LicenceAssignment::updateOrCreate(
            ['team_id' => $team->getKey(), 'user_id' => $member->getKey()],
            ['state' => $state, 'batch_id' => null, 'released_at' => now()],
        );
    }

    private function mustBeOrdinaryMember(Team $team, User $member): void
    {
        $role = DB::table('team_user')->where('team_id', $team->getKey())->where('user_id', $member->getKey())->value('role');
        $isMember = DB::table('team_user')->where('team_id', $team->getKey())->where('user_id', $member->getKey())->exists();

        if (! $isMember) {
            throw new LicenceRefused('That person is not in this company.');
        }

        if (TeamRoles::normalise($role) === TeamRoles::ADMIN) {
            throw new LicenceRefused('Administrators always keep their licence. Change their role first if you want to take it back.');
        }
    }

    /** The paid batch that still has an unassigned licence, oldest first, or null (the person is covered by capacity without a named batch). */
    private function batchWithRoom(Team $team): ?LicenceBatch
    {
        return LicenceBatch::where('team_id', $team->getKey())->whereIn('status', [LicenceBatch::ACTIVE, LicenceBatch::GRACE])->orderBy('id')->get()
            ->filter(fn (LicenceBatch $b) => $b->covers() && LicenceAssignment::where('batch_id', $b->id)->where('state', LicenceAssignment::ACTIVE)->count() < $b->quantity)
            ->first();
    }
}
