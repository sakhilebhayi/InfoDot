<?php

namespace App\Services\Identity;

use Illuminate\Support\Str;

/**
 * Turns the ways a business can write its name, registration number and web address into keys
 * that compare fairly (R-29 §15–16): case, spacing, punctuation, accents, legal suffixes and
 * common abbreviations stop mattering; look-alike characters collapse in a separate "skeleton".
 */
final class IdentityNormalizer
{
    /** Words that say what kind of entity it is, not which one. */
    private const LEGAL = [
        'proprietary limited', 'pty ltd', 'pty limited', 'pty', 'limited', 'ltd', 'llc', 'llp', 'plc', 'inc', 'incorporated',
        'corporation', 'corp', 'cc', 'close corporation', 'npc', 'nonprofit company', 'soc', 'rf', 'gmbh', 'bv', 'sa', 'sarl',
        'co', 'company', 'trading as', 't a', 'the',
    ];

    private const WORDS = ['&' => ' and ', '+' => ' and ', 'intl' => 'international', 'int\'l' => 'international', 'svcs' => 'services', 'svc' => 'services', 'mfg' => 'manufacturing', 'bros' => 'brothers', 'assoc' => 'associates'];

    /** "Acme Boreholes (Pty) Ltd." and "ACME  boreholes" both become "acme boreholes". */
    public static function name(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $s = ' '.mb_strtolower(Str::ascii($name)).' ';
        foreach (self::WORDS as $from => $to) {
            $s = str_replace($from, $to, $s);
        }
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? '';
        $s = ' '.trim($s).' ';
        foreach (self::LEGAL as $word) {
            $s = str_replace(' '.$word.' ', ' ', $s);
        }
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');

        return $s === '' ? null : $s;
    }

    /** The name with look-alike characters merged and spaces removed: "Acrne B0reholes" → same as "Acme Boreholes". */
    public static function skeleton(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $s = str_replace(' ', '', $key);
        $s = strtr(str_replace(['rn', 'vv', 'cl'], ['m', 'w', 'd'], $s), ['0' => 'o', '1' => 'l', 'i' => 'l', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '8' => 'b', 'z' => 's']);

        return preg_replace('/(.)\1+/', '$1', $s);
    }

    /** 0..1: how alike two name keys are (1 = the same). */
    public static function similarity(string $a, string $b): float
    {
        $max = max(strlen($a), strlen($b));

        return $max === 0 ? 1.0 : 1 - levenshtein(substr($a, 0, 255), substr($b, 0, 255)) / $max;
    }

    /** Registration number + country, hashed: compared, never stored in the clear. */
    public static function registrationHash(?string $number, ?string $country): ?string
    {
        $n = $number === null ? '' : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number) ?? '');

        return $n === '' ? null : hash('sha256', $n.'|'.(self::name($country) ?? ''));
    }

    public static function host(?string $url): ?string
    {
        $host = $url ? parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public static function emailDomain(?string $email): ?string
    {
        return $email && str_contains($email, '@') ? strtolower(Str::after($email, '@')) : null;
    }
}
