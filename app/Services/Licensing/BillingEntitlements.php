<?php

namespace App\Services\Licensing;

use App\Models\Team;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads what a company is entitled to from Dot.Billing (GET /api/entitlements/{company_uuid}, signed with this platform's secret) and
 * records it as the company's seat licence. The only way purchased seats ever change: a verified server-to-server answer, never a browser
 * redirect and never a value typed into InfoDot.
 *
 * Fail safe in both directions. If Billing is not configured, unreachable, slow or answers something unreadable, the stored licence is left
 * exactly as it was: nobody is locked out and nothing is granted. Billing keeps a past-due company's plan limits (the consuming app decides
 * what to do); InfoDot's decision is to keep them and never remove a person.
 *
 * Never call this inside a database transaction: it makes an HTTP request.
 */
class BillingEntitlements
{
    /** Most a smallint column holds; an unlimited plan is stored as this. */
    private const UNLIMITED = 60000;

    public function __construct(private SeatLedger $ledger) {}

    public function configured(): bool
    {
        return (bool) config('licensing.billing_connected')
            && filled(config('licensing.billing.url'))
            && filled(config('licensing.billing.secret'));
    }

    /** Refresh the stored licence from Billing if it is due. Returns true when a fresh answer was applied. */
    public function sync(Team $team): bool
    {
        if (! $this->configured() || $team->personal_team || blank($team->uuid)) {
            return false;
        }

        $key = 'licence:billing:'.$team->uuid;

        if (Cache::has($key)) {
            return false;
        }

        $payload = $this->fetch((string) $team->uuid);

        if ($payload === null) {
            return false; // not cached, so the next view tries again; the stored licence is untouched
        }

        Cache::put($key, true, (int) config('licensing.billing.cache_seconds', 300));
        $this->apply($team, $payload);

        return true;
    }

    /** @return array<string, mixed>|null */
    private function fetch(string $uuid): ?array
    {
        $base = rtrim((string) config('licensing.billing.url'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('licensing.billing.timeout_seconds', 3))
                ->withHeaders(['X-InfoDot-Platform' => (string) config('licensing.billing.platform', 'infodot')] + Signature::headers((string) config('licensing.billing.secret')))
                ->get($base.'/api/entitlements/'.$uuid);
        } catch (Throwable $e) {
            Log::warning('Billing entitlements unreachable.', ['reason' => class_basename($e)]);

            return null;
        }

        // Billing has no record of this company yet: it has never been opened in Billing, so it is on the free seats. Not a fault, so it is
        // not a warning and, unlike a failure, it is remembered for the usual window instead of asking again on every page.
        if ($response->status() === 404) {
            Log::info('Billing has no record of this company yet; it keeps the free seats.');
            Cache::put('licence:billing:'.$uuid, true, (int) config('licensing.billing.cache_seconds', 300));

            return null;
        }

        if (! $response->ok()) {
            Log::warning('Billing entitlements refused or failed.', ['status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        return is_array($json) && array_key_exists('seat_limit', $json) && is_string($json['status'] ?? null) ? $json : null;
    }

    /** @param  array<string, mixed>  $e */
    private function apply(Team $team, array $e): void
    {
        $licence = $this->ledger->licenceFor($team);
        $included = $licence->included_seats ?? (int) config('licensing.included_seats', 3);
        $limit = $e['seat_limit'];

        if ($limit !== null && ! is_int($limit) && ! (is_string($limit) && ctype_digit($limit))) {
            return; // unreadable: leave the licence alone
        }

        $total = $limit === null ? self::UNLIMITED : min((int) $limit, self::UNLIMITED);
        $purchased = max(0, $total - $included); // never below the free seats
        $status = match ($e['status']) {
            'active', 'trialing' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            default => 'free',
        };
        $ref = is_array($e['plan'] ?? null) ? mb_substr((string) ($e['plan']['slug'] ?? ''), 0, 80) : null;

        if ($licence->purchased_seats === $purchased && $licence->billing_status === $status && $licence->billing_ref === ($ref ?: null)) {
            return;
        }

        $before = ['purchased' => $licence->purchased_seats, 'status' => $licence->billing_status];
        $licence->forceFill(['purchased_seats' => $purchased, 'billing_status' => $status, 'billing_ref' => $ref ?: null])->save();
        $this->ledger->record($team, null, 'capacity_changed', null, ['from' => $before, 'to' => ['purchased' => $purchased, 'status' => $status], 'source' => 'billing']);
    }
}
