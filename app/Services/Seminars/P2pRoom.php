<?php

namespace App\Services\Seminars;

use App\Models\P2pPeer;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The server side of a peer-to-peer seminar room. It carries no video: it only (1) decides who feeds whom, as a tree with the host at the
 * root, (2) passes the small connection messages between two browsers, and (3) carries the room chat. Browsers poll it (a shared host cannot
 * hold connections open), quickly while connecting and slowly once settled.
 *
 * Who feeds whom: the host feeds as many viewers directly as their upload allows (P2pCapacity); each viewer who can pass the stream on feeds a
 * few more, nearest the host first. If someone's feeder leaves, they are given a new one at their next poll.
 */
class P2pRoom
{
    private const TYPES = ['hello', 'offer', 'answer', 'ice'];

    /** @return list<array{urls: string|list<string>, username?: string, credential?: string}> */
    public function iceServers(): array
    {
        $servers = [];

        if ($urls = config('seminars.p2p.stun_urls')) {
            $servers[] = ['urls' => array_values($urls)];
        }

        $turn = config('seminars.p2p.turn');

        if (filled($turn['url'] ?? null) && filled($turn['username'] ?? null) && filled($turn['credential'] ?? null)) {
            $servers[] = ['urls' => $turn['url'], 'username' => $turn['username'], 'credential' => $turn['credential']];
        }

        return $servers;
    }

    public function isRemoved(Seminar $seminar, User $user): bool
    {
        return P2pPeer::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', 'removed')->exists();
    }

    /** Why this person cannot be in the room now, or null. */
    public function refusal(Seminar $seminar, User $user): ?string
    {
        if (! config('seminars.p2p.enabled') || ! $seminar->isP2p() || ! $seminar->isPublished()) {
            return 'This seminar has no small room.';
        }

        if ($seminar->host_id === $user->id) {
            return $seminar->hasEnded() ? 'This seminar has finished.' : null;
        }

        $registered = \App\Models\SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', \App\Models\SeminarRegistration::REGISTERED)->exists();

        return match (true) {
            P2pPeer::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', 'removed')->exists() => 'The host removed you from this room.',
            ! $registered => 'The room is for people who registered.',
            $seminar->hasEnded() => 'This seminar has finished.',
            ! $seminar->joinIsOpen() => 'The room opens '.config('seminars.join_opens_minutes_before', 15).' minutes before the start.',
            default => null,
        };
    }

    /** @return array{peer_id: string, role: string, parent: ?string, quality: string, ice: array, poll_fast: int, poll_slow: int, viewers: int, capacity: int} */
    public function join(Seminar $seminar, User $user, bool $canRelay): array
    {
        return DB::transaction(function () use ($seminar, $user, $canRelay) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();
            $this->sweep($locked);
            $isHost = $locked->host_id === $user->id;
            $peer = P2pPeer::where('seminar_id', $locked->id)->where('user_id', $user->id)->first();

            if ($peer?->status === 'removed') {
                throw ValidationException::withMessages(['room' => 'The host removed you from this room.']);
            }

            if (! $isHost && ($peer === null || $peer->status === 'gone')) {
                $viewers = P2pPeer::where('seminar_id', $locked->id)->where('role', 'viewer')->whereIn('status', ['connecting', 'live'])->count();

                if ($locked->capacity !== null && $viewers >= $locked->capacity) {
                    throw ValidationException::withMessages(['room' => 'The room is full.']);
                }
            }

            $peer ??= new P2pPeer(['seminar_id' => $locked->id, 'user_id' => $user->id, 'peer_id' => (string) Str::uuid(), 'role' => $isHost ? 'host' : 'viewer']);
            $peer->forceFill([
                'status' => $isHost ? 'live' : 'connecting', 'can_relay' => ! $isHost && $canRelay && $this->relayRatio($locked) > 0,
                'parent_peer_id' => null, 'depth' => 0, 'last_seen' => now(),
            ])->save();

            if (! $isHost) {
                $this->place($locked, $peer);
                // Connecting to the room is what counts as attending (there is no media server to say so).
                \App\Models\SeminarRegistration::where('seminar_id', $locked->id)->where('user_id', $user->id)->where('status', \App\Models\SeminarRegistration::REGISTERED)->whereNull('attended_at')->update(['attended_at' => now()]);
            }

            return $this->state($locked, $peer);
        });
    }

    /** The person's current state; also the heartbeat. Gives a new feeder if theirs has gone. */
    public function poll(Seminar $seminar, User $user, int $chatAfter = 0): array
    {
        return DB::transaction(function () use ($seminar, $user, $chatAfter) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();
            $peer = $this->mine($locked, $user);

            if ($peer->status === 'removed') {
                return ['removed' => true, 'signals' => [], 'chat' => [], 'parent' => null, 'reassigned' => false, 'viewers' => 0, 'host_here' => false];
            }

            $peer->forceFill(['last_seen' => now()] + ($peer->status === 'gone' ? ['status' => 'connecting'] : []))->save();
            $this->sweep($locked);
            $reassigned = false;

            if ($peer->role === 'viewer' && ! $this->parentIsAlive($locked, $peer)) {
                $before = $peer->parent_peer_id;
                $this->place($locked, $peer, $before);
                $reassigned = $peer->parent_peer_id !== $before;
            }

            $signals = DB::table('p2p_signals')->where('seminar_id', $locked->id)->where('to_peer', $peer->peer_id)->orderBy('id')->limit(100)->get(['id', 'from_peer', 'type', 'payload']);

            if ($signals->isNotEmpty()) {
                DB::table('p2p_signals')->whereIn('id', $signals->pluck('id'))->delete();
            }

            return [
                'removed' => false, 'parent' => $peer->parent_peer_id, 'reassigned' => $reassigned,
                'signals' => $signals->map(fn ($s) => ['from' => $s->from_peer, 'type' => $s->type, 'payload' => $s->payload])->all(),
                'chat' => DB::table('p2p_chat')->where('seminar_id', $locked->id)->where('id', '>', $chatAfter)->orderBy('id')->limit(50)->get(['id', 'name', 'body'])->all(),
                'viewers' => P2pPeer::where('seminar_id', $locked->id)->where('role', 'viewer')->whereIn('status', ['connecting', 'live'])->count(),
                'host_here' => P2pPeer::where('seminar_id', $locked->id)->where('role', 'host')->where('status', 'live')->exists(),
            ];
        });
    }

    public function signal(Seminar $seminar, User $user, string $to, string $type, ?string $payload): void
    {
        $me = $this->mine($seminar, $user);
        $target = P2pPeer::where('seminar_id', $seminar->id)->where('peer_id', $to)->whereIn('status', ['connecting', 'live'])->first();

        if (! in_array($type, self::TYPES, true) || $target === null || $me->status === 'removed' || $to === $me->peer_id || strlen((string) $payload) > 20000) {
            throw ValidationException::withMessages(['signal' => 'That message cannot be delivered.']);
        }

        DB::table('p2p_signals')->insert(['seminar_id' => $seminar->id, 'from_peer' => $me->peer_id, 'to_peer' => $to, 'type' => $type, 'payload' => $payload, 'created_at' => now()]);
    }

    /** The viewer's video is flowing: they can now pass it on. */
    public function ready(Seminar $seminar, User $user): void
    {
        $this->mine($seminar, $user)->forceFill(['status' => 'live', 'last_seen' => now()])->save();
    }

    public function leave(Seminar $seminar, User $user): void
    {
        P2pPeer::where('seminar_id', $seminar->id)->where('user_id', $user->id)->whereNotIn('status', ['removed'])->update(['status' => 'gone']);
    }

    /** The viewer's feeder is not delivering: give them another, never the same one. */
    public function report(Seminar $seminar, User $user): array
    {
        return DB::transaction(function () use ($seminar, $user) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();
            $peer = $this->mine($locked, $user);
            $bad = $peer->parent_peer_id;
            $peer->forceFill(['parent_peer_id' => null, 'status' => 'connecting', 'last_seen' => now()])->save();
            $this->place($locked, $peer, $bad);

            return ['parent' => $peer->parent_peer_id];
        });
    }

    public function chat(Seminar $seminar, User $user, string $text): void
    {
        $this->mine($seminar, $user);
        $text = trim(mb_substr($text, 0, 300));

        if ($text === '') {
            return;
        }

        DB::table('p2p_chat')->insert(['seminar_id' => $seminar->id, 'user_id' => $user->id, 'name' => mb_substr($user->name, 0, 60), 'body' => $text, 'created_at' => now()]);
    }

    /** The host removes someone: they are disconnected at their next poll and cannot come back into this room. */
    public function remove(Seminar $seminar, User $person): void
    {
        // Also bars someone who has not entered yet: with no place in the room there is no row to change, so one is made.
        $peer = P2pPeer::firstOrNew(['seminar_id' => $seminar->id, 'user_id' => $person->id], ['peer_id' => (string) Str::uuid(), 'role' => 'viewer']);

        if ($peer->role === 'viewer') {
            $peer->forceFill(['status' => 'removed', 'parent_peer_id' => null, 'last_seen' => now()])->save();
        }
    }

    /** @return array<string, mixed> */
    private function state(Seminar $seminar, P2pPeer $peer): array
    {
        return [
            'peer_id' => $peer->peer_id, 'role' => $peer->role, 'parent' => $peer->parent_peer_id, 'quality' => (string) $seminar->p2p_quality,
            'ice' => $this->iceServers(), 'poll_fast' => 1500, 'poll_slow' => 8000, 'capacity' => (int) $seminar->capacity,
            'viewers' => P2pPeer::where('seminar_id', $seminar->id)->where('role', 'viewer')->whereIn('status', ['connecting', 'live'])->count(),
        ];
    }

    private function mine(Seminar $seminar, User $user): P2pPeer
    {
        return P2pPeer::where('seminar_id', $seminar->id)->where('user_id', $user->id)->firstOrFail();
    }

    private function relayRatio(Seminar $seminar): float
    {
        return (float) (config('seminars.p2p.qualities.'.$seminar->p2p_quality.'.relay_ratio') ?? 0);
    }

    private function sweep(Seminar $seminar): void
    {
        P2pPeer::where('seminar_id', $seminar->id)->whereIn('status', ['connecting', 'live'])
            ->where('last_seen', '<', now()->subSeconds((int) config('seminars.p2p.peer_timeout_seconds', 30)))->update(['status' => 'gone']);
    }

    private function parentIsAlive(Seminar $seminar, P2pPeer $peer): bool
    {
        return $peer->parent_peer_id !== null
            && P2pPeer::where('seminar_id', $seminar->id)->where('peer_id', $peer->parent_peer_id)->whereIn('status', ['connecting', 'live'])->where(fn ($q) => $q->where('role', 'host')->orWhere('status', 'live'))->exists();
    }

    /** Puts the viewer under the nearest feeder with a free place. Leaves them waiting (no feeder) when there is none yet. */
    private function place(Seminar $seminar, P2pPeer $peer, ?string $avoid = null): void
    {
        $plan = P2pCapacity::plan((int) $seminar->p2p_uplink_kbps, (string) $seminar->p2p_quality);
        $maxDepth = (int) config('seminars.p2p.max_depth', 4);
        $below = $this->descendants($seminar, $peer->peer_id);
        $candidates = P2pPeer::where('seminar_id', $seminar->id)->where('status', 'live')->where('peer_id', '!=', $peer->peer_id)
            ->where(fn ($q) => $q->where('role', 'host')->orWhere('can_relay', true))->where('depth', '<', $maxDepth)->orderBy('depth')->orderBy('id')->get();

        foreach ($candidates as $c) {
            if ($c->peer_id === $avoid || in_array($c->peer_id, $below, true)) {
                continue;
            }

            $slots = $c->role === 'host' ? $plan['direct'] : (int) config('seminars.p2p.relay_slots', 3);
            $used = P2pPeer::where('seminar_id', $seminar->id)->where('parent_peer_id', $c->peer_id)->whereIn('status', ['connecting', 'live'])->where('id', '!=', $peer->id)->count();

            if ($used < $slots) {
                $peer->forceFill(['parent_peer_id' => $c->peer_id, 'depth' => $c->depth + 1, 'status' => 'connecting'])->save();

                return;
            }
        }

        $peer->forceFill(['parent_peer_id' => null, 'depth' => 0])->save();
    }

    /** @return list<string> everyone fed, directly or not, by this peer (so they are never chosen as its own feeder) */
    private function descendants(Seminar $seminar, string $peerId): array
    {
        $found = [];
        $frontier = [$peerId];

        while ($frontier !== []) {
            $next = P2pPeer::where('seminar_id', $seminar->id)->whereIn('parent_peer_id', $frontier)->pluck('peer_id')->all();
            $next = array_values(array_diff($next, $found));
            $found = array_merge($found, $next);
            $frontier = $next;
        }

        return $found;
    }
}
