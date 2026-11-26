<?php

namespace App\Services\Growth;

use App\Models\OnboardingEvent;
use Carbon\CarbonInterface;

/**
 * R-59 Phase 1B: how many people reached each onboarding step, and what share of the people at the step it follows (see config/onboarding.php) reached it.
 * Counts are people, from the events as they were written; nothing is estimated.
 */
class OnboardingFunnel
{
    /** @return list<array{step: string, label: string, people: int, parent: ?string, of_parent: ?float, of_first: ?float}> */
    public function steps(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $counts = OnboardingEvent::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->selectRaw('step, count(distinct user_id) as people')
            ->groupBy('step')
            ->pluck('people', 'step');

        $labels = config('onboarding.steps');
        $parents = config('onboarding.parents');
        $first = (int) ($counts[array_key_first($labels)] ?? 0);
        $out = [];

        foreach ($labels as $step => $label) {
            $people = (int) ($counts[$step] ?? 0);
            $parent = $parents[$step] ?? null;
            $parentPeople = $parent === null ? null : (int) ($counts[$parent] ?? 0);
            $out[] = [
                'step' => $step,
                'label' => $label,
                'people' => $people,
                'parent' => $parent === null ? null : $labels[$parent],
                'of_parent' => $parentPeople === null || $parentPeople === 0 ? null : min(100.0, round($people / $parentPeople * 100, 1)),
                'of_first' => $first > 0 ? round($people / $first * 100, 1) : null,
            ];
        }

        return $out;
    }

    /** R-73: where a sign-up came from on the homepage, and the steps after it that mean the person actually got going. */
    public const SOURCE_LABELS = ['hero' => 'Homepage: the top button', 'start' => 'Homepage: the Start tab', 'close' => 'Homepage: the final call', 'other' => 'Anywhere else'];

    public const SOURCE_STEPS = ['email_verified', 'business_created', 'company_joined', 'platform_opened'];

    /**
     * People who created an account in the period, by the homepage button they came from (recorded with account_created since R-73),
     * and how many of each group went on to the steps that matter. A count of people, never of clicks.
     *
     * @return list<array{source: string, label: string, accounts: int, steps: array<string, int>}>
     */
    public function bySignupSource(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $groups = [];
        OnboardingEvent::query()->where('step', 'account_created')
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->get(['user_id', 'meta'])
            ->each(function (OnboardingEvent $event) use (&$groups): void {
                $from = $event->meta['from'] ?? null;
                $groups[is_string($from) && isset(self::SOURCE_LABELS[$from]) ? $from : 'other'][] = (int) $event->user_id;
            });

        $out = [];
        foreach (self::SOURCE_LABELS as $source => $label) {
            $ids = array_values(array_unique($groups[$source] ?? []));
            $reached = $ids === [] ? collect() : OnboardingEvent::query()->whereIn('user_id', $ids)->whereIn('step', self::SOURCE_STEPS)
                ->selectRaw('step, count(distinct user_id) as people')->groupBy('step')->pluck('people', 'step');
            $out[] = [
                'source' => $source,
                'label' => $label,
                'accounts' => count($ids),
                'steps' => collect(self::SOURCE_STEPS)->mapWithKeys(fn (string $step) => [$step => (int) ($reached[$step] ?? 0)])->all(),
            ];
        }

        return $out;
    }
}
