<?php

namespace App\Services\Platforms;

use App\Models\PlatformCatalogueEvent;
use App\Models\User;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * Who is a moderator. Like the platform administrator role, this is given and taken away from a shell on the host and from nowhere
 * else: these methods refuse to run inside a web request. Every change is written to the same audit trail, with the account's
 * before and after, so who held the role and when can always be read back. Taking the role from the last moderator needs `force`,
 * because the moderation pages and several reviews have nobody to act on them without one.
 */
class Moderators
{
    /** Why this account cannot be made a moderator, or null when it can. */
    public function whyNot(User $user): ?string
    {
        return match (true) {
            $user->email_verified_at === null => 'That account has not verified its email address.',
            $user->isSuspended() => 'That account is suspended.',
            default => null,
        };
    }

    /** True when the role was given now; false when the account already had it. */
    public function grant(User $user): bool
    {
        $this->hostOnly();

        if (($refusal = $this->whyNot($user)) !== null) {
            throw new EntitlementRefused($refusal);
        }

        return $this->set($user, true);
    }

    /** True when the role was taken away now; false when the account did not have it. */
    public function revoke(User $user, bool $force = false): bool
    {
        $this->hostOnly();

        if ($user->isModerator() && ! $force && User::where('is_moderator', true)->count() <= 1) {
            throw new EntitlementRefused('That account is the only moderator. Taking the role away leaves nobody to moderate. Add another moderator first, or run it again with --force.');
        }

        return $this->set($user, false);
    }

    private function set(User $user, bool $now): bool
    {
        $was = $user->isModerator();

        if ($was === $now) {
            return false;
        }

        $user->forceFill(['is_moderator' => $now])->save();

        PlatformCatalogueEvent::create([
            'actor_id' => null, 'subject_type' => 'moderator', 'subject_key' => (string) $user->uuid,
            'type' => $now ? 'moderator_granted' : 'moderator_revoked',
            'before' => ['is_moderator' => $was], 'after' => ['is_moderator' => $now],
            'reason' => 'From the command line on the host.', 'created_at' => now(),
        ]);

        return true;
    }

    private function hostOnly(): void
    {
        if (! App::runningInConsole()) {
            throw new LogicException('The moderator role is changed only from the command line on the host.');
        }
    }
}
