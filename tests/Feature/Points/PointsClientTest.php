<?php

namespace Tests\Feature\Points;

use App\Models\User;
use App\Services\Points\PointsClient;
use App\Services\Points\PointsUnavailableException;
use App\Services\Points\RedemptionRefusedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PointsClientTest extends TestCase
{
    use FakesDopemine;
    use RefreshDatabase;

    private const KEY = 'form-key-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDopemine();
    }

    private function client(): PointsClient
    {
        return app(PointsClient::class);
    }

    public function test_the_catalogue_is_a_signed_get_over_the_empty_body(): void
    {
        $rewards = $this->client()->rewards();

        [$request] = $this->sent('GET', '/api/points/rewards');
        $this->assertSame('', $request->body());
        $this->assertSignedOverBody($request);
        $this->assertCount(3, $rewards);
        $this->assertSame('R50 billing credit', $rewards[0]->name);
        $this->assertSame(20, $rewards[0]->costPoints);
        $this->assertTrue($rewards[0]->available);
        $this->assertFalse($rewards[2]->available);
        $this->assertNull($rewards[1]->description);
    }

    public function test_a_redeem_is_signed_over_the_exact_json_it_sends_and_names_only_the_users_own_uuid(): void
    {
        $user = User::factory()->create();

        $receipt = $this->client()->redeem($user, 3, self::KEY);

        [$request] = $this->sent('POST', '/api/points/redemptions');
        $this->assertSignedOverBody($request);
        $this->assertSame(['user_uuid' => $user->uuid, 'reward_id' => 3, 'idempotency_key' => self::KEY], json_decode($request->body(), true));
        $this->assertStringStartsWith('application/json', $request->header('Content-Type')[0]);
        $this->assertSame(20, $receipt->pointsSpent);
        $this->assertSame(30, $receipt->balanceAfter);
        $this->assertSame('pending', $receipt->status);
        $this->assertFalse($receipt->replayed);
    }

    public function test_a_replayed_redemption_comes_back_as_replayed_and_forgets_the_cached_summary(): void
    {
        $user = User::factory()->create();
        $this->upstream['POST /api/points/redemptions'] = Http::response($this->receipt(), 200);
        $this->upstream['GET /api/points/{uuid}'] = Http::response($this->summaryBody(50));
        $this->client()->summary($user->uuid);
        $this->upstream['GET /api/points/{uuid}'] = Http::response($this->summaryBody(30));

        $receipt = $this->client()->redeem($user, 3, self::KEY);

        $this->assertTrue($receipt->replayed);
        $this->assertSame(30, $this->client()->summary($user->uuid)['balance']);
    }

    public function test_the_history_is_a_signed_get_that_names_the_users_uuid_in_the_query(): void
    {
        $user = User::factory()->create();
        $this->upstream['GET /api/points/redemptions'] = Http::response([
            ['redemption_id' => 'a', 'reward' => ['name' => 'R50 billing credit', 'kind' => 'billing_credit'], 'points_spent' => 20, 'status' => 'failed',
                'requested_at' => '2026-10-21T10:00:00+00:00', 'resolved_at' => '2026-10-22T08:30:00+00:00', 'points_returned' => true, 'message' => 'Your points were returned.'],
            ['redemption_id' => 'b', 'reward' => ['name' => 'Extra storage', 'kind' => 'capacity'], 'points_spent' => 40, 'status' => 'pending',
                'requested_at' => '2026-10-20T10:00:00+00:00', 'resolved_at' => null, 'points_returned' => false, 'message' => null],
            ['redemption_id' => 'c', 'reward' => ['name' => 'Odd'], 'points_spent' => 1, 'status' => 'mystery', 'requested_at' => '2026-10-20T10:00:00+00:00'],
        ]);

        $rows = $this->client()->redemptions($user);

        [$request] = $this->sent('GET', '/api/points/redemptions');
        $this->assertSame('', $request->body());
        $this->assertSignedOverBody($request);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $this->assertSame(['user_uuid' => $user->uuid], $query);
        $this->assertCount(2, $rows, 'A row with an unknown status is skipped, not shown.');
        $this->assertTrue($rows[0]->pointsReturned);
        $this->assertSame('Your points were returned.', $rows[0]->message);
        $this->assertNull($rows[1]->resolvedAt);
    }

    /** @return array<string, array{0: int, 1: array<string, mixed>, 2: string, 3: string}> */
    public static function refusals(): array
    {
        return [
            'reward gone' => [404, ['code' => 'reward_unavailable', 'message' => 'raw upstream text'], 'reward_unavailable', 'not available any more'],
            'not enough points' => [409, ['code' => 'insufficient_points', 'message' => 'raw upstream text', 'points_needed' => 20, 'balance' => 5], 'insufficient_points', 'It costs 20 and you have 5'],
            'out of stock' => [409, ['code' => 'out_of_stock', 'message' => 'raw upstream text'], 'out_of_stock', 'run out'],
            'monthly limit' => [409, ['code' => 'monthly_limit_reached', 'message' => 'raw upstream text'], 'monthly_limit_reached', 'limit for this month'],
            'key reused' => [409, ['code' => 'idempotency_key_reused', 'message' => 'raw upstream text'], 'idempotency_key_reused', 'different reward'],
            'validation' => [422, ['code' => 'validation_failed', 'message' => 'raw upstream text', 'errors' => ['user_uuid' => ['bad']]], 'validation_failed', 'could not process'],
            'rate limited' => [429, ['code' => 'rate_limited', 'message' => 'raw upstream text', 'retry_after' => 1800], 'rate_limited', 'about 30 minute'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_every_documented_error_code_becomes_a_refusal_with_our_own_words(int $status, array $body, string $reason, string $phrase): void
    {
        $this->upstream['POST /api/points/redemptions'] = Http::response($body, $status);

        try {
            $this->client()->redeem(User::factory()->create(), 3, self::KEY);
            $this->fail('A refusal was expected.');
        } catch (RedemptionRefusedException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertStringContainsString($phrase, $e->getMessage());
            $this->assertStringNotContainsString('raw upstream text', $e->getMessage());
            $this->assertStringContainsString('Nothing was spent', $e->getMessage());
        }
    }

    public function test_the_insufficient_points_refusal_carries_the_numbers_and_rate_limits_the_wait(): void
    {
        $this->upstream['POST /api/points/redemptions'] = Http::response(['code' => 'insufficient_points', 'points_needed' => 20, 'balance' => 5], 409);
        try {
            $this->client()->redeem(User::factory()->create(), 3, self::KEY);
        } catch (RedemptionRefusedException $e) {
            $this->assertSame([20, 5], [$e->pointsNeeded, $e->balance]);
        }

        $this->upstream['POST /api/points/redemptions'] = Http::response(['code' => 'rate_limited'], 429, ['Retry-After' => '600']);
        try {
            $this->client()->redeem(User::factory()->create(), 3, self::KEY);
        } catch (RedemptionRefusedException $e) {
            $this->assertSame(600, $e->retryAfter, 'The Retry-After header is used when the body has no retry_after.');
        }
    }

    /** @return array<string, array{0: callable}> */
    public static function outages(): array
    {
        return [
            'bad signature' => [fn () => Http::response(['code' => 'invalid_signature', 'message' => 'SECRET-LEAK-CANARY'], 401)],
            'server error' => [fn () => Http::response('<html>SECRET-LEAK-CANARY</html>', 500)],
            'bad gateway' => [fn () => Http::response('', 502)],
            'unreachable' => [fn () => fn () => throw new ConnectionException('cURL error 28 SECRET-LEAK-CANARY')],
            'accepted but not the documented shape' => [fn () => Http::response(['ok' => true, 'note' => 'SECRET-LEAK-CANARY'], 201)],
            'unknown error code' => [fn () => Http::response(['code' => 'brand_new_code', 'message' => 'SECRET-LEAK-CANARY'], 418)],
            'not json' => [fn () => Http::response('SECRET-LEAK-CANARY', 200)],
        ];
    }

    #[DataProvider('outages')]
    public function test_anything_else_is_unavailable_and_never_leaks_the_secret_or_the_upstream_body(callable $answer): void
    {
        Log::spy();
        $this->upstream['POST /api/points/redemptions'] = $answer();
        $this->upstream['GET /api/points/rewards'] = $answer();
        $this->upstream['GET /api/points/redemptions'] = $answer();

        foreach ([
            fn () => $this->client()->redeem(User::factory()->create(), 3, self::KEY),
            fn () => $this->client()->rewards(),
            fn () => $this->client()->redemptions(User::factory()->create()),
        ] as $call) {
            $this->app->forgetInstance(PointsClient::class);
            try {
                $call();
                $this->fail('Unavailable was expected.');
            } catch (PointsUnavailableException $e) {
                $this->assertSame('Points are unavailable right now.', $e->getMessage());
                $this->assertStringNotContainsString('CANARY', $e->getMessage());
            }
        }

        $logged = [];
        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use (&$logged) {
            $logged[] = json_encode($args);

            return true;
        });
        $this->assertNotEmpty($logged);
        $all = implode(' ', $logged);
        $this->assertStringNotContainsString('CANARY', $all);
        $this->assertStringNotContainsString(self::SECRET, $all);
        $this->assertStringNotContainsString('sha256=', $all);
        $this->assertStringNotContainsString('dopemine.test', $all);
    }

    public function test_after_one_unreachable_call_the_rest_of_the_request_fails_fast(): void
    {
        $this->upstream['GET /api/points/rewards'] = fn () => throw new ConnectionException('down');
        $client = $this->client();

        try {
            $client->rewards();
        } catch (PointsUnavailableException) {
        }
        $this->assertNull($client->summary('00000000-0000-4000-8000-000000000000'));
        try {
            $client->redemptions(User::factory()->create());
            $this->fail('Unavailable was expected.');
        } catch (PointsUnavailableException $e) {
            $this->assertSame(PointsUnavailableException::UNREACHABLE, $e->reason);
        }
        $this->assertSame(1, $this->reached, 'Only the first call went out.');
    }

    public function test_nothing_is_sent_when_the_secret_is_not_configured(): void
    {
        config(['services.dopemine.secret' => null]);

        foreach ([fn () => $this->client()->rewards(), fn () => $this->client()->redeem(User::factory()->create(), 3, self::KEY), fn () => $this->client()->redemptions(User::factory()->create())] as $call) {
            try {
                $call();
                $this->fail('Unavailable was expected.');
            } catch (PointsUnavailableException $e) {
                $this->assertSame(PointsUnavailableException::NOT_CONFIGURED, $e->reason);
            }
        }
        Http::assertNothingSent();
    }

    public function test_a_key_that_does_not_fit_the_contract_is_refused_before_anything_is_sent(): void
    {
        foreach (['short', str_repeat('a', 65), 'has space in it 123', "newline\n12345678"] as $key) {
            try {
                $this->client()->redeem(User::factory()->create(), 3, $key);
                $this->fail('Key should be refused.');
            } catch (InvalidArgumentException) {
            }
        }
        Http::assertNothingSent();
    }
}
