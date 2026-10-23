<?php

namespace Tests\Feature\Points;

use App\Services\Points\DigestResultRefusedException;
use App\Services\Points\PointsClient;
use App\Services\Points\PointsUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DigestsClientTest extends TestCase
{
    use FakesDopemine;
    use RefreshDatabase;

    private const UUID = '9b35d390-7031-4946-807e-784f03d09f42';

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

    /** @return array<string, mixed> */
    private function row(array $override = []): array
    {
        return $override + [
            'digest_id' => '5d0c8f0e-6a1b-4f0c-9d54-2f1f3a9d7b21',
            'user_uuid' => self::UUID,
            'period' => 'week',
            'period_start' => '2026-10-12',
            'period_end' => '2026-10-18',
            'points_earned' => 20,
            'awards' => [
                ['rule_key' => 'infodot.answer.accepted', 'label' => 'Answer accepted.', 'count' => 1, 'points' => 10],
                ['rule_key' => 'infodot.answer.published', 'label' => 'Substantive, original answer.', 'count' => 2, 'points' => 10],
            ],
            'points_reversed' => 0,
            'balance' => 25,
            'expiring_next_30_days' => null,
        ];
    }

    public function test_pulling_digests_is_a_signed_get_over_the_empty_body_with_period_and_limit_in_the_query(): void
    {
        $this->upstream['GET /api/points/digests'] = Http::response([$this->row(['expiring_next_30_days' => ['points' => 12, 'first_at' => '2026-11-04T00:00:00+00:00'], 'points_reversed' => 3])]);

        $digests = $this->client()->digests('week', 25);

        [$request] = $this->sent('GET', '/api/points/digests');
        $this->assertSame('', $request->body());
        $this->assertSignedOverBody($request);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
        $this->assertSame(['period' => 'week', 'limit' => '25'], $q);
        $this->assertCount(1, $digests);
        $d = $digests[0];
        $this->assertSame('5d0c8f0e-6a1b-4f0c-9d54-2f1f3a9d7b21', $d->digestId);
        $this->assertSame(self::UUID, $d->userUuid);
        $this->assertSame('week', $d->period);
        $this->assertSame('2026-10-12', $d->periodStart->format('Y-m-d'));
        $this->assertSame('2026-10-18', $d->periodEnd->format('Y-m-d'));
        $this->assertSame(20, $d->pointsEarned);
        $this->assertSame(3, $d->pointsReversed);
        $this->assertSame(25, $d->balance);
        $this->assertSame('Answer accepted.', $d->awards[0]['label']);
        $this->assertSame(2, $d->awards[1]['count']);
        $this->assertSame(12, $d->expiring['points']);
        $this->assertSame('2026-11-04', $d->expiring['first_at']->format('Y-m-d'));
    }

    public function test_the_limit_is_kept_between_1_and_200_and_the_period_must_be_week_or_month(): void
    {
        $this->upstream['GET /api/points/digests'] = Http::response([]);

        $this->client()->digests('month', 5000);
        $this->client()->digests('month', 0);

        $queries = array_map(fn ($r) => parse_url($r->url(), PHP_URL_QUERY), $this->sent('GET', '/api/points/digests'));
        $this->assertSame(['period=month&limit=200', 'period=month&limit=1'], $queries);

        $this->expectException(InvalidArgumentException::class);
        $this->client()->digests('year');
    }

    public function test_rows_not_in_the_documented_shape_are_skipped(): void
    {
        $bad = [
            $this->row(['points_earned' => 0]),
            $this->row(['points_earned' => '20']),
            $this->row(['period' => 'month']),
            $this->row(['period_start' => '2026-13-45']),
            $this->row(['period_end' => '2026-10-01']),
            $this->row(['digest_id' => '../x']),
            $this->row(['user_uuid' => 'not a uuid!']),
            $this->row(['awards' => [['rule_key' => 'x', 'label' => 'x', 'count' => 0, 'points' => 1]]]),
            $this->row(['points_reversed' => -1]),
            $this->row(['balance' => null]),
            $this->row(['expiring_next_30_days' => ['points' => 0, 'first_at' => '2026-11-04']]),
            'junk',
        ];
        $this->upstream['GET /api/points/digests'] = Http::response([...$bad, $this->row(['digest_id' => 'good-1'])]);

        $digests = $this->client()->digests('week');

        $this->assertCount(1, $digests);
        $this->assertSame('good-1', $digests[0]->digestId);
    }

    public function test_a_result_report_is_signed_over_the_exact_json_it_sends(): void
    {
        $this->upstream['POST /api/points/digests/{id}/result'] = Http::response(['digest_id' => 'abc-1', 'state' => 'delivered']);

        $receipt = $this->client()->reportDigestResult('abc-1', 'delivered');

        [$request] = $this->sent('POST', '/api/points/digests/abc-1/result');
        $this->assertSignedOverBody($request);
        $this->assertSame('{"outcome":"delivered"}', $request->body());
        $this->assertStringStartsWith('application/json', $request->header('Content-Type')[0]);
        $this->assertSame('abc-1', $receipt->digestId);
        $this->assertSame('delivered', $receipt->state);
    }

    #[DataProvider('outcomes')]
    public function test_each_documented_outcome_can_be_reported(string $outcome): void
    {
        $this->upstream['POST /api/points/digests/{id}/result'] = Http::response(['digest_id' => 'abc-1', 'state' => 'x']);

        $this->client()->reportDigestResult('abc-1', $outcome);

        [$request] = $this->sent('POST', '/api/points/digests/abc-1/result');
        $this->assertSame(['outcome' => $outcome], json_decode($request->body(), true));
    }

    public static function outcomes(): array
    {
        return ['delivered' => ['delivered'], 'retry' => ['retry'], 'skipped' => ['skipped']];
    }

    public function test_an_unknown_outcome_or_a_hostile_id_is_refused_before_anything_is_sent(): void
    {
        foreach ([['abc', 'sent'], ['abc', ''], ['abc', 'permanent_failure'], ['../../x', 'delivered'], ['a/b', 'delivered'], ['', 'delivered']] as [$id, $outcome]) {
            try {
                $this->client()->reportDigestResult($id, $outcome);
                $this->fail('InvalidArgumentException was expected.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(0, $this->reached);
    }

    public function test_an_unknown_digest_and_an_invalid_report_are_refusals_not_outages(): void
    {
        $this->upstream['POST /api/points/digests/{id}/result'] = Http::response(['code' => 'digest_not_found', 'message' => 'x'], 404);
        try {
            $this->client()->reportDigestResult('abc', 'delivered');
            $this->fail('Refusal expected.');
        } catch (DigestResultRefusedException $e) {
            $this->assertSame(DigestResultRefusedException::NOT_FOUND, $e->reason);
        }

        $this->upstream['POST /api/points/digests/{id}/result'] = Http::response(['code' => 'validation_failed', 'message' => 'x'], 422);
        try {
            $this->client()->reportDigestResult('abc', 'delivered');
            $this->fail('Refusal expected.');
        } catch (DigestResultRefusedException $e) {
            $this->assertSame(DigestResultRefusedException::INVALID, $e->reason);
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
        ];
    }

    #[DataProvider('outages')]
    public function test_outages_are_unavailable_and_never_leak_the_secret_or_the_upstream_body(callable $answer, string $reason): void
    {
        Log::spy();
        $this->upstream['GET /api/points/digests'] = $answer();
        $this->upstream['POST /api/points/digests/{id}/result'] = $answer();

        foreach ([
            fn () => $this->client()->digests('week'),
            fn () => $this->client()->reportDigestResult('abc', 'delivered'),
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
        $this->assertStringNotContainsString(self::UUID, $all);
    }

    public function test_without_a_url_or_secret_nothing_is_sent(): void
    {
        foreach ([['services.dopemine.secret', null], ['services.dopemine.url', '']] as [$key, $value]) {
            $this->fakeDopemine();
            config([$key => $value]);
            foreach ([fn () => $this->client()->digests('week'), fn () => $this->client()->reportDigestResult('abc', 'delivered')] as $call) {
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
