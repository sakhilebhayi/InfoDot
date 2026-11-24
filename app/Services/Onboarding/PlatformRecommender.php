<?php

namespace App\Services\Onboarding;

use App\Models\Team;
use App\Support\BusinessTools;

/**
 * R-59 Phase 1B: which platforms to put in front of a new business first. Deterministic and explainable: each recommendation carries the
 * reasons it was made (the aims the business chose, its stage, its size). It reads only config/onboarding.php and the live platform
 * registry; a platform that is not live, or is hidden, is never recommended, and no platform is shown without a reason.
 */
class PlatformRecommender
{
    /** @return list<array{key: string, name: string, tagline: string, why: list<string>, score: int}> */
    public function for(Team $team): array
    {
        $profile = $team->profile()->first();

        return $this->recommend(
            array_values(array_intersect((array) ($profile->objectives ?? []), array_keys(config('onboarding.objectives')))),
            $profile?->stage,
            $profile?->size_band,
        );
    }

    /** @return list<array{key: string, name: string, tagline: string, why: list<string>, score: int}> */
    public function recommend(array $objectives, ?string $stage, ?string $sizeBand): array
    {
        $scores = [];
        $why = [];
        $add = function (string $key, int $points, string $reason) use (&$scores, &$why) {
            if (! BusinessTools::shown($key)) {
                return;
            }
            $scores[$key] = ($scores[$key] ?? 0) + $points;
            $why[$key][] = $reason;
        };

        foreach ($objectives as $objective) {
            $entry = config('onboarding.objectives.'.$objective);
            foreach ($entry['platforms'] ?? [] as $position => $key) {
                $add($key, 20 - $position * 4, 'You want to: '.mb_strtolower($entry['label']).'.');
            }
        }

        if ($stage !== null && isset(config('onboarding.stages')[$stage])) {
            foreach (config('onboarding.stage_boost.'.$stage, []) as $key) {
                $add($key, 3, 'A common first step when you are at the stage "'.mb_strtolower(config('onboarding.stages')[$stage]).'".');
            }
        }

        if ($sizeBand !== null && isset(config('onboarding.size_bands')[$sizeBand])) {
            foreach (config('onboarding.size_boost.'.$sizeBand, []) as $key) {
                $add($key, 2, 'Useful for a business of this size ('.mb_strtolower(config('onboarding.size_bands')[$sizeBand]).').');
            }
        }

        if ($scores === []) {
            foreach (config('onboarding.starter') as $position => $key) {
                $add($key, 10 - $position, 'Most businesses start here.');
            }
        }

        // Highest score first; ties keep the order of the configuration so the result never changes between requests.
        $order = array_flip(array_keys($scores));
        uksort($scores, fn ($a, $b) => [$scores[$b], $order[$a]] <=> [$scores[$a], $order[$b]]);

        $catalogue = BusinessTools::catalogue();
        $out = [];

        foreach (array_slice($scores, 0, (int) config('onboarding.max_recommendations'), true) as $key => $score) {
            $out[] = [
                'key' => $key,
                'name' => (string) config('ecosystem.platforms.'.$key.'.name', $catalogue[$key]['name'] ?? $key),
                'tagline' => (string) ($catalogue[$key]['tagline'] ?? ''),
                'why' => array_values(array_unique($why[$key])),
                'score' => $score,
            ];
        }

        return $out;
    }
}
