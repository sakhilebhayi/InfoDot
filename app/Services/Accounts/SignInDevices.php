<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * R-72 (S-06/E-04): recognises the browsers a person signs in from. A sign-in from one not seen before is worth telling them about,
 * unless it is the first sign-in InfoDot has recorded for them (otherwise everyone would be warned the first time after this shipped).
 * The fingerprint is a hash of the browser, the operating system and the first part of the network address; the address itself is
 * never stored.
 */
class SignInDevices
{
    /** Records this sign-in. Returns the device's label when it is new for someone who has signed in before, otherwise null. */
    public function record(User $user, Request $request): ?string
    {
        $agent = (string) $request->userAgent();
        $label = self::label($agent);
        $fingerprint = hash('sha256', $label.'|'.self::networkPrefix((string) $request->ip()).'|'.config('app.key'));
        $now = now();

        $known = DB::table('user_devices')->where('user_id', $user->getKey());

        if ((clone $known)->where('fingerprint', $fingerprint)->update(['last_seen_at' => $now]) > 0) {
            return null;
        }

        $hadAny = (clone $known)->exists();

        try {
            DB::table('user_devices')->insert(['user_id' => $user->getKey(), 'fingerprint' => $fingerprint, 'label' => $label, 'first_seen_at' => $now, 'last_seen_at' => $now]);
        } catch (UniqueConstraintViolationException) {
            return null; // the same sign-in recorded twice at once
        }

        return $hadAny ? $label : null;
    }

    /** "Chrome on Windows", from the user agent; good enough for a person to recognise, never used for anything else. */
    public static function label(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'A browser',
        };
        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone or iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'a Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'an unknown system',
        };

        return $browser.' on '.$system;
    }

    /** The first two parts of an IPv4 address, or the first three of an IPv6 one: a network, not a person. */
    private static function networkPrefix(string $ip): string
    {
        return str_contains($ip, ':') ? implode(':', array_slice(explode(':', $ip), 0, 3)) : implode('.', array_slice(explode('.', $ip), 0, 2));
    }
}
