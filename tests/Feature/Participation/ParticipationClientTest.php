<?php

namespace Tests\Feature\Participation;

use App\Models\User;
use App\Services\Participation\ParticipationClient;
use App\Services\Participation\ParticipationRefusedException;
use App\Services\Participation\ParticipationUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParticipationClientTest extends TestCase
{
    use FakesMemory;
    use RefreshDatabase;

    private function client(): ParticipationClient
    {
        $this->app->forgetInstance(ParticipationClient::class);

        return app(ParticipationClient::class);
    }

    /** @return array<string, string> */
    private function event(User $user, array $override = []): array
    {
        return $override + [
            'event_id' => '0b1f2a3c-9d4e-5f60-8a71-5b2c3d4e5f60', 'user_uuid' => $user->uuid, 'occurred_at' => '2026-10-22T09:00:00Z',
            'topic' => 'integrations', 'kind' => 'accepted', 'contribution_ref' => 'infodot:answer:77',
        ];
    }

    private function assertSigned(Request $request): void
    {
        $timestamp = $request->header('X-InfoDot-Timestamp')[0] ?? '';
        $this->assertMatchesRegularExpression('/\A\d{10}\z/', $timestamp);
        $this->assertEqualsWithDelta(time(), (int) $timestamp, 5);
        $this->assertSame('sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), self::MEMORY_SECRET), $request->header('X-InfoDot-Signature')[0] ?? '');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMemory();
    }

    // ---------------------------------------------------------------- signing, per call

    public function test_every_call_is_signed_over_the_exact_body_it_sends_and_a_real_check_accepts_it(): void
    {
        $user = User::factory()->create();
        $client = $this->client();

        $client->setConsent($user, true);
        $client->sendEvent($this->event($user));
        $context = $client->context($user);
        $client->erase($user);

        [$put] = $this->memorySent('PUT');
        [$post] = $this->memorySent('POST');
        [$get] = $this->memorySent('GET');
        [$delete] = $this->memorySent('DELETE');
        foreach ([$put, $post, $get, $delete] as $request) {
            $this->assertSigned($request);
            $this->assertSame('https', parse_url($request->url(), PHP_URL_SCHEME));
        }
        $this->assertSame('{"granted":true}', $put->body());
        $this->assertSame('/api/participation/'.$user->uuid.'/consent', parse_url($put->url(), PHP_URL_PATH));
        $this->assertSame('/api/participation/events', parse_url($post->url(), PHP_URL_PATH));
        $this->assertSame('', $get->body());
        $this->assertSame('/api/participation/'.$user->uuid.'/context', parse_url($get->url(), PHP_URL_PATH));
        $this->assertSame('', $delete->body());
        $this->assertSame('/api/participation/'.$user->uuid, parse_url($delete->url(), PHP_URL_PATH));
        $this->assertTrue($context->granted);
        // The fake answered 401 to anything wrongly signed: none of these did, so none was refused.
    }

    public function test_a_wrong_secret_is_rejected_and_reported_as_unavailable_without_leaking_it(): void
    {
        Log::spy();
        config(['participation.secret' => 'a-different-secret-entirely']);
        $user = User::factory()->create();

        try {
            $this->client()->setConsent($user, true);
            $this->fail('Unavailable was expected.');
        } catch (ParticipationUnavailableException $e) {
            $this->assertSame(ParticipationUnavailableException::REJECTED, $e->reason);
        }

        $logged = [];
        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use (&$logged) {
            $logged[] = json_encode($args);

            return true;
        });
        $all = implode(' ', $logged);
        $this->assertStringContainsString('invalid_signature', $all);
        $this->assertStringNotContainsString('a-different-secret-entirely', $all);
        $this->assertStringNotContainsString(self::MEMORY_SECRET, $all);
        $this->assertStringNotContainsString($user->uuid, $all);
        $this->assertStringNotContainsString('memory.test', $all);
    }

    // ---------------------------------------------------------------- what is sent

    public function test_an_event_carries_exactly_the_six_documented_fields_and_nothing_else(): void
    {
        $user = User::factory()->create(['name' => 'Thandi Nkosi', 'email' => 'thandi@example.org']);
        $this->client()->setConsent($user, true);

        // Extra keys on the way in (a title, an email) never reach the wire.
        $this->client()->sendEvent($this->event($user, ['title' => 'How do I register?', 'email' => $user->email, 'name' => $user->name]));

        [$post] = $this->memorySent('POST');
        $this->assertSame(['event_id', 'user_uuid', 'occurred_at', 'topic', 'kind', 'contribution_ref'], array_keys(json_decode($post->body(), true)));
        $this->assertStringNotContainsString('thandi', strtolower($post->body()));
        $this->assertStringNotContainsString('register', $post->body());
    }

    public function test_consent_must_be_confirmed_by_memory_before_it_counts_as_recorded(): void
    {
        $user = User::factory()->create();
        // A 200 that says the OPPOSITE of what we asked is not a recording.
        $this->memoryOverride['PUT /api/participation/{uuid}/consent'] = Http::response(['data' => ['consent' => ['granted' => false]]]);

        try {
            $this->client()->setConsent($user, true);
            $this->fail('Unavailable was expected.');
        } catch (ParticipationUnavailableException $e) {
            $this->assertSame(ParticipationUnavailableException::MALFORMED, $e->reason);
        }

        $this->memoryOverride['PUT /api/participation/{uuid}/consent'] = Http::response(['ok' => true]);
        $this->expectException(ParticipationUnavailableException::class);
        $this->client()->setConsent($user, true);
    }

    // ---------------------------------------------------------------- answers

    public function test_an_event_without_consent_is_a_refusal_and_a_replay_is_not_an_error(): void
    {
        $user = User::factory()->create();

        try {
            $this->client()->sendEvent($this->event($user));
            $this->fail('Refusal expected.');
        } catch (ParticipationRefusedException $e) {
            $this->assertSame(ParticipationRefusedException::CONSENT_REQUIRED, $e->reason);
        }

        $this->client()->setConsent($user, true);
        $this->client()->sendEvent($this->event($user));
        $this->client()->sendEvent($this->event($user)); // 200 replay
        $this->assertCount(1, $this->memoryEvents);
    }

    public function test_a_conflict_and_an_invalid_event_are_refusals(): void
    {
        $user = User::factory()->create();
        $this->memoryOverride['POST /api/participation/events'] = Http::response(['code' => 'event_conflict', 'message' => 'x'], 409);
        try {
            $this->client()->sendEvent($this->event($user));
            $this->fail('Refusal expected.');
        } catch (ParticipationRefusedException $e) {
            $this->assertSame(ParticipationRefusedException::CONFLICT, $e->reason);
        }

        $this->memoryOverride['POST /api/participation/events'] = Http::response(['code' => 'invalid_request', 'message' => 'x'], 422);
        try {
            $this->client()->sendEvent($this->event($user));
            $this->fail('Refusal expected.');
        } catch (ParticipationRefusedException $e) {
            $this->assertSame(ParticipationRefusedException::INVALID, $e->reason);
        }
    }

    public function test_the_context_is_topics_and_counts_only_and_odd_rows_are_left_out(): void
    {
        $user = User::factory()->create();
        $this->memoryOverride['GET /api/participation/{uuid}/context'] = Http::response(['data' => [
            'user_uuid' => $user->uuid, 'consent' => ['granted' => true],
            'topics' => [
                ['topic' => 'integrations', 'count' => 3, 'kinds' => ['accepted' => 2, 'verified' => 1, 'secret' => 9]],
                ['topic' => 'Not A Slug!', 'count' => 1, 'kinds' => []],
                ['topic' => 'billing-and-payments', 'count' => '2', 'kinds' => []],
                'junk',
            ],
        ]]);

        $context = $this->client()->context($user);

        $this->assertTrue($context->granted);
        $this->assertSame([['topic' => 'integrations', 'count' => 3, 'kinds' => ['accepted' => 2, 'verified' => 1]]], $context->topics);
    }

    public function test_the_context_without_consent_is_a_refusal(): void
    {
        $this->expectException(ParticipationRefusedException::class);
        $this->client()->context(User::factory()->create());
    }

    // ---------------------------------------------------------------- outages and fail-closed

    public static function outages(): array
    {
        return [
            'server error' => [fn () => Http::response(['code' => 'boom', 'message' => 'SECRET-LEAK-CANARY'], 503), ParticipationUnavailableException::UNREACHABLE],
            'rate limited' => [fn () => Http::response(['message' => 'SECRET-LEAK-CANARY'], 429), ParticipationUnavailableException::UNREACHABLE],
            'connection error' => [fn () => fn () => throw new ConnectionException('SECRET-LEAK-CANARY'), ParticipationUnavailableException::UNREACHABLE],
            'bad signature' => [fn () => Http::response(['code' => 'invalid_signature', 'message' => 'SECRET-LEAK-CANARY'], 401), ParticipationUnavailableException::REJECTED],
            'not json' => [fn () => Http::response('SECRET-LEAK-CANARY', 200), ParticipationUnavailableException::MALFORMED],
        ];
    }

    #[DataProvider('outages')]
    public function test_outages_are_unavailable_and_never_leak_the_upstream_body_or_the_secret(callable $answer, string $reason): void
    {
        Log::spy();
        $user = User::factory()->create();
        foreach (['PUT /api/participation/{uuid}/consent', 'POST /api/participation/events', 'GET /api/participation/{uuid}/context', 'DELETE /api/participation/{uuid}'] as $key) {
            $this->memoryOverride[$key] = $answer();
        }

        foreach ([
            fn () => $this->client()->setConsent($user, true),
            fn () => $this->client()->sendEvent($this->event($user)),
            fn () => $this->client()->context($user),
            fn () => $this->client()->erase($user),
        ] as $call) {
            try {
                $call();
                $this->fail('Unavailable was expected.');
            } catch (ParticipationUnavailableException $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertStringNotContainsString('CANARY', $e->getMessage());
            }
        }

        $logged = [];
        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use (&$logged) {
            $logged[] = json_encode($args);

            return true;
        });
        $all = implode(' ', $logged);
        $this->assertStringNotContainsString('CANARY', $all);
        $this->assertStringNotContainsString(self::MEMORY_SECRET, $all);
        $this->assertStringNotContainsString('sha256=', $all);
        $this->assertStringNotContainsString($user->uuid, $all);
    }

    public static function unconfigured(): array
    {
        return [
            'switched off' => [['participation.enabled' => false]],
            'no secret' => [['participation.secret' => null]],
            'empty secret' => [['participation.secret' => '']],
            'no url' => [['participation.url' => '']],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('unconfigured')]
    public function test_unconfigured_fails_closed_and_sends_nothing(array $config): void
    {
        config($config);
        $user = User::factory()->create();
        $client = $this->client();

        $this->assertFalse($client->enabled());
        foreach ([
            fn () => $client->setConsent($user, true),
            fn () => $client->sendEvent($this->event($user)),
            fn () => $client->context($user),
            fn () => $client->erase($user),
        ] as $call) {
            try {
                $call();
                $this->fail('Unavailable was expected.');
            } catch (ParticipationUnavailableException $e) {
                $this->assertSame(ParticipationUnavailableException::NOT_CONFIGURED, $e->reason);
            }
        }

        $this->assertSame([], $this->memoryRequests);
    }

    public function test_after_one_unreachable_call_the_rest_fails_fast(): void
    {
        $user = User::factory()->create();
        $this->memoryOverride['PUT /api/participation/{uuid}/consent'] = fn () => throw new ConnectionException('down');
        $client = $this->client();

        try {
            $client->setConsent($user, true);
        } catch (ParticipationUnavailableException) {
        }
        $before = count($this->memoryRequests);
        try {
            $client->context($user);
            $this->fail('Unavailable was expected.');
        } catch (ParticipationUnavailableException $e) {
            $this->assertSame(ParticipationUnavailableException::UNREACHABLE, $e->reason);
        }

        $this->assertCount($before, $this->memoryRequests);
    }
}
