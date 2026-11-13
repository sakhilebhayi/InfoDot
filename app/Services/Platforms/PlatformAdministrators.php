<?php

namespace App\Services\Platforms;

use App\Models\PlatformCatalogueEvent;
use App\Models\User;
use Illuminate\Support\Facades\App;
use LogicException;

/**
 * Who is a platform administrator (R-50). The role is given and taken away from a shell on the host and from nowhere
 * else: these methods refuse to run inside a web request, so no screen, form or API call can ever reach them. Each
 * change is written to the catalogue audit trail.
 */
class PlatformAdministrators
{
    /** Why this account cannot be made a platform administrator, or null when it can. */
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
    public function revoke(User $user): bool
    {
        $this->hostOnly();

        return $this->set($user, false);
    }

    private function set(User $user, bool $now): bool
    {
        $was = $user->isPlatformAdmin();

        if ($was === $now) {
            return false;
        }

        $user->forceFill(['is_platform_admin' => $now])->save();

        PlatformCatalogueEvent::create([
            'actor_id' => null, 'subject_type' => 'administrator', 'subject_key' => (string) $user->uuid,
            'type' => $now ? 'administrator_granted' : 'administrator_revoked',
            'before' => ['is_platform_admin' => $was], 'after' => ['is_platform_admin' => $now],
            'reason' => 'From the command line on the host.', 'created_at' => now(),
        ]);

        return true;
    }

    private function hostOnly(): void
    {
        if (! App::runningInConsole()) {
            throw new LogicException('The platform administrator role is changed only from the command line on the host.');
        }
    }
}
