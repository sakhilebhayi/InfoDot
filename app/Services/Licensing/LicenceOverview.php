<?php

namespace App\Services\Licensing;

use App\Models\LicenceAssignment;
use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamLicenceEvent;
use App\Support\TeamRoles;
use Illuminate\Support\Facades\DB;

/**
 * Everything the Team Licences screens show about one company, read in one place so every screen says the same thing. Read-only and always
 * scoped to the one company it is given. Money comes from batches (what was priced at purchase) and configuration (what more would cost);
 * nothing is invented.
 */
class LicenceOverview
{
    public function __construct(private SeatLedger $ledger, private LicencePricing $pricing, private LicenceAssignments $assignments) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Team $team): array
    {
        $seats = $this->ledger->summary($team);
        $held = $this->pricing->heldBy($team);

        return [
            'seats' => $seats,
            'owner' => $team->owner,
            'people' => $this->people($team),
            'batches' => $this->batches($team),
            'pending' => LicenceBatch::where('team_id', $team->getKey())->where('status', LicenceBatch::PENDING)->orderByDesc('id')->get(),
            'invitations' => TeamInvitation::live()->where('team_id', $team->getKey())->orderBy('email')->get(),
            'cost' => ['month' => $this->pricing->costOf($held, 'month'), 'year' => $this->pricing->costOf($held, 'year')],
            'held' => $held,
            'history' => $this->history($team),
            'events' => TeamLicenceEvent::where('team_id', $team->getKey())->where('type', 'not like', 'batch%')->orderByDesc('id')->limit(8)->get(),
            'unassigned_free' => max(0, $seats['capacity'] - $this->assignments->licensedCount($team) - $seats['reserved']),
        ];
    }

    /** One row per person in the company: the owner first, then everyone else by name. @return list<array<string, mixed>> */
    private function people(Team $team): array
    {
        $states = LicenceAssignment::where('team_id', $team->getKey())->pluck('state', 'user_id');
        $roles = DB::table('team_user')->where('team_id', $team->getKey())->pluck('role', 'user_id');
        $rows = [['user' => $team->owner, 'role' => 'owner', 'state' => 'free', 'locked' => true]];

        foreach ($team->users()->orderBy('name')->get() as $user) {
            $role = TeamRoles::normalise($roles[$user->getKey()] ?? null);
            $state = $states[$user->getKey()] ?? LicenceAssignment::ACTIVE;
            $rows[] = ['user' => $user, 'role' => $role, 'state' => $state, 'locked' => $role === TeamRoles::ADMIN];
        }

        return $rows;
    }

    /** Paid purchases that count or have counted, newest first. @return list<array<string, mixed>> */
    private function batches(Team $team): array
    {
        return LicenceBatch::where('team_id', $team->getKey())->where('status', '<>', LicenceBatch::PENDING)->orderByDesc('id')->get()
            ->map(fn (LicenceBatch $b) => [
                'batch' => $b,
                'counts' => $b->covers(),
                'assigned' => LicenceAssignment::where('batch_id', $b->getKey())->where('state', LicenceAssignment::ACTIVE)->count(),
                'renews' => $b->status === LicenceBatch::ACTIVE && $b->cancelled_at === null ? $b->period_end : null,
                'ends' => $b->status === LicenceBatch::ACTIVE && $b->cancelled_at !== null ? $b->period_end : null,
            ])->all();
    }

    /** Purchase history: batch events only, newest first. */
    private function history(Team $team)
    {
        return TeamLicenceEvent::where('team_id', $team->getKey())->where('type', 'like', 'batch%')->orderByDesc('id')->limit(12)->get();
    }

    /** The people who would lose a licence if `$count` fewer licences were held at the end of the period: newest ordinary members first. */
    public function whoWouldLose(Team $team, int $count)
    {
        return $this->assignments->lapseCandidates($team)->take(max(0, $count));
    }
}
