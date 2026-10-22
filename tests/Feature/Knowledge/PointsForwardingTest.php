<?php

namespace Tests\Feature\Knowledge;

use App\Models\Questions;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Points\PointsClient;
use App\Services\Points\RewardOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PointsForwardingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'shared-secret-for-tests';

    private const GOOD = 'First register the business with CIPC, then open a bank account, then ask your supplier for terms in writing.';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => self::SECRET]);
    }

    private function person(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function question(User $asker, string $visibility = 'public'): Questions
    {
        return Questions::create(['user_id' => $asker->id, 'team_id' => $asker->current_team_id, 'visibility' => $visibility, 'question' => 'How do I register a clothing brand?', 'description' => 'Steps please.']);
    }

    /** @return array<int, array<string, mixed>> */
    private function outbox(): array
    {
        return DB::table('reward_outbox')->orderBy('id')->get()->map(fn ($r) => ['row' => $r] + json_decode($r->payload, true))->all();
    }

    public function test_events_wait_in_the_outbox_even_when_the_ledger_is_not_configured(): void
    {
        config(['services.dopemine.secret' => null]);
        Http::fake();
        $asker = $this->person();

        app(AnswerService::class)->publish($this->question($asker), $this->person(), self::GOOD);

        $this->assertCount(1, $this->outbox());
        $this->assertSame(0, app(RewardOutbox::class)->flush());
        Http::assertNothingSent();
    }

    public function test_publishing_an_answer_queues_one_event_with_ids_and_no_content(): void
    {
        Http::fake();
        $asker = $this->person();
        $answerer = $this->person();

        $answer = app(AnswerService::class)->publish($this->question($asker), $answerer, self::GOOD);

        [$event] = $this->outbox();
        $this->assertSame('infodot.answer.published', $event['type']);
        $this->assertSame($answerer->uuid, $event['user_uuid']);
        $this->assertSame('infodot:answer:'.$answer->id, $event['contribution_ref']);
        $this->assertSame(false, $event['facts']['self_answer']);
        $this->assertSame('infodot:question:'.$answer->question_id, $event['facts']['question_ref']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $event['event_id']);
        $this->assertStringNotContainsString('CIPC', json_encode($event), 'the answer text never leaves InfoDot');
        $this->assertStringNotContainsString('@', json_encode($event), 'no email addresses either');
    }

    public function test_acceptance_names_the_author_and_the_acceptor_and_a_revoked_acceptance_asks_for_a_reversal(): void
    {
        Http::fake();
        $asker = $this->person();
        $answerer = $this->person();
        $service = app(AnswerService::class);
        $answer = $service->publish($this->question($asker), $answerer, self::GOOD);

        $service->accept($answer, $asker);
        $service->revokeAcceptance($answer->fresh(), $asker);

        $events = array_column($this->outbox(), null, 'type');
        $this->assertSame($answerer->uuid, $events['infodot.answer.accepted']['user_uuid']);
        $this->assertSame($asker->uuid, $events['infodot.answer.accepted']['actor_uuid']);
        $this->assertSame($answerer->uuid, $events['infodot.contribution.reward_reversed']['user_uuid']);
        $this->assertSame('infodot.answer.accepted', $events['infodot.contribution.reward_reversed']['facts']['rule_key']);
    }

    public function test_a_question_is_reported_as_not_yet_approved_and_carries_the_company_uuid_not_the_id(): void
    {
        Http::fake();
        $asker = $this->person();

        $this->actingAs($asker)->post('/questions/add', ['topics' => [Topic::query()->value('id')], 'question' => 'How do I price a handmade product?', 'description' => 'Materials, time and a margin, but how much?'])->assertRedirect();

        [$event] = $this->outbox();
        $this->assertSame('infodot.question.published', $event['type']);
        $this->assertFalse($event['facts']['approved']);
        $this->assertSame(Team::find($asker->current_team_id)->uuid, $event['tenant_uuid']);
    }

    public function test_flush_signs_each_request_the_way_the_ledger_checks_it_and_marks_it_sent(): void
    {
        Http::fake(['dopemine.test/*' => Http::response(['status' => 'awarded'], 202)]);
        $asker = $this->person();
        app(AnswerService::class)->publish($this->question($asker), $this->person(), self::GOOD);

        $this->assertSame(1, app(RewardOutbox::class)->flush());

        Http::assertSent(function (Request $request) {
            $timestamp = $request->header('X-InfoDot-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), self::SECRET);

            return $request->url() === 'https://dopemine.test/api/ingest/contributions'
                && hash_equals($expected, $request->header('X-InfoDot-Signature')[0])
                && abs(time() - (int) $timestamp) < 10;
        });
        $this->assertNotNull(DB::table('reward_outbox')->first()->sent_at);
        $this->assertSame(0, app(RewardOutbox::class)->flush(), 'a delivered event is not sent twice');
    }

    public function test_an_outage_is_retried_with_a_growing_delay_and_the_same_event_id(): void
    {
        Http::fake(['dopemine.test/*' => Http::sequence()->push('down', 503)->push(['status' => 'awarded'], 202)]);
        $asker = $this->person();
        app(AnswerService::class)->publish($this->question($asker), $this->person(), self::GOOD);
        $id = $this->outbox()[0]['event_id'];

        app(RewardOutbox::class)->flush();
        $row = DB::table('reward_outbox')->first();
        $this->assertSame(1, $row->attempts);
        $this->assertNull($row->sent_at);
        $this->assertTrue(Carbon::parse($row->next_attempt_at)->isFuture());

        $this->assertSame(0, app(RewardOutbox::class)->flush(), 'not due yet');

        $this->travel(5)->minutes();
        $this->assertSame(1, app(RewardOutbox::class)->flush());
        $this->assertSame($id, json_decode(DB::table('reward_outbox')->first()->payload, true)['event_id'], 'the retry carries the same event id, so the ledger can drop a duplicate');
    }

    public function test_an_unreachable_ledger_is_retried_and_stops_the_run_early(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        $asker = $this->person();
        $question = $this->question($asker);
        foreach (range(1, 5) as $n) {
            DB::table('reward_outbox')->insert(['event_id' => (string) Str::uuid(), 'type' => 'infodot.answer.published', 'payload' => json_encode(['event_id' => 'x'.$n, 'type' => 'infodot.answer.published']), 'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->assertSame(0, app(RewardOutbox::class)->flush());

        $this->assertSame(3, DB::table('reward_outbox')->where('attempts', 1)->count(), 'gave up for this minute after three failures in a row');
        $this->assertSame(2, DB::table('reward_outbox')->where('attempts', 0)->count());
    }

    public function test_a_refused_event_is_parked_and_never_retried_and_a_bad_secret_keeps_retrying_slowly(): void
    {
        Http::fake(['dopemine.test/*' => Http::sequence()->push(['message' => 'bad'], 422)->push('no', 401)]);
        $asker = $this->person();
        $answerer = $this->person();
        $service = app(AnswerService::class);
        $question = $this->question($asker);
        $service->publish($question, $answerer, self::GOOD);
        $service->publish($question, $answerer, self::GOOD.' And keep every receipt for your accountant.');

        app(RewardOutbox::class)->flush();

        $rows = DB::table('reward_outbox')->orderBy('id')->get();
        $this->assertNotNull($rows[0]->failed_at, '422: a bad event is parked');
        $this->assertNull($rows[1]->failed_at, '401: the secret is wrong, keep trying');
        $this->assertTrue(Carbon::parse($rows[1]->next_attempt_at)->gte(now()->addMinutes(59)), 'but not every minute');
    }

    public function test_the_profile_summary_is_cached_and_fails_soft(): void
    {
        $uuid = (string) Str::uuid();
        Http::fake(['dopemine.test/api/points/*' => Http::response(['balance' => 12, 'history' => []], 200)]);

        $this->assertSame(12, app(PointsClient::class)->summary($uuid)['balance']);
        $this->assertSame(12, app(PointsClient::class)->summary($uuid)['balance']);
        Http::assertSentCount(1);

        app(PointsClient::class)->forget($uuid);
        Http::fake(fn () => throw new ConnectionException('refused'));
        $this->assertNull(app(PointsClient::class)->summary($uuid));
    }
}
