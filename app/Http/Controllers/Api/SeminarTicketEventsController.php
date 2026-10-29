<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Seminars\SeminarService;
use App\Support\Knowledge\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dot.Billing tells InfoDot that a seminar ticket was paid or refunded. Server to server: signed with the callback secret, five minute
 * window, and applied once per (event, ticket). This is the only thing that turns a payment into a registration.
 */
class SeminarTicketEventsController extends Controller
{
    public function __invoke(Request $request, SeminarService $seminars): JsonResponse
    {
        $secret = (string) config('seminars.paid.callback_secret');

        if (! config('seminars.paid.enabled') || ! Signature::valid($secret, $request->header(Signature::TIMESTAMP_HEADER), $request->header(Signature::SIGNATURE_HEADER), $request->getContent(), 300)) {
            return response()->json(['error' => 'invalid_request'], 401);
        }

        $body = json_decode($request->getContent(), true);

        if (! is_array($body) || ! is_string($body['event'] ?? null) || ! is_string($body['ticket_ref'] ?? null) || ! is_int($body['amount_cents'] ?? null) || ! is_string($body['currency'] ?? null)) {
            return response()->json(['error' => 'bad_request'], 400);
        }

        return response()->json(['status' => $seminars->applyTicketEvent($body['event'], $body['ticket_ref'], $body['amount_cents'], $body['currency'])]);
    }
}
