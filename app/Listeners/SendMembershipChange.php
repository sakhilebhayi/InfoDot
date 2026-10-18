<?php

namespace App\Listeners;

use App\Support\MembershipWebhook;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamMemberRemoved;
use Laravel\Jetstream\Events\TeamMemberUpdated;

/**
 * Turns a membership change in InfoDot into a signed webhook to the platforms. Companies only:
 * a personal team is never linked anywhere, so there is nothing to tell.
 */
class SendMembershipChange
{
    public function handleRemoved(TeamMemberRemoved $event): void
    {
        $this->send(MembershipWebhook::REMOVED, $event->team, $event->user->email ?? null);
    }

    public function handleUpdated(TeamMemberUpdated $event): void
    {
        $role = $event->user->teamRole($event->team)?->key;

        $this->send(MembershipWebhook::ROLE_CHANGED, $event->team, $event->user->email ?? null, $role);
    }

    public function handleDeleted(TeamDeleted $event): void
    {
        $this->send(MembershipWebhook::TEAM_DELETED, $event->team, null);
    }

    private function send(string $name, $team, ?string $email, ?string $role = null): void
    {
        if ($team === null || $team->personal_team || blank($team->uuid)) {
            return;
        }

        $payload = ['event' => $name, 'team_uuid' => (string) $team->uuid];

        if ($email !== null) {
            $payload['email'] = strtolower($email);
        }

        if ($role !== null) {
            $payload['role'] = $role;
        }

        MembershipWebhook::dispatchAfterResponse($payload);
    }
}
