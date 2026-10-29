<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserTopicBadge;
use App\Services\Knowledge\AchievementService;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\TopicBadges;
use App\Support\Knowledge\BadgeLevels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** R-13 specialist badges: per topic, from accepted or verified work, public unless hidden, display only. */
class SpecialistBadgesTest extends TestCase
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

    private function topicId(string $slug): int
    {
        return (int) Topic::where('slug', $slug)->value('id');
    }

    /** $n answers by $author, each accepted by somebody else, on public questions filed under $slug. Rows are written directly so the answer rate limit is not in the way. */
    private function accepted(User $author, string $slug, int $n, string $prefix = 'Question'): array
    {
        $answers = [];
        foreach (range(1, $n) as $i) {
            $asker = $this->person('Asker');
            $question = $this->question($asker, 'public', "$prefix $slug $i ".uniqid());
            DB::table('question_topic')->insert(['question_id' => $question->id, 'topic_id' => $this->topicId($slug)]);
            $answers[] = Answer::create(['question_id' => $question->id, 'user_id' => $author->id, 'body' => self::GOOD, 'body_hash' => md5(uniqid('', true)), 'status' => 'published'])
                ->forceFill(['accepted_at' => now(), 'accepted_by' => $asker->id]);
            end($answers)->save();
        }

        return $answers;
    }

    private function sync(User $u): void
    {
        app(AchievementService::class)->sync($u);
    }

    private function badge(User $u, string $slug): ?UserTopicBadge
    {
        return UserTopicBadge::where('user_id', $u->id)->where('topic_id', $this->topicId($slug))->first();
    }

    /** A public question filed under $slug that $author answered and $asker has not accepted. */
    private function questionWithAnswers(string $slug, array $authors): Questions
    {
        $asker = $this->person('Asker');
        $q = $this->question($asker, 'public', 'A question about '.$slug.' '.uniqid());
        DB::table('question_topic')->insert(['question_id' => $q->id, 'topic_id' => $this->topicId($slug)]);
        foreach ($authors as $author) {
            $this->answer($q, $author, self::GOOD.' by '.$author->name);
        }

        return $q;
    }

    // ---- levels --------------------------------------------------------------------------------------------------------

    public function test_levels_come_from_the_configured_work_counts(): void
    {
        $levels = new BadgeLevels;

        $this->assertNull($levels->levelFor(0));
        $this->assertNull($levels->levelFor(1));
        $this->assertSame('contributor', $levels->levelFor(2));
        $this->assertSame('contributor', $levels->levelFor(4));
        $this->assertSame('specialist', $levels->levelFor(5));
        $this->assertSame('specialist', $levels->levelFor(11));
        $this->assertSame('authority', $levels->levelFor(12));
        $this->assertSame('authority', $levels->levelFor(500));
        $this->assertGreaterThan($levels->rank('contributor'), $levels->rank('authority'));
    }

    public function test_a_badge_is_stored_per_topic_with_its_level_and_count(): void
    {
        $me = $this->person();
        $this->accepted($me, 'billing', 1);
        $this->sync($me);
        $this->assertNull($this->badge($me, 'billing'), 'one piece of work is below the lowest level');

        $this->accepted($me, 'billing', 1);
        $this->accepted($me, 'tasks', 5);
        $this->sync($me);

        $b = $this->badge($me, 'billing');
        $this->assertSame(['contributor', 2, 1], [$b->level, $b->work_count, $b->rules_version]);
        $this->assertSame(['specialist', 5], [$this->badge($me, 'tasks')->level, $this->badge($me, 'tasks')->work_count]);
        $this->assertSame(2, UserTopicBadge::where('user_id', $me->id)->count(), 'one row per topic, never an overall badge');
    }

    public function test_a_verified_solution_counts_like_an_accepted_answer(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        $this->accepted($me, 'billing', 1);
        $s = $this->solution($me);
        DB::table('solution_topic')->insert(['solution_id' => $s->id, 'topic_id' => $this->topicId('billing')]);
        app(ModerationService::class)->verify($s, $mod);

        $this->assertSame('contributor', $this->badge($me, 'billing')->level, 'the verify event refreshed it without a manual sync');
        $this->assertSame(2, $this->badge($me, 'billing')->work_count);
    }

    public function test_the_level_follows_the_work_up_and_down(): void
    {
        $me = $this->person();
        $answers = $this->accepted($me, 'billing', 5);
        $this->sync($me);
        $this->assertSame('specialist', $this->badge($me, 'billing')->level);

        app(ModerationService::class)->hide($answers[0], $this->moderator(), 'This answer is wrong and misleading.');
        $this->sync($me);
        $this->assertSame(['contributor', 4], [$this->badge($me, 'billing')->level, $this->badge($me, 'billing')->work_count]);
    }

    // ---- revoke and restore --------------------------------------------------------------------------------------------

    public function test_hiding_the_work_takes_the_badge_back_and_restoring_it_gives_it_back(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        [$a, $b] = $this->accepted($me, 'billing', 2);
        $this->sync($me);
        $this->assertNotNull($this->badge($me, 'billing'));

        app(ModerationService::class)->hide($a, $mod, 'This answer is wrong and misleading.');
        $this->assertNull($this->badge($me, 'billing'), 'the moderation event refreshed it');

        // Hiding an accepted answer also removes its acceptance, and restoring does not bring that back: the asker accepts again.
        app(ModerationService::class)->restore($a, $mod);
        $this->assertNull($this->badge($me, 'billing'), 'restored but not yet accepted again: still no badge');

        app(AnswerService::class)->accept($a->fresh(), User::find($a->question->user_id));
        $this->assertSame(['contributor', 2], [$this->badge($me, 'billing')->level, $this->badge($me, 'billing')->work_count]);
    }

    public function test_removing_an_acceptance_takes_the_badge_back(): void
    {
        $me = $this->person();
        [$a, $b] = $this->accepted($me, 'billing', 2);
        $this->sync($me);

        $asker = User::find($a->accepted_by);
        app(AnswerService::class)->revokeAcceptance($a->fresh(), $asker);

        $this->assertNull($this->badge($me, 'billing'));
    }

    // ---- the switch ------------------------------------------------------------------------------------------------------

    public function test_badges_are_visible_by_default_and_the_person_can_hide_and_show_them(): void
    {
        $me = $this->person();
        $this->assertTrue($me->fresh()->showsBadges());

        $this->actingAs($me)->post(route('profile.badges-visibility'), ['badges_visible' => '0'])->assertRedirect();
        $this->assertFalse($me->fresh()->showsBadges());

        $this->actingAs($me)->post(route('profile.badges-visibility'), ['badges_visible' => '1'])->assertRedirect();
        $this->assertTrue($me->fresh()->showsBadges());

        $this->actingAs($me)->post(route('profile.badges-visibility'), ['badges_visible' => 'maybe'])->assertSessionHasErrors('badges_visible');
    }

    public function test_the_switch_only_ever_changes_the_signed_in_persons_own_row(): void
    {
        $me = $this->person();
        $other = $this->person('Other');

        $this->actingAs($me)->post(route('profile.badges-visibility'), ['badges_visible' => '0', 'user_id' => $other->id, 'id' => $other->id]);

        $this->assertFalse($me->fresh()->showsBadges());
        $this->assertTrue($other->fresh()->showsBadges());
    }

    public function test_a_person_who_hides_their_badges_shows_nothing_anywhere_to_others(): void
    {
        $owner = $this->person('Hidden Owner');
        $viewer = $this->person('Viewer');
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $company->users()->attach($owner, ['role' => 'admin']);
        $company->users()->attach($viewer, ['role' => 'editor']);
        $this->accepted($owner, 'billing', 5);
        $this->sync($owner);
        $q = $this->questionWithAnswers('billing', [$owner]);

        $this->actingAs($viewer)->get('/user/profile/'.$owner->id)->assertOk()->assertSee('data-badge="specialist"', false);
        $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->assertSee('data-badge="specialist"', false);

        $owner->forceFill(['badges_visible' => false])->save();

        $this->actingAs($viewer)->get('/user/profile/'.$owner->id)->assertOk()->assertDontSee('data-badge=', false)->assertDontSee('Specialist badges');
        $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->assertDontSee('data-badge=', false);

        $own = $this->actingAs($owner)->get('/user/profile/'.$owner->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-badge="specialist"', $own, 'the owner still sees their own, so the switch makes sense');
        $this->assertStringContainsString('Right now this is <strong>off</strong>', $own);
    }

    public function test_the_profile_lists_the_badges_highest_level_first(): void
    {
        $me = $this->person();
        $this->accepted($me, 'billing', 2);
        $this->accepted($me, 'tasks', 12);
        $this->sync($me);

        $html = $this->actingAs($me)->get('/user/profile/'.$me->id)->assertOk()->getContent();

        $this->assertStringContainsString('data-badge="authority" data-badge-topic="tasks"', $html);
        $this->assertLessThan(strpos($html, 'data-badge-topic="billing"'), strpos($html, 'data-badge-topic="tasks"'));
        $this->assertStringContainsString('Show my specialist badges to other people', $html);
    }

    public function test_people_outside_your_companies_still_cannot_open_your_profile(): void
    {
        $owner = $this->person('Owner');
        $stranger = $this->person('Stranger');
        $this->accepted($owner, 'billing', 2);
        $this->sync($owner);

        $this->actingAs($stranger)->get('/user/profile/'.$owner->id)->assertNotFound();
    }

    // ---- beside answers --------------------------------------------------------------------------------------------------

    public function test_only_the_badge_for_a_topic_the_question_is_filed_under_shows_and_the_highest_level_wins(): void
    {
        $viewer = $this->person('Viewer');
        $expert = $this->person('Expert');
        $this->accepted($expert, 'billing', 2);   // contributor
        $this->accepted($expert, 'tasks', 12);    // authority, but the question is not about tasks
        $this->accepted($expert, 'projects', 5);  // specialist

        $q = $this->questionWithAnswers('billing', [$expert]);
        $html = $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-badge="contributor" data-badge-topic="billing"', $html);
        $this->assertStringNotContainsString('data-badge-topic="tasks"', $html);
        $this->assertStringNotContainsString('data-badge-topic="projects"', $html);

        // Filed under two topics: the higher of the author's two badges shows, one chip.
        DB::table('question_topic')->insert(['question_id' => $q->id, 'topic_id' => $this->topicId('projects')]);
        $html = $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-badge="'));
        $this->assertStringContainsString('data-badge="specialist" data-badge-topic="projects"', $html);
    }

    public function test_a_question_with_no_topic_shows_no_badge(): void
    {
        $viewer = $this->person('Viewer');
        $expert = $this->person('Expert');
        $this->accepted($expert, 'billing', 2);
        $q = $this->question($this->person('Asker'));
        $this->answer($q, $expert);

        $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->assertDontSee('data-badge=', false);
    }

    public function test_company_only_questions_never_produce_or_show_a_badge(): void
    {
        $asker = $this->person('Asker');
        $colleague = $this->person('Colleague');
        $asker->currentTeam->users()->attach($colleague->id, ['role' => 'member']);

        // Work on company-only questions earns nothing, however much of it there is.
        foreach (range(1, 6) as $i) {
            $private = $this->question($asker, 'company', "Private question $i");
            DB::table('question_topic')->insert(['question_id' => $private->id, 'topic_id' => $this->topicId('billing')]);
            Answer::create(['question_id' => $private->id, 'user_id' => $colleague->id, 'body' => self::GOOD, 'body_hash' => md5("p$i"), 'status' => 'published'])
                ->forceFill(['accepted_at' => now(), 'accepted_by' => $asker->id])->save();
        }
        $this->sync($colleague);
        $this->assertSame(0, UserTopicBadge::where('user_id', $colleague->id)->count());

        // A badge earned in public does not show up beside an answer on a company-only question either.
        $this->accepted($colleague, 'billing', 5);
        $this->sync($colleague);
        $this->assertNotNull($this->badge($colleague, 'billing'));
        $private = $this->question($asker, 'company', 'Another private question');
        DB::table('question_topic')->insert(['question_id' => $private->id, 'topic_id' => $this->topicId('billing')]);
        $this->answer($private, $colleague);

        $this->actingAs($asker)->get('/question/view/'.$private->id)->assertOk()->assertSee('Another private question')->assertDontSee('data-badge=', false);
        $this->assertSame([], app(TopicBadges::class)->forQuestion($private, [$colleague->id]));
    }

    public function test_a_hidden_question_shows_no_badges(): void
    {
        $expert = $this->person('Expert');
        $this->accepted($expert, 'billing', 2);
        $q = $this->questionWithAnswers('billing', [$expert]);
        app(ModerationService::class)->hide($q, $this->moderator(), 'This question breaks the community rules.');

        $this->assertSame([], app(TopicBadges::class)->forQuestion($q->fresh(), [$expert->id]));
    }

    // ---- batching and ranking --------------------------------------------------------------------------------------------

    public function test_badge_lookup_costs_the_same_however_many_answers_there_are(): void
    {
        $experts = collect(range(1, 8))->map(function ($i) {
            $e = $this->person("Expert $i");
            $this->accepted($e, 'billing', 2);

            return $e;
        });
        $q = $this->question($this->person('Asker'));
        DB::table('question_topic')->insert(['question_id' => $q->id, 'topic_id' => $this->topicId('billing')]);
        $service = app(TopicBadges::class);

        $queries = function (array $ids) use ($service, $q): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->forQuestion($q, $ids);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $one = $queries([$experts[0]->id]);
        $eight = $queries($experts->pluck('id')->all());

        $this->assertSame($one, $eight);
        $this->assertLessThanOrEqual(2, $eight);
    }

    public function test_the_page_asks_for_badges_once_whatever_the_number_of_answers(): void
    {
        $viewer = $this->person('Viewer');
        $count = function (int $answers) use ($viewer): int {
            $authors = collect(range(1, $answers))->map(function ($i) {
                $e = $this->person("Author $i");
                $this->accepted($e, 'billing', 2);

                return $e;
            })->all();
            $q = $this->questionWithAnswers('billing', $authors);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk();
            DB::disableQueryLog();

            return collect(DB::getQueryLog())->filter(fn ($l) => str_contains($l['query'], 'user_topic_badges'))->count();
        };

        $this->assertSame($count(1), $count(5));
        $this->assertSame(1, $count(2));
    }

    public function test_badges_never_change_the_order_of_answers(): void
    {
        $viewer = $this->person('Viewer');
        $plain = $this->person('Plain Newcomer');
        $expert = $this->person('Expert Authority');
        $this->accepted($expert, 'billing', 12);
        $this->sync($expert);

        $asker = $this->person('Asker');
        $q = $this->question($asker, 'public', 'Ordering question');
        DB::table('question_topic')->insert(['question_id' => $q->id, 'topic_id' => $this->topicId('billing')]);
        // The newcomer answers first, the authority second: oldest first, so the newcomer stays on top.
        $first = Answer::create(['question_id' => $q->id, 'user_id' => $plain->id, 'body' => 'FIRSTANSWER '.self::GOOD, 'body_hash' => 'o1', 'status' => 'published'])->forceFill(['created_at' => now()->subHour()]);
        $first->save();
        Answer::create(['question_id' => $q->id, 'user_id' => $expert->id, 'body' => 'SECONDANSWER '.self::GOOD, 'body_hash' => 'o2', 'status' => 'published']);

        $with = $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-badge="authority"', $with);
        $this->assertLessThan(strpos($with, 'SECONDANSWER'), strpos($with, 'FIRSTANSWER'));

        $expert->forceFill(['badges_visible' => false])->save();
        UserTopicBadge::query()->delete();
        $without = $this->actingAs($viewer)->get('/question/view/'.$q->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('data-badge="authority"', $without);
        $this->assertLessThan(strpos($without, 'SECONDANSWER'), strpos($without, 'FIRSTANSWER'), 'same order with or without the badge');
    }

    public function test_a_badge_grants_no_permissions(): void
    {
        $expert = $this->person('Expert');
        $this->accepted($expert, 'billing', 12);
        $this->sync($expert);
        $q = $this->questionWithAnswers('billing', [$this->person('Other')]);
        $answer = Answer::where('question_id', $q->id)->first();

        $this->assertFalse(app(AnswerService::class)->canAccept($answer, $expert), 'only the asker or a moderator can accept, whatever the badge');
        $this->assertFalse($expert->fresh()->isModerator());
    }

    // ---- backfill --------------------------------------------------------------------------------------------------------

    public function test_the_backfill_command_builds_badges_for_earlier_work_and_is_idempotent(): void
    {
        $me = $this->person();
        $this->accepted($me, 'billing', 5);
        $this->assertSame(0, UserTopicBadge::count(), 'rows written directly raised no event');

        $this->artisan('badges:backfill')->expectsOutputToContain('1 awarded')->assertSuccessful();
        $first = UserTopicBadge::orderBy('id')->get(['id', 'user_id', 'topic_id', 'level', 'work_count', 'updated_at'])->toArray();
        $this->assertCount(1, $first);
        $this->assertSame('specialist', $first[0]['level']);

        $this->artisan('badges:backfill')->expectsOutputToContain('0 awarded, 0 changed, 0 taken back')->assertSuccessful();
        $this->assertSame($first, UserTopicBadge::orderBy('id')->get(['id', 'user_id', 'topic_id', 'level', 'work_count', 'updated_at'])->toArray());
    }

    public function test_the_backfill_takes_back_a_badge_whose_work_is_gone_and_can_target_one_person(): void
    {
        $me = $this->person();
        $other = $this->person('Other');
        $this->accepted($me, 'billing', 2);
        $this->accepted($other, 'billing', 2);
        UserTopicBadge::create(['user_id' => $me->id, 'topic_id' => $this->topicId('tasks'), 'level' => 'authority', 'work_count' => 40, 'rules_version' => 1]);

        $this->artisan('badges:backfill', ['--user' => (string) $me->id])->assertSuccessful();

        $this->assertNull($this->badge($me, 'tasks'), 'a badge with no work behind it is removed');
        $this->assertNotNull($this->badge($me, 'billing'));
        $this->assertNull($this->badge($other, 'billing'), 'the other person was not touched');
        $this->artisan('badges:backfill', ['--user' => '999999'])->assertFailed();
    }

    public function test_a_new_level_table_is_picked_up_by_a_backfill_with_a_new_version(): void
    {
        $me = $this->person();
        $this->accepted($me, 'billing', 3);
        $this->sync($me);
        $this->assertSame('contributor', $this->badge($me, 'billing')->level);

        config(['achievements.badges.version' => 2, 'achievements.badges.levels.specialist.min_work' => 3]);
        $this->artisan('badges:backfill')->expectsOutputToContain('1 changed')->assertSuccessful();

        $this->assertSame(['specialist', 2], [$this->badge($me, 'billing')->level, $this->badge($me, 'billing')->rules_version]);
    }
}
