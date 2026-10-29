<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

/**
 * The company role vocabulary (R-13): owner, admin, manager, member.
 *
 * `editor` is the old name for what is now `member`. It stays registered so nothing that still holds it breaks, is hidden from every
 * picker, and is moved to `member` by a migration. Other Dot platforms read the role key InfoDot sends and only know owner, admin and
 * editor, so on the wire `role` keeps those three values (a manager and a member both go out as `editor`) and the real role travels
 * beside it as `infodot_role`. A platform that learns the new names reads `infodot_role`; nobody is demoted by an unknown key meanwhile.
 *
 * Roles are organisation permissions only. A person's InfoDot reputation or tier never appears here.
 */
final class TeamRoles
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const MEMBER = 'member';
    public const LEGACY_EDITOR = 'editor';

    /** The roles a person can be given today, in the order shown. */
    public const ASSIGNABLE = [self::ADMIN, self::MANAGER, self::MEMBER];

    public static function normalise(?string $key): ?string
    {
        return $key === self::LEGACY_EDITOR ? self::MEMBER : $key;
    }

    /** The value older platforms understand: owner, admin or editor. */
    public static function wire(?string $key): ?string
    {
        return match (self::normalise($key)) {
            self::OWNER => 'owner',
            self::ADMIN => 'admin',
            self::MANAGER, self::MEMBER => 'editor',
            default => $key,
        };
    }

    /** @return array{role: ?string, infodot_role: ?string} */
    public static function payload(?string $key): array
    {
        return ['role' => self::wire($key), 'infodot_role' => self::normalise($key)];
    }

    /** Owners and administrators run the company's people, roles and seats. */
    public static function manages(User $user, Team $team): bool
    {
        return $user->ownsTeam($team) || $user->hasTeamRole($team, self::ADMIN);
    }

    /** The roles this person may give to someone else. A manager can only bring people in as members. */
    public static function assignableBy(User $user, Team $team): array
    {
        if (self::manages($user, $team)) {
            return self::ASSIGNABLE;
        }

        return $user->hasTeamRole($team, self::MANAGER) ? [self::MEMBER] : [];
    }
}
