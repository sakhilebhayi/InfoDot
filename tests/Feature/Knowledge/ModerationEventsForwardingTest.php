<?php

namespace Tests\Feature\Knowledge;

use App\Events\Knowledge\ContributionApproved;
use App\Events\Knowledge\SolutionVerified;
use App\Listeners\ForwardKnowledgeEventToPoints;
use App\Models\ModerationAction;
use App\Models\Questions;
use App\Models\Team;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Points\RewardOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** What InfoDot tells the points ledger about moderator decisions: verified, unverified, reported, approved. */
class ModerationEventsForwardingTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'shared-secret-for-tests']);
    }

    /** @return array<int, array<string, mixed>> the payloads, oldest first */
    private function outbox(): array
    {
        return DB::table('reward_outbox')->orderBy('id')->get()->map(fn ($r) => json_decode($r->payload, true))->all();
    }

    public function test_verifying_a_solution_sends_one_event_naming_the_author_and_the_moderator(): void
    {
        $author = $this->person();
        $mod = $this->moderator();
        $solution = $this->solution($author);

        app(ModerationService::class)->verify($solution, $mod, 'Followed every step on a real filing.');

        $events = $this->outbox();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('infodot.solution.verified', $event['type']);
        $this->assertSame($author->uuid, $event['user_uuid']);
        $this->assertSame($mod->uuid, $event['actor_uuid']);
        $this->assertNotSame($event['user_uuid'], $event['actor_uuid']);
        $this->assertSame('infodot:solution:'.$solution->id, $event['contribution_ref']);
        $this->assertSame('public', $event['visibility']);
        $this->assertSame(Team::find($author->current_team_id)->uuid, $event['tenant_uuid']);
        $this->assertSame(['kind' => 'solution'], $event['facts']);
        $this->assertSame(['event_id', 'type', 'occurred_at', 'user_uuid', 'contribution_ref', 'actor_uuid', 'tenant_uuid', 'visibility', 'facts'], array_keys($event));
        $this->assertStringNotContainsString('real filing', json_encode($event), 'the moderator\'s note never leaves InfoDot');
    }

    public function test_verifying_twice_or_by_the_author_sends_nothing_more(): void
    {
        $authorMod = $this->moderator('Author mod');
        $mine = $this->solution($authorMod);
        $theirs = $this->solution($this->person());
        $service = app(ModerationService::class);

        try {
            $service->verify($mine, $authorMod);
        } catch (HttpException) {
        }
        $this->assertCount(0, $this->outbox());

        $service->verify($theirs, $this->moderator('One'));
        $service->verify($theirs, $this->moderator('Two'));
        $this->assertCount(1, $this->outbox(), 'a second verification changes nothing and sends nothing');
    }

    public function test_a_company_solution_is_sent_with_company_visibility(): void
    {
        $solution = $this->solution($author = $this->person(), 'company');
        $mod = $this->moderator();
        $author->currentTeam->users()->attach($mod, ['role' => 'editor']);
        $mod->forceFill(['current_team_id' => $author->current_team_id])->save();

        app(ModerationService::class)->verify($solution, $mod->fresh());

        $this->assertSame('company', $this->outbox()[0]['visibility']);
    }

    public function test_the_event_id_follows_the_audit_row_so_a_replay_is_the_same_event_and_a_new_verification_is_a_new_one(): void
    {
        $solution = $this->solution($this->person());
        $service = app(ModerationService::class);
        $mod = $this->moderator();

        $service->verify($solution, $mod);
        $service->unverify($solution->fresh(), $mod, 'It does not work any more.');
        $service->verify($solution->fresh(), $mod);

        $events = $this->outbox();
        $this->assertSame(['infodot.solution.verified', 'infodot.contribution.reward_reversed', 'infodot.solution.verified'], array_column($events, 'type'));
        $this->assertCount(3, array_unique(array_column($events, 'event_id')), 'each action is its own event');

        // Replaying the first action (a listener run twice) yields the same id, and the outbox keeps one row.
        $first = ModerationAction::where('action', 'verified')->orderBy('id')->first();
        $replay = new SolutionVerified('infodot:solution:'.$solution->id, [
            'author_uuid' => $solution->user->uuid, 'verified_by_uuid' => $mod->uuid, 'tenant_team_id' => $solution->team_id,
            'visibility' => 'public', 'event_key' => 'moderation_action:'.$first->id,
        ]);
        app(ForwardKnowledgeEventToPoints::class)->handle($replay);
        $this->assertCount(3, $this->outbox());
    }

    public function test_removing_a_verification_asks_for_a_reversal_of_the_verification_reward_only(): void
    {
        $author = $this->person();
        $solution = $this->solution($author);
        $service = app(ModerationService::class);
        $service->verify($solution, $this->moderator('Checker'));
        $remover = $this->moderator('Remover');

        $service->unverify($solution->fresh(), $remover, 'It does not work any more.');

        $reversal = $this->outbox()[1];
        $this->assertSame('infodot.contribution.reward_reversed', $reversal['type']);
        $this->assertSame($author->uuid, $reversal['user_uuid']);
        $this->assertSame($remover->uuid, $reversal['actor_uuid']);
        $this->assertSame('infodot:solution:'.$solution->id, $reversal['contribution_ref']);
        $this->assertSame(['rule_key' => 'infodot.solution.verified', 'reason' => 'The verification was removed.'], $reversal['facts']);
    }

    public function test_reporting_sends_flagged_with_the_reason_the_author_and_no_reporter(): void
    {
        $author = $this->person();
        $reporter = $this->person('Secret Reporter');
        $answer = $this->answer($this->question($this->person()), $author);
        DB::table('reward_outbox')->delete();

        app(AnswerService::class)->report($answer, $reporter, 'spam', 'Looks like an advert');

        $events = $this->outbox();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('infodot.contribution.flagged', $event['type']);
        $this->assertSame($author->uuid, $event['user_uuid']);
        $this->assertSame('infodot:answer:'.$answer->id, $event['contribution_ref']);
        $this->assertSame(['reason' => 'spam'], $event['facts']);
        $this->assertArrayNotHasKey('actor_uuid', $event);
        $this->assertSame('public', $event['visibility']);
        $encoded = json_encode($event);
        $this->assertStringNotContainsString($reporter->uuid, $encoded);
        $this->assertStringNotContainsString('advert', $encoded, 'the reporter\'s free text stays in InfoDot');
    }

    public function test_reporting_a_question_and_a_solution_names_their_own_refs_and_a_repeat_report_sends_nothing(): void
    {
        $question = $this->question($this->person(), 'public');
        $solution = $this->solution($this->person());
        $reporter = $this->person();
        DB::table('reward_outbox')->delete();
        $service = app(AnswerService::class);

        $service->report($question, $reporter, 'harmful');
        $service->report($question, $reporter, 'harmful');
        $service->report($solution, $reporter, 'low_quality');

        $this->assertSame(
            ['infodot:question:'.$question->id, 'infodot:solution:'.$solution->id],
            array_column($this->outbox(), 'contribution_ref'),
        );
    }

    public function test_a_report_on_company_content_passes_the_company_visibility_through(): void
    {
        $author = $this->person();
        $question = $this->question($author, 'company');
        $reporter = $this->person();
        $author->currentTeam->users()->attach($reporter, ['role' => 'editor']);
        $reporter->forceFill(['current_team_id' => $author->current_team_id])->save();
        DB::table('reward_outbox')->delete();

        app(AnswerService::class)->report($question, $reporter->fresh(), 'spam');

        $this->assertSame('company', $this->outbox()[0]['visibility']);
    }

    public function test_approving_a_question_sends_one_event_naming_the_author_and_the_approver(): void
    {
        $author = $this->person();
        $mod = $this->moderator();
        $question = $this->question($author);
        DB::table('reward_outbox')->delete();

        $this->assertTrue(app(ModerationService::class)->approve($question, $mod));
        $this->assertFalse(app(ModerationService::class)->approve($question, $this->moderator('Another')));

        $events = $this->outbox();
        $this->assertCount(1, $events, 'approving twice sends one event');
        $event = $events[0];
        $this->assertSame('infodot.contribution.approved', $event['type']);
        $this->assertSame($author->uuid, $event['user_uuid']);
        $this->assertSame($mod->uuid, $event['actor_uuid']);
        $this->assertSame('infodot:question:'.$question->id, $event['contribution_ref']);
        $this->assertSame('public', $event['visibility']);
        $this->assertSame(Team::find($author->current_team_id)->uuid, $event['tenant_uuid']);
        $this->assertSame(['kind' => 'question'], $event['facts']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $event['event_id']);
    }

    public function test_no_event_is_raised_when_the_approval_is_refused(): void
    {
        Event::fake([ContributionApproved::class]);
        $mod = $this->moderator();

        try {
            app(ModerationService::class)->approve($this->question($mod), $mod);
        } catch (HttpException) {
        }

        Event::assertNotDispatched(ContributionApproved::class);
    }

    public function test_events_are_signed_the_same_way_and_delivered_once(): void
    {
        Http::fake(['dopemine.test/*' => Http::response(['status' => 'awarded'], 202)]);
        $mod = $this->moderator();
        app(ModerationService::class)->verify($this->solution($this->person()), $mod);

        $this->assertSame(1, app(RewardOutbox::class)->flush());
        $this->assertSame(0, app(RewardOutbox::class)->flush());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $timestamp = $request->header('X-InfoDot-Timestamp')[0];

            return hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'shared-secret-for-tests'), $request->header('X-InfoDot-Signature')[0])
                && $request['type'] === 'infodot.solution.verified';
        });
    }

    public function test_an_approval_waits_for_the_earlier_publication_of_the_same_question_and_is_never_dropped(): void
    {
        Http::fake(['dopemine.test/*' => Http::sequence()->push('down', 503)->push(['status' => 'published'], 202)->push(['status' => 'awarded'], 202)]);
        $asker = $this->person();
        $this->actingAs($asker)->post('/questions/add', ['question' => 'How do I price a handmade product?', 'description' => 'Materials, time and a margin, but how much?', 'publish' => '1'])->assertRedirect();
        $question = Questions::latest('id')->first();
        app(ModerationService::class)->approve($question, $this->moderator());
        $this->assertSame(['infodot.question.published', 'infodot.contribution.approved'], array_column($this->outbox(), 'type'));

        // The publication fails (503): the approval, which follows it, is held back and not sent ahead of it.
        $this->assertSame(0, app(RewardOutbox::class)->flush());
        Http::assertSentCount(1);
        $this->assertNull(DB::table('reward_outbox')->where('type', 'infodot.contribution.approved')->value('sent_at'));

        $this->travel(5)->minutes();
        $this->assertSame(2, app(RewardOutbox::class)->flush(), 'publication first, then the approval, in the same run');
        $this->assertCount(0, DB::table('reward_outbox')->whereNull('sent_at')->get());
    }

    public function test_a_too_early_answer_from_the_ledger_is_retried_not_parked(): void
    {
        Http::fake(['dopemine.test/*' => Http::sequence()->push(['message' => 'unknown'], 425)->push(['message' => 'unknown'], 409)->push(['status' => 'awarded'], 202)]);
        $mod = $this->moderator();
        $service = app(ModerationService::class);
        $service->approve($this->question($this->person()), $mod);
        DB::table('reward_outbox')->where('type', 'infodot.question.published')->delete();
        $service->verify($this->solution($this->person()), $mod);

        app(RewardOutbox::class)->flush();

        $this->assertSame(0, DB::table('reward_outbox')->whereNotNull('failed_at')->count());
        $this->assertSame(2, DB::table('reward_outbox')->where('attempts', 1)->count());
    }
}
