<?php

namespace App\Services\Seminars;

use App\Models\Seminar;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * InfoDot's side of the seminar room, against a LiveKit server the owner runs. Mints short-lived join tokens (HS256 JWTs signed with the
 * LiveKit API secret), checks LiveKit's signed webhooks, and asks the server to remove a person. Nothing here logs a token or the secret.
 */
class LiveKit
{
    public function configured(): bool
    {
        return (bool) config('seminars.video.enabled') && filled(config('seminars.video.url')) && filled(config('seminars.video.api_key')) && filled(config('seminars.video.api_secret'));
    }

    public static function roomName(Seminar $seminar): string
    {
        return 'seminar-'.$seminar->id;
    }

    public static function identity(User $user): string
    {
        return 'u'.$user->id;
    }

    /** @return array{url: string, token: string} */
    public function joinToken(Seminar $seminar, User $user): array
    {
        $host = $seminar->host_id === $user->id;
        // A token cannot be taken back, so it is short: someone removed from the room, refunded or charged back can re-enter on the token
        // they already hold for at most this long. The page fetches a fresh one (checked again) whenever it reconnects. Never longer than the seminar plus the grace.
        $ceiling = $seminar->endsAt()->addMinutes((int) config('seminars.video.token_minutes_after_end', 15))->timestamp;
        $exp = min($ceiling, now()->timestamp + (int) config('seminars.video.token_ttl_minutes', 10) * 60);

        $token = $this->sign([
            'iss' => config('seminars.video.api_key'), 'sub' => self::identity($user), 'name' => mb_substr($user->name, 0, 60),
            'nbf' => now()->timestamp - 10, 'exp' => max($exp, now()->timestamp + 60),
            'metadata' => json_encode(['role' => $host ? 'host' : 'attendee']),
            'video' => [
                'room' => self::roomName($seminar), 'roomJoin' => true, 'canSubscribe' => true, 'canPublishData' => true,
                'canPublish' => $host,                       // attendees watch and chat; only the host sends audio and video
                'canPublishSources' => $host ? ['camera', 'microphone', 'screen_share', 'screen_share_audio'] : [],
            ],
        ]);

        return ['url' => (string) config('seminars.video.url'), 'token' => $token];
    }

    /** Asks LiveKit to disconnect a person from this seminar's room. */
    public function remove(Seminar $seminar, User $person): void
    {
        $admin = $this->sign([
            'iss' => config('seminars.video.api_key'), 'sub' => 'infodot', 'nbf' => now()->timestamp - 10, 'exp' => now()->timestamp + 60,
            'video' => ['room' => self::roomName($seminar), 'roomAdmin' => true],
        ]);

        try {
            $response = Http::withToken($admin)->acceptJson()->timeout(8)->post($this->httpUrl().'/twirp/livekit.RoomService/RemoveParticipant', ['room' => self::roomName($seminar), 'identity' => self::identity($person)]);
        } catch (Throwable $e) {
            Log::warning('LiveKit unreachable while removing a participant.', ['reason' => class_basename($e)]);

            throw new MarketplaceUnavailable('The room server could not be reached. Try again.');
        }

        // 404 means they were not connected, which is the state the host wanted.
        if (! $response->successful() && $response->status() !== 404) {
            Log::warning('LiveKit refused to remove a participant.', ['status' => $response->status()]);

            throw new MarketplaceUnavailable('The room server did not remove them. Try again.');
        }
    }

    /**
     * Whether a webhook really came from our LiveKit server: the Authorization header is a JWT signed with the API secret whose `sha256`
     * claim is the hash of the exact body we received.
     */
    public function webhookIsValid(?string $authorization, string $rawBody): bool
    {
        $secret = (string) config('seminars.video.api_secret');
        $jwt = trim(preg_replace('/^Bearer\s+/i', '', (string) $authorization));

        if ($secret === '' || substr_count($jwt, '.') !== 2) {
            return false;
        }

        [$h, $p, $sig] = explode('.', $jwt);
        $header = json_decode($this->b64d($h), true);
        $claims = json_decode($this->b64d($p), true);

        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256' || ! is_array($claims)) {
            return false;
        }

        if (! hash_equals(hash_hmac('sha256', $h.'.'.$p, $secret, true), $this->b64d($sig))) {
            return false;
        }

        if (($claims['iss'] ?? null) !== config('seminars.video.api_key') || ($claims['exp'] ?? 0) < now()->timestamp - 300) {
            return false;
        }

        $hash = hash('sha256', $rawBody, true);
        $given = (string) ($claims['sha256'] ?? '');

        return $given !== '' && (hash_equals(base64_encode($hash), $given) || hash_equals(bin2hex($hash), strtolower($given)));
    }

    private function httpUrl(): string
    {
        return rtrim((string) preg_replace('#^wss?://#', 'https://', (string) config('seminars.video.url')), '/');
    }

    /** @param  array<string, mixed>  $claims */
    private function sign(array $claims): string
    {
        $h = $this->b64e(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = $this->b64e(json_encode($claims));

        return $h.'.'.$p.'.'.$this->b64e(hash_hmac('sha256', $h.'.'.$p, (string) config('seminars.video.api_secret'), true));
    }

    private function b64e(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function b64d(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), false);
    }
}
