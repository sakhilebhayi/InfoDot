<?php

namespace App\Services\Onboarding;

use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\CompanyCatalogue;
use App\Services\Platforms\PlatformRecommendations;
use Illuminate\Support\Collection;

/**
 * R-62 phase 5: the first platforms a new company is offered to switch on, chosen from what is really free and really on.
 *
 * "Free and on" means the catalogue says the company can switch it on for nothing today (CompanyCatalogue::SWITCHABLE: free, or free while
 * pricing is finalised) and the platform is reachable. Platforms the company already holds (the free core included), paid ones, suspended,
 * not launched and temporarily unavailable ones are never offered, so the four are always platforms the company does not have yet.
 *
 * Order: the platforms the company's own aims, stage and size point to first (PlatformRecommender), then those its industry and activity point to
 * (PlatformRecommendations), then the rest in catalogue order. Personalisation only reorders; it never adds a platform that is not free and on.
 * Each pick carries the reason it is there, and nothing is chosen without one.
 */
class FirstFourPlatforms
{
    public const LIMIT = 4;

    public function __construct(
        private readonly CompanyCatalogue $catalogue,
        private readonly PlatformRecommender $aims,
        private readonly PlatformRecommendations $facts,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>> each pick has key, name, tagline, reasons (list of sentences) and row (the catalogue row)
     */
    public function for(User $user, Team $team): Collection
    {
        if (! $team->exists || $team->personal_team) {
            return collect();
        }

        $eligible = $this->catalogue->rows($user, $team)
            ->filter(fn (array $row) => ! $row['held'] && in_array($row['state'], CompanyCatalogue::SWITCHABLE, true));

        if ($eligible->isEmpty()) {
            return collect();
        }

        $byAims = collect($this->aims->for($team))->keyBy('key');
        $byActivity = $this->facts->for($user, $team, $this->catalogue)->keyBy('key');

        $position = 0;

        return $eligible
            ->map(function (array $row, string $key) use ($byAims, $byActivity, &$position) {
                $reasons = [];
                $score = 0;

                if ($aim = $byAims->get($key)) {
                    $score += 1000 + (int) $aim['score'];
                    array_push($reasons, ...$aim['why']);
                }
                if ($rule = $byActivity->get($key)) {
                    $score += 500;
                    $reasons[] = $rule['reason'];
                }
                if ($reasons === []) {
                    $reasons[] = $row['state'] === 'free_to_activate'
                        ? $row['name'].' is free to switch on.'
                        : 'No price has been set for '.$row['name'].', so it is free for now.';
                }

                return ['score' => $score, 'order' => $position++, 'pick' => $this->pick($key, $row, $reasons)];
            })
            ->sortBy(fn (array $entry) => [-$entry['score'], $entry['order']])
            ->take(self::LIMIT)
            ->map(fn (array $entry) => $entry['pick'])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $reasons
     * @return array<string, mixed>
     */
    private function pick(string $key, array $row, array $reasons): array
    {
        return ['key' => $key, 'name' => (string) $row['name'], 'tagline' => (string) $row['tagline'], 'reasons' => $reasons, 'row' => $row];
    }
}
