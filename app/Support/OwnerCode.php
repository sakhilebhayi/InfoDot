<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Str;

/**
 * A company's owner code: four capital letters, stencilled on everything the company
 * owns, the way a shipping container carries its owner's code. Derived from the
 * name, made unique, and never changed by a rename.
 */
final class OwnerCode
{
    private const NOISE = ['team', 'ltd', 'limited', 'pty', 'inc', 'corp', 'corporation', 'co', 'company', 'the', 'and', 'of', 'workspace', 'personal'];

    /**
     * @param  Closure(string): bool  $taken  whether a code is already used
     */
    public static function for(string $name, Closure $taken): string
    {
        $base = self::base($name);

        if (! $taken($base)) {
            return $base;
        }

        // Keep the first three letters and vary the last, then the first three of
        // the digits-and-letters space, so the code still reads as the name.
        foreach (array_merge(range('A', 'Z'), range('0', '9')) as $last) {
            $candidate = substr($base, 0, 3).$last;

            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = Str::upper(Str::random(4));
        } while ($taken($candidate));

        return $candidate;
    }

    private static function base(string $name): string
    {
        // "Sakhile's Team" -> "sakhile", "Northwind Finance (Pty) Ltd" -> northwind finance
        $clean = Str::of(Str::ascii($name))->lower()->replaceMatches("/'s\b/", '')->replaceMatches('/[^a-z0-9 ]+/', ' ');
        $words = collect(explode(' ', (string) $clean))
            ->filter(fn ($w) => $w !== '' && ! in_array($w, self::NOISE, true))
            ->values();

        $letters = match ($words->count()) {
            0 => 'info',
            1 => $words[0],
            default => substr($words[0], 0, 2).substr($words[1], 0, 2),
        };

        $code = Str::upper(substr(preg_replace('/[^a-z0-9]/', '', $letters), 0, 4));

        return str_pad($code, 4, 'X');
    }
}
