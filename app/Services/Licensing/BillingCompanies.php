<?php

namespace App\Services\Licensing;

use App\Models\Team;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells Dot.Billing that a company exists, was renamed, or was deleted (POST /api/companies, signed like the entitlements call), so Billing can
 * answer for it before its owner has ever opened Billing, and can flag a deleted company instead of billing it unseen. Billing only registers and
 * flags: it never creates accounts and never cancels anything because of this. A failure is logged and never reaches the person who made the change.
 */
class BillingCompanies
{
    public function __construct(private readonly BillingEntitlements $entitlements) {}

    public function sync(Team $team, bool $deleted = false): bool
    {
        if (! $this->entitlements->configured() || $team->personal_team || blank($team->uuid)) {
            return false;
        }

        $body = json_encode(['company_uuid' => (string) $team->uuid, 'name' => mb_substr((string) $team->name, 0, 120), 'deleted' => $deleted], JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::acceptJson()->timeout((int) config('licensing.billing.timeout_seconds', 3))
                ->withHeaders(['X-InfoDot-Platform' => (string) config('licensing.billing.platform', 'infodot')] + Signature::headers((string) config('licensing.billing.secret'), $body))
                ->withBody($body, 'application/json')->post(rtrim((string) config('licensing.billing.url'), '/').'/api/companies');
        } catch (Throwable $e) {
            Log::warning('Billing company sync unreachable.', ['reason' => class_basename($e)]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Billing company sync refused or failed.', ['status' => $response->status()]);

            return false;
        }

        return true;
    }
}
