<?php

namespace App\Services\Seminars;

use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * InfoDot's side of paid seminars: asks Dot.Billing (which owns Paystack) to set up a host's payout account, start a ticket payment,
 * and refund or cancel one. Every call is signed with this platform's secret. Nothing here ever logs a request body, because the
 * payout call carries a bank account number that must not be written anywhere.
 */
class BillingMarketplace
{
    public function configured(): bool
    {
        return (bool) config('seminars.paid.enabled')
            && filled(config('licensing.billing.url'))
            && filled(config('licensing.billing.secret'));
    }

    /** @return list<array{name: string, code: string}> */
    public function banks(): array
    {
        return Cache::remember('seminars:banks', 3600, function () {
            $json = $this->send('GET', '/api/payout-banks');

            return collect($json['banks'] ?? $json)->filter(fn ($b) => is_array($b) && isset($b['name'], $b['code']))
                ->map(fn ($b) => ['name' => (string) $b['name'], 'code' => (string) $b['code']])->values()->all();
        });
    }

    /** @return array{id: int, status: string, last4: string, bank_code: string} */
    public function createPayoutAccount(string $hostRef, string $businessName, string $bankCode, string $accountNumber): array
    {
        $json = $this->send('POST', '/api/payout-accounts', ['host_ref' => $hostRef, 'business_name' => $businessName, 'settlement_bank' => $bankCode, 'account_number' => $accountNumber]);

        return ['id' => (int) $json['id'], 'status' => (string) ($json['status'] ?? 'active'), 'last4' => (string) $json['last4'], 'bank_code' => (string) ($json['bank_code'] ?? $bankCode)];
    }

    /** @return array{reference: string, authorization_url: string, status: string} */
    public function createTicket(string $ticketRef, int $payoutAccountId, string $buyerEmail, int $amountCents, string $currency, string $description, string $returnUrl): array
    {
        $json = $this->send('POST', '/api/seminar-tickets', [
            'ticket_ref' => $ticketRef, 'payout_account_id' => $payoutAccountId, 'buyer_email' => $buyerEmail,
            'amount_cents' => $amountCents, 'currency' => $currency, 'description' => mb_substr($description, 0, 140), 'return_url' => $returnUrl,
        ]);

        $url = (string) ($json['authorization_url'] ?? '');

        if (! str_starts_with($url, 'https://') || ! preg_match('/(^|\.)paystack\.(com|co)(\/|$)/i', (string) parse_url($url, PHP_URL_HOST).'/')) {
            throw new MarketplaceUnavailable('Payments are unavailable right now. Nothing was charged.');
        }

        return ['reference' => (string) ($json['reference'] ?? ''), 'authorization_url' => $url, 'status' => (string) ($json['status'] ?? 'pending')];
    }

    /** No amount = the whole remaining ticket. A partial refund needs an amount and a key that makes a repeat the same refund. */
    public function refund(string $ticketRef, ?int $amountCents = null, ?string $refundKey = null): void
    {
        $body = $amountCents === null ? [] : ['amount_cents' => $amountCents, 'refund_key' => (string) $refundKey];
        $this->send('POST', '/api/seminar-tickets/'.rawurlencode($ticketRef).'/refund', $body);
    }

    public function cancel(string $ticketRef): void
    {
        $this->send('POST', '/api/seminar-tickets/'.rawurlencode($ticketRef).'/cancel', []);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, ?array $body = null): array
    {
        if (! $this->configured()) {
            throw new MarketplaceUnavailable('Paid seminars are not switched on.');
        }

        $raw = $method === 'GET' ? '' : json_encode($body ?? [], JSON_UNESCAPED_SLASHES);
        $request = Http::acceptJson()->timeout(8)->withHeaders(['X-InfoDot-Platform' => (string) config('licensing.billing.platform', 'infodot')]
            + Signature::headers((string) config('licensing.billing.secret'), $raw));

        try {
            $response = $method === 'GET'
                ? $request->get(rtrim((string) config('licensing.billing.url'), '/').$path)
                : $request->withBody($raw, 'application/json')->post(rtrim((string) config('licensing.billing.url'), '/').$path);
        } catch (Throwable $e) {
            Log::warning('Billing marketplace unreachable.', ['path' => $path, 'reason' => class_basename($e)]);

            throw new MarketplaceUnavailable('Payments are unavailable right now. Nothing was charged.');
        }

        if (! $response->successful()) {
            Log::warning('Billing marketplace refused.', ['path' => $path, 'status' => $response->status()]);

            throw new MarketplaceUnavailable($response->status() === 422
                ? 'Billing did not accept those details. Check them and try again.'
                : 'Payments are unavailable right now. Nothing was charged.');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
