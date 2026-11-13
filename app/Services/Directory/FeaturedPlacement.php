<?php

namespace App\Services\Directory;

use App\Models\BusinessFeature;
use App\Models\BusinessInvestigation;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Verification\VerificationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Featured placements (R-33 §8), under the rules published at /businesses/featured: a business that
 * meets every rule may ask; a reviewer outside it grants a placement of up to 30 days or declines
 * with a reason. Being featured says nothing about quality or verification, and the directory says
 * so beside every featured business. Paid featuring is not offered: payments are not live and its
 * price is the owner's decision.
 */
final class FeaturedPlacement
{
    /** The rules, as published. Each one is checked, never assumed. */
    public const RULES = [
        'public' => 'Listed for anyone, so every visitor can open its page',
        'described' => 'Says what it does',
        'service' => 'Lists at least one service or product',
        'logo' => 'Shows its logo',
        'clear' => 'Is not under review by InfoDot',
    ];

    public const SHOWN = 3;

    public function __construct(private InAppNotices $notices, private VerificationService $verification) {}

    /** The rules this business does not meet yet. @return list<string> rule keys */
    public function unmet(Team $team): array
    {
        $p = $team->profile;
        $missing = [];

        if ($p?->visibility !== TeamProfile::PUBLIC) {
            $missing[] = 'public';
        }
        if (! filled($p?->description)) {
            $missing[] = 'described';
        }
        if (! $team->services()->exists()) {
            $missing[] = 'service';
        }
        if ($team->currentMedia('logo') === null) {
            $missing[] = 'logo';
        }
        if ($team->isRestricted() || BusinessInvestigation::where('team_id', $team->id)->where('status', BusinessInvestigation::OPEN)->exists()) {
            $missing[] = 'clear';
        }

        return $missing;
    }

    public function current(Team $team): ?BusinessFeature
    {
        return BusinessFeature::where('team_id', $team->id)->whereIn('status', [BusinessFeature::REQUESTED, BusinessFeature::ACTIVE])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->latest('id')->first();
    }

    public function request(User $manager, Team $team, ?string $reason): BusinessFeature
    {
        abort_unless($manager->ownsTeam($team) || $manager->hasTeamRole($team, 'admin'), 403);

        if ($missing = $this->unmet($team)) {
            throw ValidationException::withMessages(['reason' => 'Not yet: '.implode('; ', array_map(fn ($k) => mb_strtolower(self::RULES[$k]), $missing)).'.']);
        }
        if ($this->current($team) !== null) {
            throw ValidationException::withMessages(['reason' => $team->name.' already has a request or a placement.']);
        }

        $feature = new BusinessFeature(['reason' => $reason ?: null]);
        $feature->team_id = $team->id;
        $feature->status = BusinessFeature::REQUESTED;
        $feature->basis = 'editorial';
        $feature->requested_by = $manager->id;
        $feature->save();

        User::where('is_moderator', true)->get()->reject(fn (User $u) => $u->belongsToTeam($team))
            ->each(fn (User $u) => $this->notices->record(UserNotice::BUSINESS_TRUST_QUEUE, $u, 'business', $team->id, 'bt:feature:'.$feature->id));

        return $feature;
    }

    public function grant(User $reviewer, BusinessFeature $feature, int $days, string $note): void
    {
        abort_unless($this->verification->canReview($reviewer, $feature->team) && $feature->status === BusinessFeature::REQUESTED, 403);
        if ($missing = $this->unmet($feature->team)) {
            throw ValidationException::withMessages(['note' => 'It no longer meets: '.implode('; ', array_map(fn ($k) => mb_strtolower(self::RULES[$k]), $missing)).'.']);
        }

        $feature->forceFill([
            'status' => BusinessFeature::ACTIVE,
            'decided_by' => $reviewer->id,
            'decision_note' => $note,
            'starts_at' => now(),
            'ends_at' => now()->addDays(max(1, min(BusinessFeature::MAX_DAYS, $days))),
        ])->save();
        $this->tell($feature);
    }

    public function decline(User $reviewer, BusinessFeature $feature, string $note): void
    {
        abort_unless($this->verification->canReview($reviewer, $feature->team) && $feature->status === BusinessFeature::REQUESTED, 403);
        $feature->forceFill(['status' => BusinessFeature::DECLINED, 'decided_by' => $reviewer->id, 'decision_note' => $note])->save();
        $this->tell($feature);
    }

    public function end(User $reviewer, BusinessFeature $feature): void
    {
        abort_unless($this->verification->canReview($reviewer, $feature->team) && $feature->status === BusinessFeature::ACTIVE, 403);
        $feature->forceFill(['status' => BusinessFeature::ENDED, 'ends_at' => now()])->save();
        $this->tell($feature);
    }

    /**
     * Featured businesses to show now: placements in their dates whose business still meets the
     * rules and may be seen by this viewer. The order rotates daily, so no placement is always first.
     *
     * @return Collection<int, Team>
     */
    public function showing(?User $viewer): Collection
    {
        $ids = BusinessFeature::query()->showing()->pluck('team_id')->unique();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Team::query()->listedFor($viewer)->whereIn('id', $ids)->with(['profile', 'categories', 'services', 'standing', 'logo'])->get()
            ->filter(fn (Team $t) => $t->profile?->visibility === TeamProfile::PUBLIC)
            ->sortBy(fn (Team $t) => crc32(now()->toDateString().':'.$t->id))
            ->take(self::SHOWN)->values();
    }

    private function tell(BusinessFeature $feature): void
    {
        $team = $feature->team;
        collect([$team->owner])->filter()->concat($team->users()->wherePivot('role', 'admin')->get())->unique('id')
            ->each(fn ($u) => $u instanceof User && $this->notices->record(UserNotice::BUSINESS_STANDING, $u, 'business', $team->id, 'bs:feature:'.$feature->id.':'.$feature->status));
    }
}
