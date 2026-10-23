<?php

namespace App\Services\Participation;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turning "let Dot.Memory remember the topics I help with" on and off, for one person, honestly.
 *
 * ON: Memory is asked to record the person's consent FIRST. Only when Memory confirms it is the local switch set and the
 * person's eligible past contributions queued. If Memory cannot be reached or does not confirm, nothing changes and the
 * person is told so: consent is never claimed that was not recorded.
 *
 * OFF: Memory is asked to revoke consent (which purges what it holds) and then to erase the person entirely. If it cannot
 * be reached the switch stays on and the person is told nothing changed. If the revoke went through but the final erase
 * did not, sharing IS off (nothing more is sent, the topic counts are gone) and the person is told what is left.
 *
 * Nothing is retracted one event at a time: Memory has no per-event delete. When an acceptance or verification is later
 * removed, or the content is hidden, the count that was sent stays until the person turns this off or Memory's retention
 * removes it. The page says so.
 */
class ExpertiseSharing
{
    public function __construct(
        private readonly ParticipationClient $client,
        private readonly ParticipationOutbox $outbox,
    ) {}

    /** @return array{level: 'ok'|'error', message: string} */
    public function enable(User $user): array
    {
        if ($user->sharesExpertise()) {
            return ['level' => 'ok', 'message' => 'Sharing was already on.'];
        }

        try {
            $this->client->setConsent($user, true);
        } catch (ParticipationUnavailableException $e) {
            return ['level' => 'error', 'message' => $e->reason === ParticipationUnavailableException::NOT_CONFIGURED
                ? 'Dot.Memory is not connected to InfoDot yet, so your choice was not recorded. Sharing is still off.'
                : 'We could not get Dot.Memory to record your choice, so it was not recorded. Sharing is still off. Try again in a few minutes.'];
        }

        $queued = 0;
        DB::transaction(function () use ($user, &$queued) {
            User::whereKey($user->getKey())->update(['share_expertise' => true]);
            $this->outbox->forget($user); // A fresh start: nothing from an earlier time of sharing is assumed to still be in Memory.
            foreach (EligibleContributions::forUser($user) as $event) {
                $queued += $this->outbox->enqueue($user, $event) ? 1 : 0;
            }
        });
        $user->forceFill(['share_expertise' => true])->syncOriginal();

        if ($queued > 0) {
            dispatch(fn () => app(ParticipationOutbox::class)->flush(25))->afterResponse();
        }

        return ['level' => 'ok', 'message' => 'Sharing is on. Dot.Memory will remember the topics you help with, as a topic and a count only.'
            .($queued > 0 ? ' '.number_format($queued).' of your earlier contributions are being sent now.' : '')];
    }

    /** @return array{level: 'ok'|'error', message: string} */
    public function disable(User $user): array
    {
        if (! $user->sharesExpertise()) {
            return ['level' => 'ok', 'message' => 'Sharing was already off.'];
        }

        try {
            $this->client->setConsent($user, false);
        } catch (ParticipationUnavailableException $e) {
            return ['level' => 'error', 'message' => $e->reason === ParticipationUnavailableException::NOT_CONFIGURED
                ? 'Dot.Memory is not connected to InfoDot right now, so we could not ask it to erase anything. Nothing was changed. Try again later.'
                : 'We could not reach Dot.Memory, so nothing was changed and your choice is as it was. Try again in a few minutes.'];
        }

        // Memory has revoked consent and purged what it held. From here on nothing is sent, whatever happens next.
        User::whereKey($user->getKey())->update(['share_expertise' => false]);
        $user->forceFill(['share_expertise' => false])->syncOriginal();
        $this->outbox->forget($user);

        try {
            $this->client->erase($user);
        } catch (ParticipationUnavailableException) {
            return ['level' => 'ok', 'message' => 'Sharing is off and Dot.Memory has removed the topic counts it held. The last clean-up, deleting the record that you had agreed, did not finish; that record holds no topics and Memory removes it by itself later.'];
        }

        return ['level' => 'ok', 'message' => 'Sharing is off, and everything Dot.Memory held about you has been erased.'];
    }

    /**
     * What the Dot Points page shows about sharing.
     *
     * state: 'unavailable' (feature not connected and the person is not sharing), 'off', or for a person who is sharing:
     * 'ok' (topics read from Memory), 'no_consent' (Memory holds no consent for them) or 'down' (could not read it).
     *
     * @return array{state: string, connected: bool, topics: list<array{name: string, topic: string, count: int, kinds: array<string, int>}>, waiting: int}
     */
    public function overview(User $user): array
    {
        $connected = $this->client->enabled();
        if (! $user->sharesExpertise()) {
            return ['state' => $connected ? 'off' : 'unavailable', 'connected' => $connected, 'topics' => [], 'waiting' => 0];
        }

        $waiting = $this->outbox->waitingFor($user);
        try {
            $context = $this->client->context($user);
        } catch (ParticipationRefusedException) {
            return ['state' => 'no_consent', 'connected' => $connected, 'topics' => [], 'waiting' => $waiting];
        } catch (ParticipationUnavailableException) {
            return ['state' => 'down', 'connected' => $connected, 'topics' => [], 'waiting' => $waiting];
        }

        if (! $context->granted) {
            return ['state' => 'no_consent', 'connected' => $connected, 'topics' => [], 'waiting' => $waiting];
        }

        $names = Topic::whereIn('slug', array_column($context->topics, 'topic'))->pluck('name', 'slug');
        $topics = array_map(fn (array $t) => ['name' => (string) ($names[$t['topic']] ?? $t['topic']), 'topic' => $t['topic'], 'count' => $t['count'], 'kinds' => $t['kinds']], $context->topics);
        usort($topics, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return ['state' => 'ok', 'connected' => $connected, 'topics' => $topics, 'waiting' => $waiting];
    }
}
