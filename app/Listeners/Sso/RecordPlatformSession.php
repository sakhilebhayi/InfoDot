<?php

namespace App\Listeners\Sso;

use App\Models\DotPlatform;
use App\Models\SsoPlatformSession;
use Laravel\Passport\Events\AccessTokenCreated;

/**
 * Every issued access token marks the (user, platform) pair as signed in: the connected-platforms
 * view, platform health (last_authenticated_at) and back-channel logout targeting all read this.
 */
class RecordPlatformSession
{
    public function handle(AccessTokenCreated $event): void
    {
        if (! $event->userId) {
            return;
        }

        $platform = DotPlatform::findByClientId($event->clientId);

        if (! $platform) {
            return;
        }

        SsoPlatformSession::updateOrCreate(
            ['user_id' => $event->userId, 'dot_platform_id' => $platform->id],
            ['last_issued_at' => now()],
        );

        $platform->forceFill(['last_authenticated_at' => now()])->save();
    }
}
