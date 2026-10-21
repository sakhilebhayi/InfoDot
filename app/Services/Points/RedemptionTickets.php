<?php

namespace App\Services\Points;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * One ticket per displayed Redeem form. The ticket's random token is rendered into the form and is also the
 * idempotency key sent to the points service, so the browser never chooses a key, a reward or an amount: it only
 * hands back a token this server issued to this person for one reward.
 *
 * Lifecycle: issued -> (attempted when we have asked the service but did not get a clear answer) -> consumed.
 * A consumed ticket never reaches the service again, so a double-click or a repeated POST cannot spend twice; an
 * attempted ticket is handed out again for that reward, so the retry carries the same key and the service replays
 * instead of spending a second time.
 */
class RedemptionTickets
{
    private const SESSION_KEY = 'points.tickets';

    private const TTL_SECONDS = 7200;

    private const MAX = 120;

    public function __construct(private readonly Session $session) {}

    /** The token to render for this reward: an unresolved earlier one if there is one, otherwise a fresh one. */
    public function issue(int $userId, int $rewardId, int $cost): string
    {
        $tickets = $this->live();

        foreach ($tickets as $token => $t) {
            if ($t['user_id'] === $userId && $t['reward_id'] === $rewardId && $t['state'] === 'attempted') {
                $tickets[$token]['cost'] = $cost;
                $this->session->put(self::SESSION_KEY, $tickets);

                return $token;
            }
        }

        $token = Str::random(32);
        $tickets[$token] = ['user_id' => $userId, 'reward_id' => $rewardId, 'cost' => $cost, 'state' => 'issued', 'at' => time(), 'notice' => null];
        $this->session->put(self::SESSION_KEY, array_slice($tickets, -self::MAX, null, true));

        return $token;
    }

    /** @return array{user_id: int, reward_id: int, cost: int, state: string, at: int, notice: ?array{level: string, message: string}}|null */
    public function find(string $token, int $userId): ?array
    {
        $ticket = $this->live()[$token] ?? null;

        return $ticket !== null && $ticket['user_id'] === $userId ? $ticket : null;
    }

    public function isAttempted(int $userId, int $rewardId): bool
    {
        foreach ($this->live() as $t) {
            if ($t['user_id'] === $userId && $t['reward_id'] === $rewardId && $t['state'] === 'attempted') {
                return true;
            }
        }

        return false;
    }

    public function markAttempted(string $token): void
    {
        $this->update($token, ['state' => 'attempted']);
    }

    /** @param array{level: string, message: string} $notice What the person was told, replayed if the form is posted again. */
    public function consume(string $token, array $notice): void
    {
        $this->update($token, ['state' => 'consumed', 'notice' => $notice]);
    }

    /** @param array<string, mixed> $changes */
    private function update(string $token, array $changes): void
    {
        $tickets = $this->live();
        if (isset($tickets[$token])) {
            $tickets[$token] = $changes + $tickets[$token];
            $this->session->put(self::SESSION_KEY, $tickets);
        }
    }

    /** @return array<string, array{user_id: int, reward_id: int, cost: int, state: string, at: int, notice: ?array{level: string, message: string}}> */
    private function live(): array
    {
        $cutoff = time() - self::TTL_SECONDS;

        return array_filter((array) $this->session->get(self::SESSION_KEY, []), fn ($t) => is_array($t) && ($t['at'] ?? 0) >= $cutoff);
    }
}
