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
}
