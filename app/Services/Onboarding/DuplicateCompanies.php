<?php

namespace App\Services\Onboarding;

use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Does a company that is about to be created look like one that exists (R-62 phase 2)? Three signs, in this order of weight: the same registration
 * number, the same website host, and (only among the person's OWN companies) the same name. A shared name alone never counts against a company the
 * person does not belong to: two businesses are allowed to be called the same, and saying so would reveal that the other one exists.
 */
class DuplicateCompanies
{
    /**
     * @param  array<string, mixed>  $values  name, website, registration_number as typed or extracted
     * @return array{own: Collection<int, Team>, elsewhere: bool}
     */
    public function find(User $user, array $values): array
    {
        $name = Str::lower(trim((string) ($values['name'] ?? '')));
        $host = self::host($values['website'] ?? null);
        $registration = self::registration($values['registration_number'] ?? null);

        // Fresh queries, not the loaded relations: a company created a moment ago in this same request must count.
        $mine = Team::query()->where('personal_team', false)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereHas('users', fn ($u) => $u->where('users.id', $user->id)))->get();
        $mineIds = $mine->pluck('id')->all();

        $own = $mine->filter(function (Team $team) use ($name, $host, $registration) {
            $profile = $team->profile()->first();

            return ($name !== '' && Str::lower(trim($team->name)) === $name)
                || ($host !== null && self::host($profile?->website) === $host)
                || ($registration !== null && self::registration($profile?->registration_number) === $registration);
        })->values();

        $elsewhere = false;
        if ($registration !== null) {
            $elsewhere = TeamProfile::query()->whereNotNull('registration_number')->whereNotIn('team_id', $mineIds)->get(['registration_number'])
                ->contains(fn ($p) => self::registration($p->registration_number) === $registration);
        }
        if (! $elsewhere && $host !== null) {
            $elsewhere = TeamProfile::query()->whereNotNull('website')->whereNotIn('team_id', $mineIds)->where('website', 'like', '%'.$host.'%')->get(['website'])
                ->contains(fn ($p) => self::host($p->website) === $host);
        }

        return ['own' => $own, 'elsewhere' => $elsewhere];
    }

    /** The host of a website as written (with or without a scheme or www.), lower case, or null when it is not a usable address. */
    public static function host(?string $website): ?string
    {
        $website = trim((string) $website);
        if ($website === '') {
            return null;
        }

        $host = parse_url(str_contains($website, '://') ? $website : 'https://'.$website, PHP_URL_HOST);
        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        return Str::lower(preg_replace('/^www\./i', '', $host));
    }

    /** A registration number with spaces, dashes and slashes removed and the case folded, or null when empty. */
    public static function registration(?string $number): ?string
    {
        $clean = Str::lower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $number));

        return $clean === '' ? null : $clean;
    }
}
