<?php

namespace App\Rules;

use App\Support\ReservedEmailDomains;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses a NEW email address on a domain that cannot receive mail (example, test, invalid, localhost and the reserved
 * example.com/.net/.org). Used wherever InfoDot accepts an address a person types. Existing accounts are never judged
 * by it; callers that edit an existing value only apply it when the value changed. Turned off by the config flag
 * `auth.block_reserved_email_domains` (env BLOCK_RESERVED_EMAIL_DOMAINS).
 *
 * Put it before the `email` rule (with `bail`) so a reserved address gets this message and not a generic one.
 */
class DeliverableEmailDomain implements ValidationRule
{
    public const MESSAGE = 'Please use a real email address that can receive mail: addresses on example, test, invalid and localhost domains are not accepted.';

    public static function enabled(): bool
    {
        return (bool) config('auth.block_reserved_email_domains', true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::enabled() && ReservedEmailDomains::isReserved($value)) {
            $fail(self::MESSAGE);
        }
    }
}
