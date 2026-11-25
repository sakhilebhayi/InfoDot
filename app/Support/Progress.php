<?php

namespace App\Support;

/**
 * The one place progress figures are worked out (R-64 phase 5). A progress indicator shows only a figure that was calculated from real data: where there
 * is nothing to measure against (no steps, no capacity), the answer is null and the screen draws nothing rather than an empty or invented bar.
 */
final class Progress
{
    /** Whole percent from 0 to 100, or null when `max` is not a positive number. A value past the maximum counts as 100; a negative one as 0. */
    public static function percent(float|int $value, float|int $max): ?int
    {
        if ($max <= 0) {
            return null;
        }

        return (int) round(max(0.0, min(1.0, $value / $max)) * 100);
    }

    /**
     * Done and total of a list of steps, each with a boolean `done`. Null total when there are no steps.
     *
     * @param  iterable<array{done: bool}>  $steps
     * @return array{done: int, total: int, percent: ?int}
     */
    public static function ofSteps(iterable $steps): array
    {
        $done = 0;
        $total = 0;

        foreach ($steps as $step) {
            $total++;
            $done += $step['done'] ? 1 : 0;
        }

        return ['done' => $done, 'total' => $total, 'percent' => self::percent($done, $total)];
    }
}
