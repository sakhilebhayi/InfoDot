<?php

namespace App\Services\Growth;

use App\Models\AcquisitionTouch;
use App\Models\GrowthTarget;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralTier;
use App\Models\TeamProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * R-59 Phase 1D: every figure the Growth Centre shows, counted from the records InfoDot already keeps. Nothing is estimated or
 * invented. What InfoDot cannot know yet (revenue, cost of acquiring a business, retention) is listed in unmeasured() so the page
 * says "not yet measured" instead of showing a number.
 */
class GrowthMetrics
{
    public function __construct(private readonly OnboardingFunnel $funnel) {}

    /** @return array{accounts: int, new_accounts: int, verified: int, businesses: int, new_businesses: int, platform_openers: int} */
    public function overview(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $users = fn () => User::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
        $businesses = fn () => DB::table('teams')->where('personal_team', false)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        return [
            'accounts' => User::count(),
            'new_accounts' => $users()->count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'businesses' => DB::table('teams')->where('personal_team', false)->count(),
            'new_businesses' => $businesses()->count(),
            'platform_openers' => User::whereNotNull('platform_opened_at')->count(),
        ];
    }

    /** @return list<array{step: string, label: string, people: int, of_previous: ?float, of_first: ?float}> */
    public function funnel(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return $this->funnel->steps($from, $to);
    }

    /** @return array{rows: list<array{channel: string, people: int, share: float}>, known: int, unknown: int} */
    public function channels(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $touches = AcquisitionTouch::query()
            ->when($from, fn ($q) => $q->where('touched_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('touched_at', '<=', $to));

        $counts = (clone $touches)->selectRaw('channel, count(*) as people')->groupBy('channel')->orderByDesc('people')->pluck('people', 'channel');
        $known = (int) $counts->sum();
        $accounts = User::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->count();

        $rows = [];
        foreach ($counts as $channel => $people) {
            $rows[] = ['channel' => (string) $channel, 'people' => (int) $people, 'share' => $known > 0 ? round($people / $known * 100, 1) : 0.0];
        }

        // Accounts made before arrivals were recorded, or whose arrival was not recorded, are not guessed into a channel.
        return ['rows' => $rows, 'known' => $known, 'unknown' => max(0, $accounts - $known)];
    }

    /** Where businesses say they are registered. Only what a business has stated; blank is counted as blank. @return array{rows: list<array{country: string, businesses: int}>, stated: int, unstated: int} */
    public function geography(): array
    {
        $counts = TeamProfile::query()->whereNotNull('registration_country')->where('registration_country', '!=', '')
            ->selectRaw('registration_country as country, count(*) as businesses')->groupBy('registration_country')->orderByDesc('businesses')->get();
        $stated = (int) $counts->sum('businesses');
        $all = (int) DB::table('teams')->where('personal_team', false)->count();

        return [
            'rows' => $counts->map(fn ($r) => ['country' => (string) $r->country, 'businesses' => (int) $r->businesses])->all(),
            'stated' => $stated,
            'unstated' => max(0, $all - $stated),
        ];
    }

    /** Businesses holding each platform now, by the platform's key (entitlements that are active or on trial). @return list<array{platform: string, businesses: int}> */
    public function platforms(): array
    {
        return DB::table('platform_entitlements')->whereIn('status', ['active', 'trial'])
            ->selectRaw('platform_key as platform, count(*) as businesses')->groupBy('platform_key')->orderByDesc('businesses')->get()
            ->map(fn ($r) => ['platform' => (string) $r->platform, 'businesses' => (int) $r->businesses])->all();
    }

    /** @return array<string, array{label: string, target: ?int, target_date: ?string, actual: int, progress: ?float}> */
    public function targets(): array
    {
        $overview = $this->overview(null, null);
        $set = GrowthTarget::all()->keyBy('metric');
        $out = [];

        foreach (GrowthTarget::METRICS as $metric => $label) {
            $actual = (int) $overview[$metric];
            $row = $set->get($metric);
            $target = $row?->value;
            $out[$metric] = [
                'label' => $label,
                'target' => $target,
                'target_date' => $row?->target_date?->format('j M Y'),
                'actual' => $actual,
                'progress' => $target ? round(min(100, $actual / $target * 100), 1) : null,
            ];
        }

        return $out;
    }

    /** @return array{states: array<string,int>, rewards: array<string,int>, tiers: list<ReferralTier>} */
    public function referrals(): array
    {
        return [
            'states' => Referral::selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state')->map(fn ($n) => (int) $n)->all(),
            'rewards' => ReferralReward::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
            'tiers' => ReferralTier::orderBy('referrals_required')->get()->all(),
        ];
    }

    /** What InfoDot cannot measure yet, and why. @return array<string, string> */
    public function unmeasured(): array
    {
        return [
            'Monthly recurring revenue' => 'Payments are taken by Dot.Finance and no recurring price is set yet.',
            'Cost of acquiring a business' => 'No marketing spend is recorded in InfoDot.',
            'Retention' => 'The definition of an active business is still to be agreed.',
        ];
    }
}
