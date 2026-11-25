<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * R-66: which of a person's companies a booking or a message is for. A person may act only for a company they belong to, never for a personal
 * workspace and never for the business they are writing to. "Just me" (an empty value) is always allowed.
 */
final class ActingCompany
{
    public const FIELD = 'for_company';

    /** @return Collection<int, Team> the companies this person may act for towards $target, by name */
    public static function options(User $user, Team $target): Collection
    {
        return $user->allTeams()
            ->filter(fn (Team $team) => ! $team->personal_team && (int) $team->getKey() !== (int) $target->getKey())
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** The choice to show first: the company the person is working in, or their only company. */
    public static function preselected(User $user, Team $target): ?int
    {
        $options = self::options($user, $target);
        $current = $options->firstWhere('id', $user->current_team_id);

        return $current?->getKey() ?? ($options->count() === 1 ? $options->first()->getKey() : null);
    }

    /** The company chosen in the request, checked against the person's own companies; null for "just me". */
    public static function resolve(User $user, Team $target, mixed $input): ?Team
    {
        if ($input === null || $input === '' || $input === 'me') {
            return null;
        }

        $team = is_scalar($input) && ctype_digit((string) $input) ? self::options($user, $target)->firstWhere('id', (int) $input) : null;

        if ($team === null) {
            throw ValidationException::withMessages([self::FIELD => 'Choose one of your own companies, or just you.']);
        }

        return $team;
    }
}
