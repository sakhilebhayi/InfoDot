<?php

namespace App\Support\Knowledge;

/**
 * The one signing scheme of the Verified library, in both directions: HMAC-SHA256 over "{timestamp}.{raw body}"
 * (a GET signs the empty body), sent as X-InfoDot-Timestamp (unix seconds) and X-InfoDot-Signature ("sha256=" + hex).
 * Whoever holds the secret can sign; the timestamp window limits how long a captured request stays usable.
 */
final class Signature
{
    public const TIMESTAMP_HEADER = 'X-InfoDot-Timestamp';

    public const SIGNATURE_HEADER = 'X-InfoDot-Signature';

    public static function sign(string $secret, int $timestamp, string $body = ''): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** @return array<string, string> the two headers for a request with this body */
    public static function headers(string $secret, string $body = '', ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [self::TIMESTAMP_HEADER => (string) $timestamp, self::SIGNATURE_HEADER => self::sign($secret, $timestamp, $body)];
    }

    /** True only for a non-empty secret, a whole-number timestamp inside the window, and a matching signature. */
    public static function valid(string $secret, ?string $timestamp, ?string $signature, string $body, int $window): bool
    {
        if ($secret === '' || $timestamp === null || $signature === null || ! preg_match('/\A\d{1,12}\z/', $timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $window) {
            return false;
        }

        return hash_equals(self::sign($secret, (int) $timestamp, $body), $signature);
    }
}
