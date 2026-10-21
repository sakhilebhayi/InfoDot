<?php

namespace Tests\Feature\Points;

use App\Services\Points\ExpiryResultRefusedException;
use App\Services\Points\PointsClient;
use App\Services\Points\PointsUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpiryWarningsClientTest extends TestCase
{
    use FakesDopemine;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDopemine();
    }

    private function client(): PointsClient
    {
        $this->app->forgetInstance(PointsClient::class);

        return app(PointsClient::class);
    }

    public function test_pulling_warnings_is_a_signed_get_over_the_empty_body_with_the_limit_in_the_query(): void
    {
        $uuid = '9b35d390-7031-4946-807e-784f03d09f42';
        $this->upstream['GET /api/points/expiry-warnings'] = Http::response([
            ['warning_id' => 7, 'user_uuid' => $uuid, 'points' => 12, 'expires_on' => '2026-11-04'],
            ['warning_id' => 'w-8', 'user_uuid' => $uuid, 'points' => 1, 'expires_on' => '2026-12-31'],
        ]);

        $warnings = $this->client()->expiryWarnings(25);

        [$request] = $this->sent('GET', '/api/points/expiry-warnings');
        $this->assertSame('', $request->body());
        $this->assertSignedOverBody($request);
        $this->assertSame('limit=25', parse_url($request->url(), PHP_URL_QUERY));
        $this->assertCount(2, $warnings);
        $this->assertSame(7, $warnings[0]->warningId);
        $this->assertSame($uuid, $warnings[0]->userUuid);
        $this->assertSame(12, $warnings[0]->points);
        $this->assertSame('2026-11-04', $warnings[0]->expiresOn->format('Y-m-d'));
        $this->assertSame('w-8', $warnings[1]->warningId);
    }

    public function test_the_limit_is_kept_between_1_and_100(): void
    {
        $this->upstream['GET /api/points/expiry-warnings'] = Http::response([]);

        $this->client()->expiryWarnings(5000);
        $this->client()->expiryWarnings(0);

        $queries = array_map(fn ($r) => parse_url($r->url(), PHP_URL_QUERY), $this->sent('GET', '/api/points/expiry-warnings'));
        $this->assertSame(['limit=100', 'limit=1'], $queries);
    }

    public function test_nothing_due_is_an_empty_list_and_rows_not_in_the_documented_shape_are_skipped(): void
    {
        $uuid = '9b35d390-7031-4946-807e-784f03d09f42';
        $this->upstream['GET /api/points/expiry-warnings'] = Http::response([]);
        $this->assertSame([], $this->client()->expiryWarnings());

        $this->upstream['GET /api/points/expiry-warnings'] = Http::response([
            ['warning_id' => 1, 'user_uuid' => $uuid, 'points' => 0, 'expires_on' => '2026-11-04'],
            ['warning_id' => 2, 'user_uuid' => $uuid, 'points' => 5, 'expires_on' => '2026-13-45'],
            ['warning_id' => 3, 'user_uuid' => $uuid, 'points' => '5', 'expires_on' => '2026-11-04'],
            ['warning_id' => '../x', 'user_uuid' => $uuid, 'points' => 5, 'expires_on' => '2026-11-04'],
            ['warning_id' => 5, 'user_uuid' => 'not a uuid!', 'points' => 5, 'expires_on' => '2026-11-04'],
            'junk',
            ['warning_id' => 6, 'user_uuid' => $uuid, 'points' => 5, 'expires_on' => '2026-11-04'],
        ]);
        $warnings = $this->client()->expiryWarnings();

        $this->assertCount(1, $warnings);
        $this->assertSame(6, $warnings[0]->warningId);
    }

    public function test_a_result_report_is_signed_over_the_exact_json_it_sends(): void
    {
        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = Http::response(['warning_id' => 7, 'state' => 'delivered']);

        $receipt = $this->client()->reportExpiryResult(7, 'delivered');

        [$request] = $this->sent('POST', '/api/points/expiry-warnings/7/result');
        $this->assertSignedOverBody($request);
        $this->assertSame('{"outcome":"delivered"}', $request->body());
        $this->assertStringStartsWith('application/json', $request->header('Content-Type')[0]);
        $this->assertSame('7', $receipt->warningId);
        $this->assertSame('delivered', $receipt->state);
    }

    #[DataProvider('outcomes')]
    public function test_each_documented_outcome_can_be_reported(string $outcome): void
    {
        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = Http::response(['warning_id' => 'abc-1', 'state' => 'x']);

        $this->client()->reportExpiryResult('abc-1', $outcome);

        [$request] = $this->sent('POST', '/api/points/expiry-warnings/abc-1/result');
        $this->assertSame(['outcome' => $outcome], json_decode($request->body(), true));
    }

    public static function outcomes(): array
    {
        return ['delivered' => ['delivered'], 'retry' => ['retry'], 'permanent_failure' => ['permanent_failure']];
    }

    public function test_an_unknown_outcome_or_a_hostile_id_is_refused_before_anything_is_sent(): void
    {
        foreach ([[7, 'sent'], [7, ''], ['../../x', 'delivered'], ['a/b', 'delivered'], [0, 'delivered'], ['', 'delivered']] as [$id, $outcome]) {
            try {
                $this->client()->reportExpiryResult($id, $outcome);
                $this->fail('InvalidArgumentException was expected.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(0, $this->reached);
    }

    public function test_an_unknown_warning_and_an_invalid_report_are_refusals_not_outages(): void
    {
        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = Http::response(['code' => 'warning_not_found', 'message' => 'x'], 404);
        try {
            $this->client()->reportExpiryResult(7, 'delivered');
            $this->fail('Refusal expected.');
        } catch (ExpiryResultRefusedException $e) {
            $this->assertSame(ExpiryResultRefusedException::NOT_FOUND, $e->reason);
        }

        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = Http::response(['code' => 'validation_failed', 'message' => 'x'], 422);
        try {
            $this->client()->reportExpiryResult(7, 'delivered');
            $this->fail('Refusal expected.');
        } catch (ExpiryResultRefusedException $e) {
            $this->assertSame(ExpiryResultRefusedException::INVALID, $e->reason);
        }
    }

    public static function outages(): array
    {
        return [
            'bad signature' => [fn () => Http::response(['code' => 'invalid_signature', 'message' => 'SECRET-LEAK-CANARY'], 401), PointsUnavailableException::REJECTED],
            'server error' => [fn () => Http::response(['code' => 'boom', 'message' => 'SECRET-LEAK-CANARY'], 503), PointsUnavailableException::UNREACHABLE],
            'connection error' => [fn () => fn () => throw new ConnectionException('SECRET-LEAK-CANARY'), PointsUnavailableException::UNREACHABLE],
            'not json' => [fn () => Http::response('SECRET-LEAK-CANARY', 200), PointsUnavailableException::MALFORMED],
            'wrong shape' => [fn () => Http::response(['x' => 'SECRET-LEAK-CANARY'], 200), PointsUnavailableException::MALFORMED],
            'unknown error code' => [fn () => Http::response(['code' => 'brand_new_code', 'message' => 'SECRET-LEAK-CANARY'], 418), PointsUnavailableException::MALFORMED],
            'not_found with another code' => [fn () => Http::response(['code' => 'route_missing', 'message' => 'SECRET-LEAK-CANARY'], 404), PointsUnavailableException::MALFORMED],
        ];
    }

    #[DataProvider('outages')]
    public function test_outages_are_unavailable_and_never_leak_the_secret_or_the_upstream_body(callable $answer, string $reason): void
    {
        Log::spy();
        $this->upstream['GET /api/points/expiry-warnings'] = $answer();
        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = $answer();

        foreach ([
            fn () => $this->client()->expiryWarnings(),
            fn () => $this->client()->reportExpiryResult(7, 'delivered'),
        ] as $call) {
            try {
                $call();
                $this->fail('Unavailable was expected.');
            } catch (PointsUnavailableException $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertSame('Points are unavailable right now.', $e->getMessage());
            }
        }

        $logged = [];
        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use (&$logged) {
            $logged[] = json_encode($args);

            return true;
        });
        $all = implode(' ', $logged);
        $this->assertStringNotContainsString('CANARY', $all);
        $this->assertStringNotContainsString(self::SECRET, $all);
        $this->assertStringNotContainsString('sha256=', $all);
        $this->assertStringNotContainsString('dopemine.test', $all);
    }

    public function test_after_one_unreachable_call_the_rest_fails_fast(): void
    {
        $this->upstream['GET /api/points/expiry-warnings'] = fn () => throw new ConnectionException('down');
        $client = $this->client();

        try {
            $client->expiryWarnings();
        } catch (PointsUnavailableException) {
        }
        $reached = $this->reached;
        try {
            $client->reportExpiryResult(7, 'retry');
            $this->fail('Unavailable was expected.');
        } catch (PointsUnavailableException $e) {
            $this->assertSame(PointsUnavailableException::UNREACHABLE, $e->reason);
        }

        $this->assertSame($reached, $this->reached);
    }

    public function test_without_a_url_or_secret_nothing_is_sent(): void
    {
        foreach ([['services.dopemine.secret', null], ['services.dopemine.url', '']] as [$key, $value]) {
            $this->fakeDopemine();
            config([$key => $value]);
            foreach ([fn () => $this->client()->expiryWarnings(), fn () => $this->client()->reportExpiryResult(7, 'delivered')] as $call) {
                try {
                    $call();
                    $this->fail('Unavailable was expected.');
                } catch (PointsUnavailableException $e) {
                    $this->assertSame(PointsUnavailableException::NOT_CONFIGURED, $e->reason);
                }
            }
        }

        $this->assertSame(0, $this->reached);
    }
}
