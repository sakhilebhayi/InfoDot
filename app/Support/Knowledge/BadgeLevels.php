<?php

namespace App\Support\Knowledge;

/**
 * Which specialist badge level a number of qualifying work items earns. Pure: numbers in, a level key out, so every boundary
 * can be tested with integers. The levels are config/achievements.php `badges.levels`.
 */
final class BadgeLevels
{
    /** @param array<string, array{name: string, min_work: int}>|null $levels */
    public function __construct(private readonly ?array $levels = null) {}

    /** Lowest first. @return array<string, array{name: string, min_work: int}> */
    public function all(): array
    {
        $levels = $this->levels ?? config('achievements.badges.levels', []);
        uasort($levels, fn (array $a, array $b) => $a['min_work'] <=> $b['min_work']);

        return $levels;
    }

    public function version(): int
    {
        return (int) config('achievements.badges.version', 1);
    }

    /** The highest level this many items reach, or null when not even the lowest is reached. */
    public function levelFor(int $work): ?string
    {
        $reached = null;

        foreach ($this->all() as $key => $level) {
            if ($work >= max(1, (int) $level['min_work'])) {
                $reached = $key;
            }
        }

        return $reached;
    }

    /** 0 for an unknown level, 1 for the lowest, and so on. Used to pick the highest of several. */
    public function rank(string $level): int
    {
        $i = array_search($level, array_keys($this->all()), true);

        return $i === false ? 0 : $i + 1;
    }

    public function name(string $level): string
    {
        return $this->all()[$level]['name'] ?? ucfirst($level);
    }
}
