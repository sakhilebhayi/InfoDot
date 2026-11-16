<?php

namespace App\Services\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Support\Knowledge\Signature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The server-to-server calls to Dot.Finance about one purchase batch (R-53). Signed like every other Dot call (X-InfoDot-Timestamp and
 * X-InfoDot-Signature, HMAC-SHA256 over "{timestamp}.{raw body}"). InfoDot sends the batch it priced; Finance answers a checkout address.
 * Nothing here activates a licence: that only ever follows Finance's signed `paid` confirmation.
 *
 * Every failure is a LicenceRefused with a sentence that is safe to show, and always says nothing was charged. The call is idempotent on the
 * batch uuid at Finance, so asking again for the same batch gives the same checkout rather than a second one.
 */
class FinanceCheckout
{
    public function configured(): bool
    {
        return (bool) config('licensing.finance_connected') && filled(config('licensing.finance.url')) && filled(config('licensing.finance.secret'));
    }

    public function cancelEnabled(): bool
    {
        return $this->configured() && (bool) config('licensing.finance.cancel_enabled') && filled(config('licensing.finance.cancel_path'));
    }

    /**
     * @return string the checkout address to send the owner to
     *
     * @throws LicenceRefused
     */
    public function start(LicenceBatch $batch, Team $team, string $name, string $email): string
    {
        if (! $this->configured()) {
            throw new LicenceRefused('Paid licences cannot be bought just now: payments are not connected yet. Nothing has been charged.');
        }

        $body = (string) json_encode([
            'batch_uuid' => $batch->uuid, 'team_uuid' => (string) $team->uuid, 'quantity' => $batch->quantity, 'interval' => $batch->interval,
            'total_minor' => $batch->total_minor, 'currency' => $batch->currency, 'customer' => ['name' => $name, 'email' => $email],
        ], JSON_UNESCAPED_SLASHES);

        $json = $this->post((string) config('licensing.finance.checkout_path'), $body, $batch, 'checkout');
        $url = is_array($json) ? ($json['checkout_url'] ?? null) : null;

        if (! is_string($url) || ! $this->safeUrl($url)) {
            Log::warning('licence.checkout_answer_unusable', ['batch' => $batch->uuid]);

            throw new LicenceRefused('The payment service did not give a checkout address. Nothing has been charged. Please try again in a few minutes.');
        }

        return $url;
    }

    /**
     * Tells Finance the owner wants a batch to stop renewing, or to renew at a smaller quantity. Only when enabled; otherwise refused so the
     * owner is never told a subscription will stop when it will not.
     *
     * @throws LicenceRefused
     */
    public function requestChange(LicenceBatch $batch, Team $team, ?int $newQuantity): void
    {
        if (! $this->cancelEnabled()) {
            throw new LicenceRefused('Changing or cancelling a purchase online is not available yet. Nothing has been changed and nothing will stop renewing until it is.');
        }

        $body = (string) json_encode([
            'batch_uuid' => $batch->uuid, 'team_uuid' => (string) $team->uuid, 'action' => $newQuantity === null ? 'cancel' : 'reduce',
            'quantity_next' => $newQuantity, 'effective' => $batch->period_end?->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);

        $this->post((string) config('licensing.finance.cancel_path'), $body, $batch, 'change');
    }

    /** @return array<string, mixed>|null */
    private function post(string $path, string $body, LicenceBatch $batch, string $what): ?array
    {
        $url = rtrim((string) config('licensing.finance.url'), '/').'/'.ltrim($path, '/');

        try {
            $response = Http::acceptJson()->withBody($body, 'application/json')
                ->connectTimeout(3)->timeout(max(2, (int) config('licensing.finance.checkout_timeout_seconds', 8)))
                ->withHeaders(['X-InfoDot-Platform' => (string) config('licensing.finance.platform', 'infodot')] + Signature::headers((string) config('licensing.finance.secret'), $body))
                ->post($url);
        } catch (ConnectionException $e) {
            Log::warning('licence.finance_unreachable', ['what' => $what, 'batch' => $batch->uuid, 'reason' => class_basename($e)]);

            throw new LicenceRefused('The payment service did not answer in time. Nothing has been charged. Please try again in a few minutes.');
        } catch (Throwable $e) {
            Log::warning('licence.finance_failed', ['what' => $what, 'batch' => $batch->uuid, 'reason' => class_basename($e)]);

            throw new LicenceRefused('The payment service could not be reached. Nothing has been charged. Please try again in a few minutes.');
        }

        if ($response->status() === 409) {
            throw new LicenceRefused('The payment service already has a different purchase under this reference. Nothing has been charged. Start the purchase again.');
        }

        if (! $response->successful()) {
            Log::warning('licence.finance_refused', ['what' => $what, 'batch' => $batch->uuid, 'status' => $response->status()]);

            throw new LicenceRefused('The payment service could not start this just now. Nothing has been charged. Please try again in a few minutes.');
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /** Only an https address without credentials (plain http only on a local host), so a crafted answer cannot send the owner somewhere odd. */
    private function safeUrl(string $url): bool
    {
        $p = parse_url($url);

        if (! is_array($p) || ! isset($p['scheme'], $p['host']) || isset($p['user']) || isset($p['pass']) || strlen($url) > 2000) {
            return false;
        }

        return $p['scheme'] === 'https' || (app()->environment(['local', 'testing']) && $p['scheme'] === 'http');
    }
}
