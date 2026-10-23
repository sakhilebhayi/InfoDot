<?php

namespace Tests\Feature\Points;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A stand-in for the Dot.Dopemine points API (never the real service). Each test sets the answer for the calls it
 * cares about in $this->upstream, keyed "METHOD /path"; every request the app sends is recorded in Http's own log.
 */
trait FakesDopemine
{
    protected const SECRET = 'test-only-shared-secret-3f9a';

    /** @var array<string, mixed> "METHOD /path" => Http::response(...) | callable(Request): PromiseInterface */
    protected array $upstream = [];

    /** How many requests reached the fake, including ones it answered with a thrown connection error. */
    protected int $reached = 0;

    /** @var list<Request> */
    protected array $requests = [];

    protected function fakeDopemine(): void
    {
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => self::SECRET]);

        $this->upstream += [
            'GET /api/points/rewards' => Http::response([
                ['id' => 3, 'name' => 'R50 billing credit', 'kind' => 'billing_credit', 'description' => 'Off your next invoice.', 'cost_points' => 20, 'available' => true],
                ['id' => 4, 'name' => 'Extra storage', 'kind' => 'capacity', 'description' => null, 'cost_points' => 40, 'available' => true],
                ['id' => 5, 'name' => 'Sticker pack', 'kind' => 'marketplace', 'description' => 'Posted to you.', 'cost_points' => 10, 'available' => false],
            ]),
            'GET /api/points/redemptions' => Http::response([]),
            'POST /api/points/redemptions' => Http::response($this->receipt(), 201),
        ];

        Http::fake(function (Request $request) {
            $this->reached++;
            $this->requests[] = $request;
            $path = parse_url($request->url(), PHP_URL_PATH);
            if (preg_match('#^/api/points/[0-9a-f-]{36}$#', (string) $path)) {
                $path = '/api/points/{uuid}';
            }
            $path = preg_replace('#^/api/points/expiry-warnings/[A-Za-z0-9._-]+/result$#', '/api/points/expiry-warnings/{id}/result', (string) $path);
            $path = preg_replace('#^/api/points/digests/[A-Za-z0-9._-]+/result$#', '/api/points/digests/{id}/result', (string) $path);
            $answer = $this->upstream[$request->method().' '.$path] ?? Http::response(['code' => 'not_found', 'message' => 'x'], 404);

            return is_callable($answer) ? $answer($request) : $answer;
        });
    }

    /** @return array<string, mixed> */
    protected function summaryBody(int $balance = 50, array $extra = []): array
    {
        return $extra + [
            'balance' => $balance,
            'total_earned' => 120,
            'expiring_soon' => ['points' => 12, 'first_at' => '2026-11-04'],
            'next_expiry' => '2026-11-04',
            'next_expiry_points' => 12,
            'history' => [],
        ];
    }

    /** @return array<string, mixed> */
    protected function receipt(int $spent = 20, int $balanceAfter = 30, string $status = 'pending'): array
    {
        return [
            'redemption_id' => '9b35d390-7031-4946-807e-784f03d09f42',
            'status' => $status,
            'reward' => ['id' => 3, 'name' => 'R50 billing credit', 'kind' => 'billing_credit'],
            'points_spent' => $spent,
            'balance_after' => $balanceAfter,
        ];
    }

    /** The requests the app sent to the fake for a method and path (including ones the fake answered by failing). */
    protected function sent(string $method, string $path): array
    {
        return array_values(array_filter($this->requests, fn (Request $r) => $r->method() === $method && parse_url($r->url(), PHP_URL_PATH) === $path));
    }

    protected function assertSignedOverBody(Request $request): void
    {
        $timestamp = $request->header('X-InfoDot-Timestamp')[0] ?? '';
        $signature = $request->header('X-InfoDot-Signature')[0] ?? '';

        $this->assertMatchesRegularExpression('/\A\d{10}\z/', $timestamp);
        $this->assertEqualsWithDelta(time(), (int) $timestamp, 5);
        $this->assertSame('sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), self::SECRET), $signature);
    }
}
