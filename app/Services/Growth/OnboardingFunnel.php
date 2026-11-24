<?php

namespace App\Services\Growth;

use App\Models\OnboardingEvent;
use Carbon\CarbonInterface;

/**
 * R-59 Phase 1B: how many people reached each onboarding step, and what share of the people at the step before went on to the next one.
 * Counts are people, from the events as they were written; nothing is estimated.
 */
class OnboardingFunnel
{
    /** @return list<array{step: string, label: string, people: int, of_previous: ?float, of_first: ?float}> */
    public function steps(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $counts = OnboardingEvent::query()
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->selectRaw('step, count(distinct user_id) as people')
            ->groupBy('step')
            ->pluck('people', 'step');

        $out = [];
        $first = null;
        $previous = null;

        foreach (config('onboarding.steps') as $step => $label) {
            $people = (int) ($counts[$step] ?? 0);
            $first ??= $people;
            $out[] = [
                'step' => $step,
                'label' => $label,
                'people' => $people,
                'of_previous' => $previous === null ? null : ($previous > 0 ? round($people / $previous * 100, 1) : null),
                'of_first' => $first > 0 ? round($people / $first * 100, 1) : null,
            ];
            $previous = $people;
        }

        return $out;
    }
}
