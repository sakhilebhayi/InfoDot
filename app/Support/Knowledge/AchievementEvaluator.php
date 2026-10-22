<?php

namespace App\Support\Knowledge;

/**
 * Decides which milestones a set of counts meets. Pure: counts in, answers out, no database and no clock, so every
 * boundary can be tested with arrays. The catalogue is config/achievements.php.
 */
final class AchievementEvaluator
{
    /** @param array<string, array{name: string, meaning: string, metric: string, threshold: int}>|null $catalogue */
    public function __construct(private readonly ?array $catalogue = null) {}

    /** @return array<string, array{name: string, meaning: string, metric: string, threshold: int}> */
    public function catalogue(): array
    {
        return $this->catalogue ?? config('achievements.achievements', []);
    }

    public function version(): int
    {
        return (int) config('achievements.version', 1);
    }

    /**
     * Which milestones these counts meet, in catalogue order.
     *
     * @param  array<string, int>  $counts  metric => number of current qualifying items
     * @return array<string, bool>
     */
    public function met(array $counts): array
    {
        $met = [];

        foreach ($this->catalogue() as $key => $rule) {
            $met[$key] = max(0, (int) ($counts[$rule['metric']] ?? 0)) >= $rule['threshold'];
        }

        return $met;
    }

    /**
     * The next milestone of each metric that is not met yet, with how far along it is. Never more than one per metric,
     * so the list stays short and calm.
     *
     * @param  array<string, int>  $counts
     * @return list<array{key: string, name: string, meaning: string, metric: string, current: int, target: int}>
     */
    public function next(array $counts): array
    {
        $byMetric = [];

        foreach ($this->catalogue() as $key => $rule) {
            $current = max(0, (int) ($counts[$rule['metric']] ?? 0));

            if ($current >= $rule['threshold']) {
                continue;
            }

            $best = $byMetric[$rule['metric']] ?? null;

            if ($best === null || $rule['threshold'] < $best['target']) {
                $byMetric[$rule['metric']] = [
                    'key' => $key, 'name' => $rule['name'], 'meaning' => $rule['meaning'],
                    'metric' => $rule['metric'], 'current' => $current, 'target' => $rule['threshold'],
                ];
            }
        }

        return array_values($byMetric);
    }
}
