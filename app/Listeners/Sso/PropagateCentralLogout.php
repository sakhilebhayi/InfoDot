<?php

namespace App\Listeners\Sso;

use App\Models\SsoPlatformSession;
use App\Models\User;
use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * R-20 §10: a central logout or a password reset ends the user's access across the ecosystem.
 * The HTTP calls run after the response is sent (sync queue, afterResponse) so sign-out stays fast.
 */
class PropagateCentralLogout
{
    public function handleLogout(Logout $event): void
    {
        if ($event->guard === 'web' && $event->user instanceof User) {
            $this->propagate($event->user);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->propagate($event->user);
        }
    }

    private function propagate(User $user): void
    {
        if (! SsoPlatformSession::where('user_id', $user->id)->exists()) {
            return;
        }

        dispatch(function () use ($user) {
            app(BackchannelLogoutService::class)->propagate($user);
        })->afterResponse();
    }
}
