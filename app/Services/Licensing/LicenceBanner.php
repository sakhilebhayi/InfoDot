<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\User;
use App\Support\TeamRoles;

/**
 * The one banner an owner or administrator sees on every page while something about the company's licences needs attention (R-53 phase 4):
 * a payment that failed, a shortfall running out its 14 days, or the end of the free period for a frozen company. Nobody else sees it, and
 * it never says anything that the licences screen does not also say. The answer is remembered for the request, so a page with several
 * includes asks once.
 */
class LicenceBanner
{
    /** @var array<string, array{text: string, tone: string}|null> */
    private array $memo = [];

    public function __construct(private SeatLedger $ledger, private LicenceAssignments $assignments) {}

    /** @return array{text: string, tone: string}|null */
    public function for(?Team $team, ?User $user): ?array
    {
        if ($team === null || $user === null || $team->personal_team || ! TeamRoles::manages($user, $team)) {
            return null;
        }

        return $this->memo[$team->getKey().':'.$user->getKey()] ??= $this->compute($team, $user);
    }

    /** @return array{text: string, tone: string}|null */
    private function compute(Team $team, User $user): ?array
    {
        $day = fn ($d) => $d->timezone(config('app.timezone'))->format('j F Y');
        $tail = $user->ownsTeam($team) ? '' : ' Tell the owner.';

        // One query for the common case (nothing to say): the company's licence row plus, as a sub-select, whether any batch is in a grace.
        $licence = TeamLicence::where('team_id', $team->getKey())->addSelect(['*', 'failed_batch_id' => LicenceBatch::select('id')->whereColumn('licence_batches.team_id', 'team_licences.team_id')
            ->whereIn('status', [LicenceBatch::GRACE, LicenceBatch::REFUNDED])->whereNotNull('grace_until')->where('grace_until', '>', now())->orderBy('grace_until')->limit(1)])->first();

        if ($licence === null) {
            return null;
        }

        $failed = $licence->getAttribute('failed_batch_id') ? LicenceBatch::find($licence->getAttribute('failed_batch_id')) : null;

        if ($failed) {
            return ['tone' => 'warn', 'text' => ($failed->status === LicenceBatch::REFUNDED ? 'A licence payment was refunded.' : 'A licence payment failed.')." The {$failed->quantity} ".($failed->quantity === 1 ? 'licence keeps' : 'licences keep').' working until '.$day($failed->grace_until).'. After that the people beyond your licences lose access to the platforms. Nothing is deleted.'.$tail];
        }

        if ($licence->grace_until?->isFuture()) {
            $over = max(0, $this->assignments->licensedCount($team) - $this->ledger->summary($team)['capacity']);

            if ($over > 0) {
                return ['tone' => 'warn', 'text' => "{$team->name} has {$over} more ".($over === 1 ? 'person' : 'people').' than licences. They keep access until '.$day($licence->grace_until).', then the newest members without a licence lose it. Nothing is deleted.'.$tail];
            }
        }

        if ($licence->grandfathered_until?->isFuture() && $licence->grandfathered_until->lte(now()->addDays(30))) {
            return ['tone' => 'info', 'text' => 'The free period for the people already in '.$team->name.' ends on '.$day($licence->grandfathered_until).'. After that each person other than the owner needs a licence.'.$tail];
        }

        return null;
    }
}
