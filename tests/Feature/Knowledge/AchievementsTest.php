<?php

namespace Tests\Feature\Knowledge;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentModerationChanged;
use App\Events\Knowledge\QuestionSubmitted;
use App\Listeners\RefreshAchievements;
use App\Models\Answer;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\Knowledge\AchievementService;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Support\Knowledge\AchievementEvaluator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class AchievementsTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.dopemine.secret' => null]);
    }

    /** @return list<string> keys that are currently earned */
    private function earned(User $user): array
    {
        return UserAchievement::where('user_id', $user->id)->whereNull('revoked_at')->orderBy('id')->pluck('key')->all();
    }

    private function sync(User $user): array
    {
        return app(AchievementService::class)->sync($user);
    }

    /** A question the way the app saves one: the row, then the event the controller raises. */
    private function submitted(User $asker, string $visibility = 'public', string $title = 'How do I register a clothing brand?'): Questions
    {
        $question = $this->question($asker, $visibility, $title);
        event(new QuestionSubmitted('infodot:question:'.$question->id, ['question_id' => $question->id, 'author_uuid' => $asker->uuid, 'tenant_team_id' => $question->team_id, 'visibility' => $question->visibility]));

        return $question;
    }

    /** An answer by $author, accepted by the asker of the question, through the real service. */
    private function acceptedAnswer(User $author, ?User $asker = null, string $body = self::GOOD): Answer
    {
        $asker ??= $this->person('Asker');
        $answer = $this->answer($this->question($asker), $author, $body);

        return app(AnswerService::class)->accept($answer, $asker);
    }

    // ---- what counts -------------------------------------------------------------------------------------------------

    public function test_nothing_is_earned_without_contribution(): void
    {
        $me = $this->person();

        $this->assertSame(['awarded' => 0, 'revoked' => 0, 'restored' => 0], $this->sync($me));
        $this->assertSame([], $this->earned($me));
    }

    public function test_a_public_question_and_an_answer_to_someone_elses_question_earn_the_first_milestones(): void
    {
        $me = $this->person();
        $other = $this->person();
        $this->submitted($me);
        $this->answer($this->question($other), $me);

        $this->assertEqualsCanonicalizing(['first_question', 'first_answer'], $this->earned($me));
    }

    public function test_company_only_work_never_counts(): void
    {
        $me = $this->person();
        $other = $this->person();
        $this->question($me, 'company');
        Answer::create(['question_id' => $this->question($other, 'company')->id, 'user_id' => $me->id, 'body' => self::GOOD, 'body_hash' => 'c', 'status' => 'published']);
        $this->solution($me, 'company');

        $this->sync($me);

        $this->assertSame(['questions' => 0, 'answers' => 0, 'accepted' => 0, 'verified' => 0], app(AchievementService::class)->counts($me));
        $this->assertSame([], $this->earned($me));
    }

    public function test_answering_your_own_question_and_accepting_it_yourself_earn_nothing(): void
    {
        $me = $this->person();
        $mine = $this->submitted($me);
        $answer = $this->answer($mine, $me); // a self-answer: allowed, but not help given

        $this->assertEqualsCanonicalizing(['first_question'], $this->earned($me));

        // Even if a row says the author accepted their own answer, it is not help given.
        $answer->forceFill(['accepted_at' => now(), 'accepted_by' => $me->id])->save();
        $this->sync($me);

        $this->assertSame(0, app(AchievementService::class)->counts($me)['accepted']);
        $this->assertNotContains('first_accepted', $this->earned($me));
    }

    public function test_an_unpublished_or_removed_answer_does_not_count(): void
    {
        $me = $this->person();
        $answer = $this->answer($this->question($this->person()), $me);
        $this->assertContains('first_answer', $this->earned($me));

        $answer->forceFill(['status' => Answer::REMOVED])->save();
        $this->sync($me);
        $this->assertNotContains('first_answer', $this->earned($me));

        $answer->forceFill(['status' => Answer::PUBLISHED])->save();
        $answer->delete(); // soft delete
        $this->sync($me);
        $this->assertNotContains('first_answer', $this->earned($me));
    }

    public function test_the_counts_reach_the_ten_answers_milestone_at_ten_and_not_at_nine(): void
    {
        $me = $this->person();
        $asker = $this->person();

        foreach (range(1, 9) as $i) {
            Answer::create(['question_id' => $this->question($asker, 'public', 'Question '.$i)->id, 'user_id' => $me->id, 'body' => self::GOOD, 'body_hash' => 'h'.$i, 'status' => 'published']);
        }
        $this->sync($me);
        $this->assertContains('first_answer', $this->earned($me));
        $this->assertNotContains('answers_10', $this->earned($me));

        Answer::create(['question_id' => $this->question($asker, 'public', 'Question 10')->id, 'user_id' => $me->id, 'body' => self::GOOD, 'body_hash' => 'h10', 'status' => 'published']);
        $this->sync($me);
        $this->assertContains('answers_10', $this->earned($me));
        $this->assertNotContains('answers_50', $this->earned($me));
    }

    public function test_an_answer_accepted_by_someone_else_earns_the_first_accepted_milestone(): void
    {
        $me = $this->person();
        $this->acceptedAnswer($me);

        $this->assertContains('first_accepted', $this->earned($me));
        $this->assertNotContains('accepted_5', $this->earned($me));
    }

    public function test_a_verified_solution_counts_only_when_public_and_verified_by_somebody_else(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        $mine = $this->solution($me);
        $private = $this->solution($me, 'company', 'Internal procedure');
        $unverified = $this->solution($me, 'public', 'Not checked yet');

        app(ModerationService::class)->verify($mine, $mod);
        $private->forceFill(['verified_at' => now(), 'verified_by' => $mod->id])->save(); // verified, but company-only

        $this->assertSame(1, app(AchievementService::class)->counts($me)['verified']);
        $this->assertContains('first_verified', $this->earned($me));

        // A verification recorded as made by the author themselves is not independent.
        $unverified->forceFill(['verified_at' => now(), 'verified_by' => $me->id])->save();
        $this->assertSame(1, app(AchievementService::class)->counts($me)['verified']);
    }

    // ---- reversibility -----------------------------------------------------------------------------------------------

    public function test_hiding_an_accepted_answer_takes_the_milestones_back_and_restoring_gives_them_back(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        $answer = $this->acceptedAnswer($me);
        $this->assertEqualsCanonicalizing(['first_answer', 'first_accepted'], $this->earned($me));
        $firstEarned = UserAchievement::where('user_id', $me->id)->where('key', 'first_answer')->value('earned_at');

        $this->travel(5)->days();
        app(ModerationService::class)->hide($answer, $mod, 'This answer is wrong and misleading.');
        $this->assertSame([], $this->earned($me));

        $row = UserAchievement::where('user_id', $me->id)->where('key', 'first_answer')->first();
        $this->assertNotNull($row->revoked_at, 'taken back, not deleted');

        $this->travel(5)->days();
        app(ModerationService::class)->restore($answer->refresh(), $mod);
        // Restoring shows the answer again but does not bring back the removed acceptance.
        $this->assertSame(['first_answer'], $this->earned($me));

        $row = $row->fresh();
        $this->assertNull($row->revoked_at);
        $this->assertTrue($row->earned_at->equalTo($firstEarned), 'earned_at keeps the first time');
    }

    public function test_hiding_a_question_takes_back_the_milestones_of_everyone_who_answered_it(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $mod = $this->moderator();
        $question = $this->submitted($asker);
        $this->answer($question, $answerer);
        $this->assertContains('first_question', $this->earned($asker));
        $this->assertContains('first_answer', $this->earned($answerer));

        app(ModerationService::class)->hide($question, $mod, 'This question is spam.');

        $this->assertSame([], $this->earned($asker));
        $this->assertSame([], $this->earned($answerer));

        app(ModerationService::class)->restore($question->refresh(), $mod);

        $this->assertContains('first_question', $this->earned($asker));
        $this->assertContains('first_answer', $this->earned($answerer));
    }

    public function test_an_upheld_appeal_that_shows_the_content_again_gives_the_milestone_back(): void
    {
        $me = $this->person();
        $hider = $this->moderator('Hider');
        $decider = $this->moderator('Decider');
        $question = $this->submitted($me);
        app(ModerationService::class)->hide($question, $hider, 'This looks like an advert.');
        $this->assertSame([], $this->earned($me));

        $appeal = app(ModerationService::class)->fileAppeal($question->refresh(), $me, 'It is a real question about my business.');
        app(ModerationService::class)->decideAppeal($appeal, $decider, true, null);

        $this->assertSame(ModerationAppeal::UPHELD, $appeal->fresh()->status);
        $this->assertSame(['first_question'], $this->earned($me));
    }

    public function test_revoking_an_acceptance_and_removing_a_verification_take_those_milestones_back(): void
    {
        $me = $this->person();
        $asker = $this->person('Asker');
        $mod = $this->moderator();
        $answer = $this->acceptedAnswer($me, $asker);
        $solution = $this->solution($me);
        app(ModerationService::class)->verify($solution, $mod);
        $this->assertEqualsCanonicalizing(['first_answer', 'first_accepted', 'first_verified'], $this->earned($me));

        app(AnswerService::class)->revokeAcceptance($answer, $asker);
        $this->assertEqualsCanonicalizing(['first_answer', 'first_verified'], $this->earned($me));

        app(ModerationService::class)->unverify($solution->refresh(), $mod, 'The steps no longer work.');
        $this->assertSame(['first_answer'], $this->earned($me));
    }

    public function test_hiding_a_verified_solution_takes_the_verified_milestone_back(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        $solution = $this->solution($me);
        app(ModerationService::class)->verify($solution, $mod);
        $this->assertSame(['first_verified'], $this->earned($me));

        app(ModerationService::class)->hide($solution, $mod, 'The procedure is unsafe.');

        $this->assertSame([], $this->earned($me));
    }

    public function test_moving_the_acceptance_to_another_answer_keeps_each_persons_milestones_correct(): void
    {
        $first = $this->person('First');
        $second = $this->person('Second');
        $asker = $this->person('Asker');
        $question = $this->question($asker);
        $a1 = $this->answer($question, $first);
        $a2 = $this->answer($question, $second, self::GOOD.' And keep the receipts.');
        app(AnswerService::class)->accept($a1, $asker);
        $this->assertContains('first_accepted', $this->earned($first));

        app(AnswerService::class)->accept($a2, $asker);

        $this->assertNotContains('first_accepted', $this->earned($first));
        $this->assertContains('first_accepted', $this->earned($second));
    }

    public function test_syncing_is_idempotent_and_never_duplicates_rows(): void
    {
        $me = $this->person();
        $this->acceptedAnswer($me);

        $before = UserAchievement::where('user_id', $me->id)->orderBy('id')->get()->map->only(['key', 'earned_at', 'revoked_at', 'rule_version'])->all();
        $again = $this->sync($me) + $this->sync($me);
        $after = UserAchievement::where('user_id', $me->id)->orderBy('id')->get()->map->only(['key', 'earned_at', 'revoked_at', 'rule_version'])->all();

        $this->assertSame(['awarded' => 0, 'revoked' => 0, 'restored' => 0], $again);
        $this->assertEquals($before, $after);
        $this->assertSame(2, UserAchievement::where('user_id', $me->id)->count());
    }

    public function test_every_award_records_the_rule_version(): void
    {
        config(['achievements.version' => 7]);
        $me = $this->person();
        $this->question($me);
        $this->sync($me);

        $this->assertSame(7, UserAchievement::where('user_id', $me->id)->value('rule_version'));
    }

    // ---- wiring ------------------------------------------------------------------------------------------------------

    public function test_the_listener_is_wired_to_the_knowledge_events_and_the_points_forwarding_still_is(): void
    {
        foreach ([QuestionSubmitted::class, AnswerPublished::class, AnswerAccepted::class, AnswerAcceptanceRevoked::class, ContentModerationChanged::class] as $event) {
            $this->assertTrue(Event::hasListeners($event), $event);
        }

        $me = $this->person();
        $this->answer($this->question($this->person()), $me);

        $this->assertDatabaseHas('reward_outbox', ['type' => 'infodot.answer.published']);
        $this->assertContains('first_answer', $this->earned($me));
    }

    public function test_a_failing_milestone_check_never_breaks_the_action_that_raised_it(): void
    {
        $me = $this->person();
        $this->app->instance(AchievementService::class, new class(app(AchievementEvaluator::class)) extends AchievementService
        {
            public function sync(User $user): array
            {
                throw new \RuntimeException('boom');
            }
        });

        $answer = $this->answer($this->question($this->person()), $me);

        $this->assertNotNull($answer->id, 'the answer was still published');
    }

    public function test_the_listener_ignores_events_it_does_not_know(): void
    {
        $this->person();
        (new RefreshAchievements(app(AchievementService::class)))->handle(new \stdClass);
        $this->assertSame(0, UserAchievement::count());
    }

    // ---- backfill ----------------------------------------------------------------------------------------------------

    public function test_the_backfill_awards_people_who_contributed_before_milestones_existed_and_prints_counts_only(): void
    {
        $me = $this->person('Secret Name');
        $quiet = $this->person();
        $this->submitted($me);
        $this->answer($this->question($quiet), $me);
        UserAchievement::query()->delete(); // what the database looked like before milestones existed

        $this->artisan('achievements:backfill')
            ->expectsOutput('Checked 2 users: 3 awarded, 0 restored, 0 taken back.')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['first_question', 'first_answer'], $this->earned($me));
        $this->assertSame(['first_question'], $this->earned($quiet), 'the other person asked a public question too');
    }

    public function test_the_backfill_is_idempotent_and_can_target_one_user(): void
    {
        $me = $this->person();
        $other = $this->person();
        $this->question($me);
        $this->question($other);

        $this->artisan('achievements:backfill', ['--user' => $me->id])->expectsOutput('Checked 1 users: 1 awarded, 0 restored, 0 taken back.')->assertSuccessful();
        $this->assertSame(['first_question'], $this->earned($me));
        $this->assertSame([], $this->earned($other));

        $this->artisan('achievements:backfill', ['--user' => $me->uuid])->expectsOutput('Checked 1 users: 0 awarded, 0 restored, 0 taken back.')->assertSuccessful();
        $this->artisan('achievements:backfill')->assertSuccessful();
        $this->artisan('achievements:backfill')->expectsOutput('Checked 2 users: 0 awarded, 0 restored, 0 taken back.')->assertSuccessful();
        $this->assertSame(['first_question'], $this->earned($other));
        $this->assertSame(2, UserAchievement::count());
        $this->artisan('achievements:backfill', ['--user' => 'nobody'])->assertFailed();
    }

    public function test_the_backfill_repairs_a_milestone_whose_work_was_hidden_without_an_event(): void
    {
        $me = $this->person();
        $question = $this->submitted($me);
        $this->assertSame(['first_question'], $this->earned($me));

        $question->forceFill(['hidden_at' => now()])->save(); // no event raised

        $this->artisan('achievements:backfill')->expectsOutput('Checked 1 users: 0 awarded, 0 restored, 1 taken back.')->assertSuccessful();
        $this->assertSame([], $this->earned($me));
    }

    public function test_submitting_a_question_through_the_form_earns_the_first_question_milestone(): void
    {
        $me = $this->person();

        $this->actingAs($me)->post('/questions/add', ['topics' => [Topic::query()->value('id')], 'question' => 'How do I register a brand?', 'description' => 'I want the steps in order.', 'publish' => '1'])->assertRedirect('questions');
        $this->assertSame(['first_question'], $this->earned($me));

        // A company-only question is not public work, so it adds nothing.
        $other = $this->person();
        $this->actingAs($other)->post('/questions/add', ['topics' => [Topic::query()->value('id')], 'question' => 'Our internal supplier terms?', 'description' => 'Only for our company.'])->assertRedirect('questions');
        $this->assertSame([], $this->earned($other));
    }

    // ---- schema ------------------------------------------------------------------------------------------------------

    public function test_the_tables_have_the_agreed_shape(): void
    {
        $this->assertTrue(Schema::hasColumns('user_achievements', ['user_id', 'key', 'earned_at', 'revoked_at', 'rule_version']));
        $this->assertTrue(Schema::hasColumn('users', 'achievements_public'));
        $this->assertFalse(User::factory()->create()->fresh()->achievements_public, 'sharing is off by default');
    }

    public function test_one_row_per_person_per_milestone(): void
    {
        $me = $this->person();
        UserAchievement::create(['user_id' => $me->id, 'key' => 'first_question', 'earned_at' => now(), 'rule_version' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);
        UserAchievement::create(['user_id' => $me->id, 'key' => 'first_question', 'earned_at' => now(), 'rule_version' => 1]);
    }

    public function test_deleting_a_person_removes_their_milestones_with_them(): void
    {
        $me = $this->person();
        $this->submitted($me);
        $this->assertSame(1, UserAchievement::count());

        \DB::table('questions')->where('user_id', $me->id)->delete();
        $me->delete();

        $this->assertSame(0, UserAchievement::count());
    }
}
