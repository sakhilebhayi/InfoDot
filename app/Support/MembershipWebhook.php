<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the platforms that hold a copy of a company's membership that it changed, so access
 * ends in seconds instead of at the person's next sign-in.
 *
 * Every call is signed: HMAC-SHA256 over "{timestamp}.{body}" with the platform's own secret
 * (the one it redeems handoff tokens with), so a platform accepts it only from InfoDot and
 * only while it is fresh. Events are idempotent, carry no content, and delivery is best
 * effort: a failure is logged and the sign-in sync still catches the change later.
 */
final class MembershipWebhook
{
    public const REMOVED = 'member.removed';

    public const ROLE_CHANGED = 'member.role_changed';

    public const TEAM_DELETED = 'team.deleted';

    /** The header names the receiving platforms read. */
    public const HEADER_TIMESTAMP = 'X-InfoDot-Timestamp';

    public const HEADER_SIGNATURE = 'X-InfoDot-Signature';

    /**
     * @param  array{event: string, team_uuid: string, email?: string, role?: string}  $payload
     */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * Send one event to every platform that should hear it. Run after the response, with plain
     * values only (no models), so it never delays or breaks the screen that caused it.
     *
     * @param  array{event: string, team_uuid: string, email?: string, role?: string}  $payload
     */
    public static function dispatchAfterResponse(array $payload): void
    {
        dispatch(fn () => self::send($payload))->afterResponse();
    }

    /**
     * @param  array{event: string, team_uuid: string, email?: string, role?: string}  $payload
     */
    public static function send(array $payload): void
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);

        foreach ((array) config('ecosystem.membership_platforms', []) as $key) {
            $secret = (string) config('ecosystem.service_secrets.'.$key, '');
            $url = EcosystemHandoff::apiUrlFor((string) $key, '/api/ecosystem/membership');

            if ($secret === '' || $url === null) {
                continue;
            }

            $timestamp = time();

            try {
                $response = Http::withHeaders([
                    self::HEADER_TIMESTAMP => (string) $timestamp,
                    self::HEADER_SIGNATURE => self::sign($secret, $timestamp, $body),
                ])->acceptJson()->timeout(4)->withBody($body, 'application/json')->post($url);

                if (! $response->successful()) {
                    Log::warning('Membership webhook refused.', ['platform' => $key, 'event' => $payload['event'], 'status' => $response->status()]);
                }
            } catch (Throwable $e) {
                Log::warning('Membership webhook failed.', ['platform' => $key, 'event' => $payload['event'], 'error' => class_basename($e)]);
            }
        }
    }
}
