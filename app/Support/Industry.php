<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Support\Arr;

/**
 * What a company does, and the examples that follow from it. See config/industries.php.
 * Everything falls back to `general`, so an unset or unknown industry still has a full set.
 */
final class Industry
{
    public const FALLBACK = 'general';

    /** @return array<string, string> key => label, general first */
    public static function options(): array
    {
        return collect(config('industries'))->map(fn ($i) => $i['label'])->all();
    }

    public static function valid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, config('industries'));
    }

    /**
     * The examples for a company, with the general set filling any gap.
     *
     * @return array{key: string, label: string, searches: list<string>, questions: list<string>, solutions: list<string>, ask_title: string, ask_details: string, solution_title: string, solution_description: string, solution_tags: string}
     */
    public static function for(?Team $team): array
    {
        $key = self::valid($team?->industry) ? $team->industry : self::FALLBACK;
        $all = config('industries');

        return ['key' => $key] + array_replace($all[self::FALLBACK], Arr::only($all[$key], array_keys($all[self::FALLBACK])));
    }
}
