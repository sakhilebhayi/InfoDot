<?php

namespace Tests\Feature\Knowledge;

use App\Models\LibraryOutcome;
use App\Services\Knowledge\AnswerService;
use App\Services\Library\LibraryOutcomeOutbox;
use App\Services\Library\LibraryService;
use App\Services\Library\MemoryRecommendationsClient;
use App\Services\Library\OutcomeRefusedException;
use App\Services\Library\RecommendationsUnavailableException;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** InfoDot's side of the conversation with Dot.Memory: reading Brain's recommendations and reporting moderator decisions. */
class MemoryRecommendationsClientTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private const SECRET = 'infodot-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config(['knowledge.memory_url' => 'https://memory.test', 'knowledge.infodot_secret' => self::SECRET]);
        Carbon::setTestNow('2026-10-27 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Replace every earlier stub: Http::fake() adds to them, and the first match wins. */
    private function memory(mixed $response): void
    {
        Http::swap(new Factory);
        Http::fake(['memory.test/*' => $response]);
    }

    private function client(): MemoryRecommendationsClient
    {
        return app(MemoryRecommendationsClient::class);
    }

    /** @return array<string, mixed> */
    private function rec(string $id = 'dec-1', string $ref = 'infodot:answer:5', array $over = []): array
    {
        return $over + [
            'decision_event_id' => $id, 'contribution_ref' => $ref, 'verdict' => 'recommend_for_library', 'confidence' => 0.82,
            'rationale' => [['rule' => 'independent_acceptance', 'effect' => '+0.40', 'detail' => 'Accepted by someone other than the author.']],
            'rule_version' => 'v1', 'decided_at' => '2026-10-26T10:00:00Z', 'status' => 'open', 'outcome_at' => null,
        ];
    }

    private function queuedOutcome(string $id = 'dec-1', string $outcome = 'adopted'): LibraryOutcome
    {
        return LibraryOutcome::create([
            'decision_event_id' => $id, 'contribution_ref' => 'infodot:answer:5', 'outcome' => $outcome, 'actor_id' => null,
            'actor_ref' => '9d1c0b52-0a8c-4a5e-9f43-2b3c1d9e7a10', 'next_attempt_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Reading recommendations
    // ---------------------------------------------------------------------------------------------------------------

    public function test_it_asks_memory_with_a_signed_get_of_an_empty_body_and_the_documented_query(): void
    {
        $this->memory(Http::response(['items' => [], 'next_cursor' => null]));

        $this->client()->recommendations('open', 25, 'cur-1');

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $this->assertSame('GET', $request->method());
            $this->assertSame('memory.test', parse_url($request->url(), PHP_URL_HOST));
            $this->assertSame('/api/knowledge-recommendations', parse_url($request->url(), PHP_URL_PATH));
            $this->assertSame(['status' => 'open', 'verdict' => 'recommend_for_library', 'limit' => '25', 'cursor' => 'cur-1'], $query);
            $this->assertTrue(Signature::valid(self::SECRET, $request->header('X-InfoDot-Timestamp')[0], $request->header('X-InfoDot-Signature')[0], '', 300));
            $this->assertStringStartsWith('sha256=', $request->header('X-InfoDot-Signature')[0]);

            return true;
        });
    }

    public function test_it_returns_items_in_the_documented_shape_and_the_next_cursor(): void
    {
        $this->memory(Http::response(['items' => [$this->rec(), $this->rec('dec-2', 'infodot:solution:9')], 'next_cursor' => 'cur-2']));

        $page = $this->client()->recommendations();

        $this->assertSame('cur-2', $page['next_cursor']);
        $this->assertSame(0, $page['skipped']);
        $this->assertSame(['dec-1', 'dec-2'], array_column($page['items'], 'decision_event_id'));
        $this->assertSame([
            'decision_event_id' => 'dec-1', 'contribution_ref' => 'infodot:answer:5', 'confidence' => 0.82,
            'rationale' => [['rule' => 'independent_acceptance', 'effect' => '+0.40', 'detail' => 'Accepted by someone other than the author.']],
            'rule_version' => 'v1', 'decided_at' => '2026-10-26T10:00:00Z', 'status' => 'open',
        ], $page['items'][0]);
    }

    public function test_items_that_break_the_contract_are_left_out_not_repaired(): void
    {
        $this->memory(Http::response(['items' => [
            $this->rec('ok'),
            $this->rec('q', 'infodot:question:4'),
            $this->rec('bad-ref', 'not a ref'),
            $this->rec('wrong-verdict', 'infodot:answer:6', ['verdict' => 'do_not_recommend']),
            $this->rec('', 'infodot:answer:7'),
            'not even an object',
            ['contribution_ref' => 'infodot:answer:8'],
        ], 'next_cursor' => null]));

        $page = $this->client()->recommendations();

        $this->assertSame(['ok'], array_column($page['items'], 'decision_event_id'));
        $this->assertSame(6, $page['skipped']);
    }

    public function test_every_failure_to_get_a_trustworthy_answer_is_an_unavailable_exception_with_the_reason(): void
    {
        $cases = [
            'unreachable' => [fn () => throw new ConnectionException('timeout'), RecommendationsUnavailableException::UNREACHABLE],
            'server error' => [fn () => Http::response('boom', 500), RecommendationsUnavailableException::UNREACHABLE],
            'throttled' => [fn () => Http::response(['code' => 'rate_limited', 'message' => 'x'], 429), RecommendationsUnavailableException::UNREACHABLE],
            'bad signature' => [fn () => Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401), RecommendationsUnavailableException::REJECTED],
            'forbidden' => [fn () => Http::response(['code' => 'nope', 'message' => 'x'], 403), RecommendationsUnavailableException::REJECTED],
            'not json' => [fn () => Http::response('<html>proxy</html>', 200), RecommendationsUnavailableException::MALFORMED],
            'no items' => [fn () => Http::response(['data' => []], 200), RecommendationsUnavailableException::MALFORMED],
            'items not a list' => [fn () => Http::response(['items' => ['a' => 1], 'next_cursor' => null], 200), RecommendationsUnavailableException::MALFORMED],
            'cursor not a string' => [fn () => Http::response(['items' => [], 'next_cursor' => 5], 200), RecommendationsUnavailableException::MALFORMED],
        ];

        foreach ($cases as $name => [$respond, $reason]) {
            $this->memory($respond);

            try {
                $this->client()->recommendations();
                $this->fail("$name: no exception");
            } catch (RecommendationsUnavailableException $e) {
                $this->assertSame($reason, $e->reason, $name);
                $this->assertStringNotContainsString(self::SECRET, $e->getMessage());
                $this->assertStringNotContainsString('memory.test', $e->getMessage());
            }
        }
    }

    public function test_without_a_url_or_secret_nothing_is_sent(): void
    {
        Http::fake();

        foreach (['knowledge.infodot_secret', 'knowledge.memory_url'] as $key) {
            config([$key => null]);

            try {
                $this->client()->recommendations();
                $this->fail('a request was attempted');
            } catch (RecommendationsUnavailableException $e) {
                $this->assertSame(RecommendationsUnavailableException::NOT_CONFIGURED, $e->reason);
            }

            config(['knowledge.infodot_secret' => self::SECRET, 'knowledge.memory_url' => 'https://memory.test']);
        }

        Http::assertNothingSent();
        $this->assertTrue($this->client()->enabled());
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Reporting outcomes
    // ---------------------------------------------------------------------------------------------------------------

    public function test_an_outcome_is_a_signed_post_of_exactly_the_documented_body(): void
    {
        $this->memory(Http::response(['ok' => true], 200));

        $this->client()->reportOutcome('dec/1 é', 'adopted', '9d1c0b52-0a8c-4a5e-9f43-2b3c1d9e7a10');

        Http::assertSent(function (Request $request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://memory.test/api/knowledge-recommendations/dec%2F1%20%C3%A9/outcome', $request->url());
            $this->assertSame('{"outcome":"adopted","actor_ref":"9d1c0b52-0a8c-4a5e-9f43-2b3c1d9e7a10"}', $request->body());
            $this->assertTrue(Signature::valid(self::SECRET, $request->header('X-InfoDot-Timestamp')[0], $request->header('X-InfoDot-Signature')[0], $request->body(), 300));

            return true;
        });
    }

    public function test_memory_refusing_an_outcome_for_good_is_told_apart_from_an_outage(): void
    {
        $this->memory(Http::response(['code' => 'decision_not_found', 'message' => 'No such decision.'], 404));
        try {
            $this->client()->reportOutcome('dec-x', 'declined', 'uuid');
            $this->fail('404 was accepted');
        } catch (OutcomeRefusedException $e) {
            $this->assertSame('decision_not_found', $e->reason);
        }

        $this->memory(Http::response(['code' => 'invalid_request', 'message' => 'x'], 422));
        $this->expectException(OutcomeRefusedException::class);
        $this->client()->reportOutcome('dec-x', 'declined', 'uuid');
    }

    public function test_an_outcome_answered_by_a_proxy_page_or_an_outage_is_not_a_delivery(): void
    {
        foreach ([Http::response('<html>ok</html>', 200), Http::response('', 502), Http::response('', 401)] as $response) {
            $this->memory($response);

            try {
                $this->client()->reportOutcome('dec-1', 'adopted', 'uuid');
                $this->fail('a non-delivery was taken for a delivery');
            } catch (RecommendationsUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The outbox: a Memory outage never loses a decision
    // ---------------------------------------------------------------------------------------------------------------

    public function test_the_outbox_delivers_a_due_outcome_once(): void
    {
        $this->memory(Http::response(['ok' => true], 200));
        $row = $this->queuedOutcome();

        $this->assertSame(1, app(LibraryOutcomeOutbox::class)->flush());
        $this->assertSame(0, app(LibraryOutcomeOutbox::class)->flush());

        $this->assertNotNull($row->fresh()->sent_at);
        Http::assertSentCount(1);
    }

    public function test_an_outage_leaves_the_outcome_queued_and_it_is_retried_with_growing_delays_until_delivered(): void
    {
        $row = $this->queuedOutcome();
        $outbox = app(LibraryOutcomeOutbox::class);

        $this->memory(Http::response('down', 503));
        $this->assertSame(0, $outbox->flush());
        $row->refresh();
        $this->assertNull($row->sent_at);
        $this->assertNull($row->failed_at);
        $this->assertSame(1, $row->attempts);
        $this->assertEquals(now()->addSeconds(30), $row->next_attempt_at);
        $this->assertSame(1, $outbox->waiting());

        // Not due yet: nothing is sent.
        $this->memory(Http::response(['ok' => true], 200));
        $this->assertSame(0, $outbox->flush());
        Http::assertNothingSent();

        // Still down after the first delay: the next wait is longer.
        Carbon::setTestNow(now()->addSeconds(31));
        $this->memory(fn () => throw new ConnectionException('timeout'));
        $this->assertSame(0, $outbox->flush());
        $row->refresh();
        $this->assertSame(2, $row->attempts);
        $this->assertEquals(now()->addSeconds(120), $row->next_attempt_at);

        // Memory is back.
        Carbon::setTestNow(now()->addSeconds(121));
        $this->memory(Http::response(['ok' => true], 200));
        $this->assertSame(1, $outbox->flush());
        $this->assertNotNull($row->fresh()->sent_at);
        $this->assertSame(0, $outbox->waiting());
    }

    public function test_a_decision_made_during_an_outage_is_reported_afterwards_by_the_scheduled_command(): void
    {
        $this->memory(fn () => throw new ConnectionException('down'));
        $asker = $this->person('Asker');
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $this->person('Answerer')), $asker);
        $mod = $this->moderator('Curator');

        app(LibraryService::class)->adopt($answer->fresh(), $mod, 'dec-outage');
        $this->artisan('library:flush-outcomes')->assertExitCode(0);
        $this->assertNull(LibraryOutcome::sole()->sent_at, 'adopted locally; Memory not told yet');

        Carbon::setTestNow(now()->addMinutes(2));
        $this->memory(Http::response(['ok' => true], 200));
        $this->artisan('library:flush-outcomes')->expectsOutputToContain('Delivered 1')->assertExitCode(0);

        $this->assertNotNull(LibraryOutcome::sole()->sent_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/knowledge-recommendations/dec-outage/outcome')
            && $r->data() === ['outcome' => 'adopted', 'actor_ref' => $mod->uuid]);
    }

    public function test_the_outbox_stops_early_when_memory_is_unreachable_instead_of_hammering_it(): void
    {
        foreach (range(1, 5) as $i) {
            $this->queuedOutcome('dec-'.$i);
        }
        $attempts = 0;
        $this->memory(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('down');
        });

        app(LibraryOutcomeOutbox::class)->flush();

        $this->assertSame(3, $attempts);
        $this->assertSame(5, LibraryOutcome::whereNull('sent_at')->whereNull('failed_at')->count(), 'none is lost');
    }

    public function test_an_outcome_memory_does_not_know_is_parked_never_retried(): void
    {
        $this->memory(Http::response(['code' => 'decision_not_found', 'message' => 'x'], 404));
        $row = $this->queuedOutcome();

        app(LibraryOutcomeOutbox::class)->flush();
        $row->refresh();

        $this->assertNotNull($row->failed_at);
        $this->assertSame('decision_not_found', $row->last_error);
        $this->assertSame(0, app(LibraryOutcomeOutbox::class)->waiting());

        Carbon::setTestNow(now()->addDay());
        app(LibraryOutcomeOutbox::class)->flush();
        Http::assertSentCount(1);
    }

    public function test_a_rejected_signature_is_logged_as_an_error_and_retried_slowly(): void
    {
        Log::spy();
        $this->memory(Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401));
        $row = $this->queuedOutcome();

        app(LibraryOutcomeOutbox::class)->flush();
        $row->refresh();

        Log::shouldHaveReceived('error')->once();
        $this->assertNull($row->failed_at);
        $this->assertEquals(now()->addSeconds(3600), $row->next_attempt_at);
    }

    public function test_it_gives_up_after_25_attempts_and_says_so(): void
    {
        $this->memory(Http::response('down', 500));
        $row = $this->queuedOutcome();
        $row->forceFill(['attempts' => 24])->save();

        app(LibraryOutcomeOutbox::class)->flush();
        $row->refresh();

        $this->assertNotNull($row->failed_at);
        $this->assertStringStartsWith('gave up', $row->last_error);
    }

    public function test_without_configuration_the_outbox_sends_nothing_and_keeps_the_row_waiting(): void
    {
        Http::fake();
        config(['knowledge.infodot_secret' => null]);
        $row = $this->queuedOutcome();

        $this->assertSame(0, app(LibraryOutcomeOutbox::class)->flush());

        Http::assertNothingSent();
        $this->assertNull($row->fresh()->sent_at);
        $this->assertNull($row->fresh()->failed_at);
        $this->assertSame(0, $row->fresh()->attempts);
    }
}
