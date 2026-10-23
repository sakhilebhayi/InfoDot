<?php

namespace Tests\Feature\Participation;

use App\Actions\Jetstream\DeleteUser;
use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\SolutionUnverified;
use App\Events\Knowledge\SolutionVerified;
use App\Listeners\ForwardKnowledgeEventToMemory;
use App\Models\Answer;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Participation\EligibleContributions;
use App\Services\Participation\ExpertiseSharing;
use App\Services\Participation\ParticipationOutbox;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/**
 * Participation events: only for people who opted in, one per topic, deterministic ids, never retracted one by one, and
 * delivered from a durable outbox that checks the switch again just before every send.
 */
class ParticipationEventsTest extends TestCase
{
    use FakesMemory;
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.dopemine.secret' => null]);
        $this->fakeMemory();
    }

    private function sharing(?User $user = null): User
    {
        $user ??= $this->person('Helper');
        $this->assertSame('ok', app(ExpertiseSharing::class)->enable($user)['level']);

        return $user->fresh();
    }

    private function accept(User $helper, string ...$slugs): Answer
    {
        $asker = $this->person('Asker');
        $question = $this->file($this->question($asker, 'public', 'How do I connect my bank feed?'), ...$slugs);

        return app(AnswerService::class)->accept($this->answer($question, $helper), $asker);
    }

    /** @return list<object> */
    private function rows(): array
    {
        return DB::table('participation_outbox')->orderBy('id')->get()->all();
    }

    private function flush(): int
    {
        return app(ParticipationOutbox::class)->flush();
    }

    // ---------------------------------------------------------------- emission

    public function test_nothing_is_queued_and_memory_is_never_called_for_someone_who_did_not_opt_in(): void
    {
        Http::preventStrayRequests();
        $helper = $this->person('Helper');
        $moderator = $this->moderator();
        $this->accept($helper, 'integrations');
        app(ModerationService::class)->verify($this->file($this->solution($helper), 'integrations'), $moderator, 'Followed every step.');

        $this->assertSame([], $this->rows());
        $this->assertSame([], $this->memoryRequests);
    }

    public function test_an_accepted_answer_queues_one_event_per_topic_with_a_deterministic_id(): void
    {
        $helper = $this->sharing();

        $answer = $this->accept($helper, 'integrations', 'billing-and-payments');

        $rows = $this->rows();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['integrations', 'billing-and-payments'], array_column($rows, 'topic'));
        foreach ($rows as $row) {
            $event = json_decode($row->payload, true);
            $this->assertSame(['event_id', 'user_uuid', 'occurred_at', 'topic', 'kind', 'contribution_ref'], array_keys($event));
            $this->assertSame('accepted', $event['kind']);
            $this->assertSame($helper->uuid, $event['user_uuid']);
            $this->assertSame('infodot:answer:'.$answer->id, $event['contribution_ref']);
            $this->assertSame(Uuid::uuid5(Uuid::NAMESPACE_URL, 'infodot:participation:infodot:answer:'.$answer->id.':'.$event['topic'].':accepted')->toString(), $event['event_id']);
            $this->assertSame($event['event_id'], $row->event_id);
            $this->assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $event['occurred_at']);
        }
    }

    public function test_a_verified_solution_queues_a_verified_event_per_topic(): void
    {
        $helper = $this->sharing();
        $solution = $this->file($this->solution($helper), 'data-and-reports', 'integrations');

        app(ModerationService::class)->verify($solution, $this->moderator(), 'Followed every step.');

        $this->assertEqualsCanonicalizing(
            [['infodot:solution:'.$solution->id, 'data-and-reports', 'verified'], ['infodot:solution:'.$solution->id, 'integrations', 'verified']],
            array_map(fn ($r) => [$r->contribution_ref, $r->topic, $r->kind], $this->rows()),
        );
    }

    public function test_nothing_is_queued_for_content_that_is_private_or_has_no_topic(): void
    {
        $helper = $this->sharing();
        $asker = $this->person('Asker');

        // A private (company) question, a public question without a topic, and a company solution.
        $private = $this->file($this->question($asker, 'public', 'Our internal process'), 'integrations');
        $privateAnswer = $this->answer($private, $helper);
        $private->forceFill(['visibility' => 'company'])->save(); // it became company-only before the asker accepted
        app(AnswerService::class)->accept($privateAnswer, $asker);
        $untagged = $this->question($asker, 'public', 'No topic');
        app(AnswerService::class)->accept($this->answer($untagged, $helper), $asker);
        $solution = $this->file($this->solution($helper, 'company'), 'integrations');
        $solution->forceFill(['verified_at' => now(), 'verified_by' => $this->moderator()->id])->save();
        app(ForwardKnowledgeEventToMemory::class)->handle(new SolutionVerified('infodot:solution:'.$solution->id, ['author_uuid' => $helper->uuid]));

        $this->assertSame([], $this->rows());
    }

    public function test_accepting_the_same_answer_again_after_a_revoke_is_not_a_second_event(): void
    {
        $helper = $this->sharing();
        $answer = $this->accept($helper, 'integrations');
        $this->flush();
        $asker = $answer->question->user;

        app(AnswerService::class)->revokeAcceptance($answer->fresh(), $asker);
        app(AnswerService::class)->accept($answer->fresh(), $asker);
        $this->flush();

        $this->assertCount(1, $this->rows());
        $this->assertCount(1, $this->memoryEvents);
    }

    public function test_a_revoke_or_a_hide_retracts_nothing_and_says_nothing_to_memory(): void
    {
        $helper = $this->sharing();
        $answer = $this->accept($helper, 'integrations');
        $this->flush();
        $before = count($this->memoryRequests);

        app(ModerationService::class)->hide($answer->fresh(), $this->moderator(), 'This answer is wrong and misleading.');
        $this->flush();

        $this->assertCount($before, $this->memoryRequests, 'no request at all: Memory has no per-event delete and none is invented');
        $this->assertCount(1, $this->memoryEvents);
    }

    public function test_a_failure_while_queueing_never_breaks_the_acceptance(): void
    {
        $helper = $this->sharing();
        $this->mock(ParticipationOutbox::class, fn ($m) => $m->shouldReceive('enqueue')->andThrow(new \RuntimeException('boom '.$helper->email)));
        Log::spy();

        $answer = $this->accept($helper, 'integrations');

        $this->assertTrue($answer->fresh()->isAccepted());
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => $message === 'participation.enqueue_failed' && ! str_contains(json_encode($context), $helper->email));
    }

    // ---------------------------------------------------------------- the one definition of "eligible"

    public function test_eligible_contributions_are_only_public_visible_other_person_accepted_or_verified_with_topics(): void
    {
        $helper = $this->person('Helper');
        $asker = $this->person('Asker');
        $moderator = $this->moderator();

        $good = $this->accept($helper, 'integrations');                                   // eligible
        // Accepted by the asker, but hidden afterwards: acceptance is revoked and the answer is hidden.
        $hidden = $this->accept($helper, 'billing-and-payments');
        app(ModerationService::class)->hide($hidden->fresh(), $moderator, 'This answer is wrong and misleading.');
        // Self-answer to own question accepted by self: not another person's judgement.
        $own = $this->file($this->question($helper, 'public', 'My own question'), 'integrations');
        $selfAnswer = $this->answer($own, $helper);
        $selfAnswer->forceFill(['accepted_at' => now(), 'accepted_by' => $helper->id])->save();
        // Published but accepted_by is the author (data oddity): excluded.
        $odd = $this->answer($this->file($this->question($asker, 'public', 'Odd'), 'integrations'), $helper);
        $odd->forceFill(['accepted_at' => now(), 'accepted_by' => $helper->id])->save();
        // Not accepted at all.
        $this->answer($this->file($this->question($asker, 'public', 'Open'), 'integrations'), $helper);
        // Someone else's accepted answer.
        $this->accept($this->person('Someone else'), 'integrations');
        // A verified public solution, an unverified one, a hidden verified one, and a company one.
        $verified = $this->file($this->solution($helper), 'getting-started');
        app(ModerationService::class)->verify($verified, $moderator, 'Followed every step.');
        $this->file($this->solution($helper, 'public', 'Not yet verified'), 'getting-started');
        $hiddenSolution = $this->file($this->solution($helper, 'public', 'Hidden later'), 'getting-started');
        app(ModerationService::class)->verify($hiddenSolution, $moderator, 'Followed every step.');
        app(ModerationService::class)->hide($hiddenSolution->fresh(), $moderator, 'This is misleading and has been hidden.');

        $events = EligibleContributions::forUser($helper);

        $this->assertEqualsCanonicalizing(
            [['infodot:answer:'.$good->id, 'integrations', 'accepted'], ['infodot:solution:'.$verified->id, 'getting-started', 'verified']],
            array_map(fn ($e) => [$e['contribution_ref'], $e['topic'], $e['kind']], $events),
        );
    }

    public function test_contributions_older_than_the_retention_window_are_not_sent(): void
    {
        $helper = $this->person('Helper');
        $answer = $this->accept($helper, 'integrations');
        Answer::withoutGlobalScopes()->whereKey($answer->id)->update(['accepted_at' => Carbon::now()->subDays(400)]);

        $this->assertSame([], EligibleContributions::forUser($helper));

        Answer::withoutGlobalScopes()->whereKey($answer->id)->update(['accepted_at' => Carbon::now()->subDays(100)]);
        $this->assertCount(1, EligibleContributions::forUser($helper));
    }

    // ---------------------------------------------------------------- the outbox

    public function test_delivery_sends_each_event_once_and_memory_counts_them(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations', 'billing-and-payments');

        $this->assertSame(2, $this->flush());
        $this->assertSame(0, $this->flush());

        $this->assertCount(2, $this->memoryEvents);
        $this->assertCount(2, array_filter($this->rows(), fn ($r) => $r->sent_at !== null));
        $this->assertCount(2, $this->memorySent('POST'));
    }

    public function test_a_person_who_turned_it_off_since_the_event_was_queued_is_never_sent_anything(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');
        User::whereKey($helper->id)->update(['share_expertise' => false]); // switched off by another request, queue not yet cleared
        $this->memoryRequests = [];

        $this->assertSame(0, $this->flush());

        $this->assertSame([], $this->memoryRequests);
        $this->assertSame([], $this->rows());
    }

    public function test_a_memory_outage_keeps_the_event_and_retries_it_later_with_a_growing_delay(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');
        $this->memoryOverride['POST /api/participation/events'] = Http::response(['message' => 'x'], 503);

        $this->assertSame(0, $this->flush());

        [$row] = $this->rows();
        $this->assertNull($row->sent_at);
        $this->assertNull($row->failed_at);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertTrue(Carbon::parse($row->next_attempt_at)->isFuture());

        $this->assertSame(0, $this->flush(), 'not due yet: not attempted again');
        $this->assertCount(1, $this->memorySent('POST'));

        $this->memoryOverride = [];
        DB::table('participation_outbox')->update(['next_attempt_at' => now()->subMinute()]);
        $this->assertSame(1, $this->flush());
        $this->assertCount(1, $this->memoryEvents);
    }

    public function test_three_unreachable_events_in_a_row_stop_the_run(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations', 'billing-and-payments', 'getting-started', 'data-and-reports');
        $this->memoryOverride['POST /api/participation/events'] = fn () => throw new ConnectionException('down');
        $this->memoryRequests = [];

        $this->flush();

        $this->assertCount(1, $this->memoryRequests, 'once Memory is known to be unreachable the rest of the run fails fast');
        $this->assertSame(4, DB::table('participation_outbox')->whereNull('sent_at')->whereNull('failed_at')->count());
    }

    public function test_a_wrong_secret_is_logged_as_an_error_and_retried_slowly(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');
        $this->memoryOverride['POST /api/participation/events'] = Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401);
        Log::spy();

        $this->flush();

        Log::shouldHaveReceived('error')->once();
        [$row] = $this->rows();
        $this->assertNull($row->failed_at);
        $this->assertTrue(Carbon::parse($row->next_attempt_at)->gte(now()->addMinutes(59)));
    }

    public function test_an_event_memory_refuses_is_parked_and_never_retried(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations', 'billing-and-payments');
        $this->memoryOverride['POST /api/participation/events'] = Http::response(['code' => 'consent_required', 'message' => 'x'], 403);

        $this->flush();
        $this->memoryOverride = [];
        $this->flush();

        foreach ($this->rows() as $row) {
            $this->assertNotNull($row->failed_at);
            $this->assertSame('consent_required', $row->last_error);
        }
        $this->assertSame([], $this->memoryEvents);
    }

    public function test_a_conflict_on_a_deterministic_id_means_memory_already_holds_it(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');
        $this->memoryOverride['POST /api/participation/events'] = Http::response(['code' => 'event_conflict', 'message' => 'x'], 409);

        $this->assertSame(1, $this->flush());

        [$row] = $this->rows();
        $this->assertNotNull($row->sent_at);
        $this->assertNull($row->failed_at);
    }

    public function test_a_later_event_for_the_same_contribution_waits_for_the_earlier_one(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations', 'billing-and-payments');
        $calls = 0;
        $this->memoryOverride['POST /api/participation/events'] = function () use (&$calls) {
            $calls++;

            return Http::response(['message' => 'x'], 503);
        };

        $this->flush();

        $this->assertSame(1, $calls, 'the second event for the same answer is not attempted while the first is undelivered');
    }

    public function test_with_memory_not_configured_events_wait_and_nothing_is_sent(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');
        config(['participation.secret' => null]);
        $this->memoryRequests = [];

        $this->assertSame(0, $this->flush());

        $this->assertSame([], $this->memoryRequests);
        $this->assertCount(1, $this->rows());
        $this->assertNull($this->rows()[0]->sent_at);
    }

    public function test_the_flush_command_delivers_and_is_scheduled_every_minute(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');

        $code = Artisan::call('participation:flush-outbox');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Delivered 1 event(s).', Artisan::output());
        $this->assertCount(1, $this->memoryEvents);

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'participation:flush-outbox'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    public function test_old_delivered_rows_are_pruned_but_undelivered_ones_stay(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations', 'billing-and-payments');
        $this->flush();
        DB::table('participation_outbox')->orderBy('id')->limit(1)->update(['sent_at' => now()->subDays(40)]);
        DB::table('participation_outbox')->orderByDesc('id')->limit(1)->update(['sent_at' => null]);

        $this->assertSame(1, app(ParticipationOutbox::class)->prune());
        $this->assertCount(1, $this->rows());
    }

    // ---------------------------------------------------------------- account deletion

    public function test_deleting_an_account_erases_what_memory_holds_but_never_blocks_the_deletion(): void
    {
        $helper = $this->sharing();
        $this->accept($helper, 'integrations');
        $this->flush();
        $this->assertNotEmpty($this->memoryEvents);

        app(DeleteUser::class)->delete($helper->fresh());

        $this->assertNull(User::find($helper->id));
        $this->assertSame([], $this->memoryEvents);
        $this->assertSame(0, DB::table('participation_outbox')->count());

        $other = $this->sharing();
        $this->memoryOverride['DELETE /api/participation/{uuid}'] = fn () => throw new ConnectionException('down');
        app(DeleteUser::class)->delete($other->fresh());
        $this->assertNull(User::find($other->id), 'Memory being down must not keep the account alive');
    }

    public function test_deleting_an_account_that_never_shared_asks_memory_nothing(): void
    {
        $user = $this->person();
        $this->memoryRequests = [];

        app(DeleteUser::class)->delete($user);

        $this->assertSame([], $this->memoryRequests);
    }

    public function test_the_listener_is_wired_to_acceptance_and_verification_only(): void
    {
        $listeners = fn (string $event) => collect(app('events')->getListeners($event))->count();

        $this->assertTrue(class_exists(ForwardKnowledgeEventToMemory::class));
        $this->assertGreaterThan(0, $listeners(AnswerAccepted::class));
        $this->assertGreaterThan(0, $listeners(SolutionVerified::class));
        // Revokes, un-verifies and hides have their own listeners for other purposes, but none for Memory.
        $helper = $this->sharing();
        $listener = app(ForwardKnowledgeEventToMemory::class);
        $listener->handle(new AnswerAcceptanceRevoked('infodot:answer:1', ['author_uuid' => $helper->uuid]));
        $listener->handle(new SolutionUnverified('infodot:solution:1', ['author_uuid' => $helper->uuid]));
        $this->assertSame([], $this->rows());
    }
}
