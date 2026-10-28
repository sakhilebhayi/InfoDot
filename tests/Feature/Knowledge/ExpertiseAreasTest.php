<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Services\Knowledge\AchievementService;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** Areas of expertise on the profile: derived from accepted and verified work, never declared, private until shared. */
class ExpertiseAreasTest extends TestCase
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

    /** An answer by $author, accepted by somebody else, on a public question filed under the topics. */
    private function acceptedIn(User $author, array $slugs, string $title): Answer
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker, 'public', $title);
        foreach ($slugs as $slug) {
            DB::table('question_topic')->insert(['question_id' => $question->id, 'topic_id' => $this->topicId($slug)]);
        }

        return app(AnswerService::class)->accept($this->answer($question, $author), $asker);
    }

    /** Like acceptedIn() but writes the rows, so the answer rate limit does not get in the way of volume. */
    private function acceptedRow(User $author, string $slug, string $title): void
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker, 'public', $title);
        DB::table('question_topic')->insert(['question_id' => $question->id, 'topic_id' => $this->topicId($slug)]);
        Answer::create(['question_id' => $question->id, 'user_id' => $author->id, 'body' => self::GOOD, 'body_hash' => md5($title), 'status' => 'published'])
            ->forceFill(['accepted_at' => now(), 'accepted_by' => $asker->id])->save();
    }

    public function test_a_topic_needs_the_minimum_amount_of_accepted_work_and_names_the_counts(): void
    {
        $me = $this->person();
        $this->acceptedIn($me, ['billing'], 'Billing one');
        $this->assertSame([], app(AchievementService::class)->expertise($me), 'one piece of work is not an area');

        $this->acceptedIn($me, ['billing'], 'Billing two');
        $areas = app(AchievementService::class)->expertise($me);

        $this->assertCount(1, $areas);
        $this->assertSame('billing', $areas[0]['slug']);
        $this->assertSame(2, $areas[0]['accepted']);
        $this->assertSame(0, $areas[0]['verified']);
    }

    public function test_verified_solutions_count_and_an_item_counts_once_under_each_of_its_topics(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        $this->acceptedIn($me, ['billing', 'tasks'], 'Both topics');
        $solution = $this->solution($me);
        DB::table('solution_topic')->insert(['solution_id' => $solution->id, 'topic_id' => $this->topicId('billing')]);
        app(ModerationService::class)->verify($solution, $mod);

        $areas = collect(app(AchievementService::class)->expertise($me))->keyBy('slug');

        $this->assertSame(2, $areas['billing']['work']);
        $this->assertSame(1, $areas['billing']['accepted']);
        $this->assertSame(1, $areas['billing']['verified']);
        $this->assertArrayNotHasKey('tasks', $areas->all(), 'one piece of work in a topic stays below the minimum');
    }

    public function test_the_list_is_ordered_by_work_then_name_and_capped(): void
    {
        config(['achievements.expertise.max_areas' => 2]);
        $me = $this->person();
        foreach (['billing' => 3, 'tasks' => 2, 'projects' => 2] as $slug => $n) {
            foreach (range(1, $n) as $i) {
                $this->acceptedRow($me, $slug, $slug.' '.$i);
            }
        }

        $names = array_column(app(AchievementService::class)->expertise($me), 'slug');

        $this->assertCount(2, $names);
        $this->assertSame('billing', $names[0]);
    }

    public function test_work_that_does_not_qualify_never_counts(): void
    {
        $me = $this->person();
        $mod = $this->moderator();

        // Company-only question, a self-answer, an answer nobody accepted, and an accepted answer that was then hidden.
        $private = $this->question($this->person('P'), 'company', 'Private');
        DB::table('question_topic')->insert(['question_id' => $private->id, 'topic_id' => $this->topicId('billing')]);
        $ownQuestion = $this->question($me, 'public', 'Own');
        DB::table('question_topic')->insert(['question_id' => $ownQuestion->id, 'topic_id' => $this->topicId('billing')]);
        $unaccepted = $this->question($this->person('U'), 'public', 'Unaccepted');
        DB::table('question_topic')->insert(['question_id' => $unaccepted->id, 'topic_id' => $this->topicId('billing')]);
        $this->answer($unaccepted, $me);
        $hidden = $this->acceptedIn($me, ['billing'], 'Hidden later');
        app(ModerationService::class)->hide($hidden, $mod, 'This answer is wrong and misleading.');

        $this->assertSame([], app(AchievementService::class)->expertise($me));
    }

    public function test_help_you_gave_to_yourself_does_not_count(): void
    {
        $me = $this->person();
        $q = $this->question($me, 'public', 'Mine');
        DB::table('question_topic')->insert(['question_id' => $q->id, 'topic_id' => $this->topicId('billing')]);
        foreach ([1, 2] as $i) {
            Answer::create(['question_id' => $q->id, 'user_id' => $me->id, 'body' => self::GOOD, 'body_hash' => 's'.$i, 'status' => 'published', 'is_self_answer' => true])
                ->forceFill(['accepted_at' => now(), 'accepted_by' => $me->id])->save();
        }

        $this->assertSame([], app(AchievementService::class)->expertise($me));
    }

    public function test_others_see_the_areas_only_when_the_person_shares_their_achievements(): void
    {
        $owner = $this->person('Owner');
        $viewer = $this->person('Viewer');
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $company->users()->attach($owner, ['role' => 'admin']);
        $company->users()->attach($viewer, ['role' => 'editor']);
        $this->acceptedIn($owner, ['billing'], 'B1');
        $this->acceptedIn($owner, ['billing'], 'B2');

        $own = $this->actingAs($owner)->get('/user/profile/'.$owner->id)->assertOk()->getContent();
        $this->assertStringContainsString('Areas of expertise', $own);
        $this->assertStringContainsString('data-expertise="billing"', $own);
        $this->assertStringContainsString('Other people cannot see this list', $own);

        $this->actingAs($viewer)->get('/user/profile/'.$owner->id)->assertOk()->assertDontSee('Areas of expertise');

        $owner->forceFill(['achievements_public' => true])->save();
        $seen = $this->actingAs($viewer)->get('/user/profile/'.$owner->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-expertise="billing"', $seen);
        $this->assertStringContainsString('2 accepted answers', $seen);
    }

    public function test_a_person_without_qualifying_work_has_no_section(): void
    {
        $me = $this->person();

        $this->actingAs($me)->get('/user/profile/'.$me->id)->assertOk()->assertDontSee('Areas of expertise');
    }
}
