<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentConfirmation;
use App\Services\Platforms\PaymentConfirmations;
use App\Services\Platforms\PaymentEventRefused;
use App\Support\Knowledge\Signature;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dot.Finance tells InfoDot that a company's platform subscription was paid, renewed, failed, refunded or cancelled
 * (R-50). Server to server, in the same scheme as the seminar ticket events: HMAC-SHA256 over "{timestamp}.{raw body}"
 * with its own secret, compared in constant time, inside a five minute window, and applied once per event id.
 *
 * This is the only thing that makes a platform paid. While the secret is unset every request is refused; there is no
 * switch that accepts an unsigned or simulated event. The three refusals say as little as possible: 401 for anything
 * about the signature, 400 for a body that is not an event, 422 for an event that names something unknown, with no
 * hint of which part.
 */
class PlatformSubscriptionEventsController extends Controller
{
    public function __invoke(Request $request, PaymentConfirmations $confirmations): JsonResponse
    {
        $secret = (string) config('platform_subscriptions.confirmation_secret');

        if (! Signature::valid($secret, $request->header(Signature::TIMESTAMP_HEADER), $request->header(Signature::SIGNATURE_HEADER), $request->getContent(), (int) config('platform_subscriptions.signature_window', 300))) {
            return response()->json(['error' => 'invalid_request'], 401);
        }

        $body = json_decode($request->getContent(), true);

        if (! is_array($body) || ! $this->wellFormed($body)) {
            return response()->json(['error' => 'bad_request'], 400);
        }

        try {
            return response()->json(['status' => $confirmations->apply($body)]);
        } catch (PaymentEventRefused $e) {
            // Why it was refused is for the operator, never for the caller.
            Log::warning('A signed platform subscription event was refused.', ['event_id' => $body['event_id'], 'event' => $body['event'], 'why' => $e->getMessage()]);

            return response()->json(['error' => 'unprocessable'], 422);
        }
    }

    /** @param  array<mixed>  $body */
    private function wellFormed(array $body): bool
    {
        $string = fn (string $field, int $max, bool $required = true) => array_key_exists($field, $body)
            ? is_string($body[$field]) && $body[$field] !== '' && strlen($body[$field]) <= $max
            : ! $required;
        $date = function (string $field, bool $required) use ($body): bool {
            if (! array_key_exists($field, $body)) {
                return ! $required;
            }

            try {
                return is_string($body[$field]) && preg_match('/\A\d{4}-\d{2}-\d{2}T/', $body[$field]) === 1 && CarbonImmutable::parse($body[$field])->year < 2200;
            } catch (Throwable) {
                return false;
            }
        };

        $event = $body['event'] ?? null;

        if (! is_string($event) || ! in_array($event, PlatformPaymentConfirmation::EVENTS, true)) {
            return false;
        }

        $money = in_array($event, [PlatformPaymentConfirmation::PAID, PlatformPaymentConfirmation::RENEWED, PlatformPaymentConfirmation::REFUNDED], true);
        $confirms = $money && $event !== PlatformPaymentConfirmation::REFUNDED;
        $amount = $body['amount_minor'] ?? null;

        return $string('event_id', 100) && preg_match('/\A[A-Za-z0-9._:-]+\z/', $body['event_id']) === 1
            && $string('company_uuid', 36) && $string('platform', 40) && $string('reference', 120)
            && $string('plan', 60, false) && $string('bundle', 60, false)
            && ($amount === null ? ! $money : is_int($amount) && $amount >= ($confirms ? 1 : 0))
            && (array_key_exists('currency', $body) ? is_string($body['currency']) && preg_match('/\A[A-Z]{3}\z/', $body['currency']) === 1 : $amount === null)
            && $date('occurred_at', true) && $date('period_end', $confirms) && $date('grace_until', false)
            && (! $confirms || CarbonImmutable::parse($body['period_end'])->isFuture());
    }
}
