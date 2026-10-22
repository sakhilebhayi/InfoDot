<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\TopicService;
use App\Support\Knowledge\TopicSuggestions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/**
 * "Questions you might be able to answer": opt-in, for the signed-in person alone, from topics where they have an accepted
 * answer or a verified solution. It never pushes, never ranks and never shows anything to anyone else.
 */
class TopicSuggestionsTest extends TestCase
{
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.dopemine.secret' => null]);
    }

    /** $helper has an answer accepted, by the asker, on a public question filed under $slug. */
    private function expertIn(User $helper, string $slug): Answer
    {
        $asker = $this->person('Asker of '.$slug);
        $question = $this->file($this->question($asker, 'public', 'Settled question about '.$slug), $slug);

        return app(AnswerService::class)->accept($this->answer($question, $helper), $asker);
    }

    private function open(string $title, string $slug, ?User $asker = null, string $visibility = 'public'): Questions
    {
        return $this->file($this->question($asker ?? $this->person('Someone'), $visibility, $title), $slug);
    }

    /**
     * The suggestions section of the points page, and nothing else: the header search lists the newest questions on every
     * page, so a title found elsewhere on the page proves nothing.
     */
    private function section(User $as): string
    {
        $html = $this->actingAs($as)->get('/points')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<section[^>]*id="topic-suggestions".*?</section>#s', $html, $m));

        return $m[0];
    }

    private function titles(User $user): array
    {
        return TopicSuggestions::questionsFor($user)->pluck('question')->all();
    }

    public function test_it_is_off_by_default_and_then_nothing_is_worked_out_or_shown(): void
    {
        $me = $this->person('Me');
        $this->expertIn($me, 'billing');
        $this->open('A billing question waiting', 'billing');
        $this->assertFalse($me->fresh()->wantsTopicSuggestions());

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $section = $this->section($me);
        $this->assertStringContainsString('Questions you might be able to answer', $section);
        $this->assertStringContainsString('Right now this is <strong>off</strong>', $section);
        $this->assertStringNotContainsString('A billing question waiting', $section);
        $this->assertStringNotContainsString('Based on your work in', $section);

        $this->assertSame([], array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'question_topic'))), 'no topic query runs while it is off');
    }

    public function test_the_person_switches_it_on_and_off_and_it_only_ever_changes_their_own_row(): void
    {
        $me = $this->person('Me');
        $other = $this->person('Other');

        $this->actingAs($me)->post(route('points.topic-suggestions'), ['topic_suggestions' => '1', 'user_id' => $other->id, 'id' => $other->id])
            ->assertRedirect(route('points').'#topic-suggestions')->assertSessionHas('topic_suggestions_notice');

        $this->assertTrue($me->fresh()->wantsTopicSuggestions());
        $this->assertFalse($other->fresh()->wantsTopicSuggestions(), 'a posted user id changes nothing');

        $this->actingAs($me)->post(route('points.topic-suggestions'), ['topic_suggestions' => '0'])->assertRedirect();
        $this->assertFalse($me->fresh()->wantsTopicSuggestions());
    }

    public function test_the_switch_needs_a_signed_in_person_a_valid_value_and_a_csrf_token_in_the_form(): void
    {
        $me = $this->person('Me');

        $this->post(route('points.topic-suggestions'), ['topic_suggestions' => '1'])->assertRedirect();
        $this->actingAs($me)->post(route('points.topic-suggestions'), ['topic_suggestions' => 'yes'])->assertSessionHasErrors('topic_suggestions');
        $this->actingAs($me)->post(route('points.topic-suggestions'))->assertSessionHasErrors('topic_suggestions');
        $this->assertFalse($me->fresh()->wantsTopicSuggestions());

        $html = $this->actingAs($me)->get('/points')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<form method="POST" action="'.preg_quote(route('points.topic-suggestions'), '#').'">\s*<input type="hidden" name="_token"#', $html);
        $this->assertStringContainsString('only you see it, and nobody is told or ranked', $html);
    }

    public function test_a_beginner_with_no_accepted_work_is_told_there_is_nothing_yet_and_nothing_is_wrong(): void
    {
        $me = $this->person('New');
        $me->forceFill(['topic_suggestions' => true])->save();
        $this->open('A billing question waiting', 'billing');

        $section = $this->section($me);
        $this->assertStringContainsString('Nothing to suggest yet', $section);
        $this->assertStringContainsString('no target and nothing to catch up on', $section);
        $this->assertStringNotContainsString('A billing question waiting', $section);
    }

    public function test_it_lists_open_public_questions_in_topics_where_the_person_has_shown_they_know_the_subject(): void
    {
        $me = $this->person('Me');
        $this->expertIn($me, 'billing');
        $this->open('Billing question one', 'billing');
        $this->open('Tasks question', 'tasks');
        $this->open('Mixed question', 'tasks')->topics()->attach($this->topic('billing')->id);

        $this->assertEqualsCanonicalizing(['Billing question one', 'Mixed question'], $this->titles($me));
    }

    public function test_a_verified_solution_counts_as_expertise_too_but_an_unverified_one_does_not(): void
    {
        $me = $this->person('Me');
        $mod = $this->moderator();
        $verified = $this->file($this->solution($me, 'public', 'Verified procedure'), 'tasks');
        $unverified = $this->file($this->solution($me, 'public', 'Unverified procedure'), 'hr');
        $this->open('A tasks question', 'tasks');
        $this->open('An hr question', 'hr');

        $this->assertSame([], $this->titles($me));
        app(ModerationService::class)->verify($verified, $mod);
        $this->assertSame(['A tasks question'], $this->titles($me));
        $this->assertNotNull($unverified);
    }

    public function test_work_that_does_not_count_gives_no_expertise(): void
    {
        $me = $this->person('Me');
        $asker = $this->person('Asker');
        $this->open('A billing question', 'billing');
        $this->open('A tasks question', 'tasks');
        $this->open('An hr question', 'hr');
        $this->open('A files question', 'files');

        // An answer nobody accepted.
        $this->answer($this->file($this->question($asker), 'billing'), $me);
        // An answer to a question that was later made company-only, accepted.
        $private = $this->file($this->question($asker, 'public', 'Private'), 'tasks');
        app(AnswerService::class)->accept($this->answer($private, $me), $asker);
        Questions::withoutGlobalScopes()->where('id', $private->id)->update(['visibility' => 'company']);
        // An answer on a question a moderator then hid.
        $hiddenQ = $this->file($this->question($asker, 'public', 'Hidden later'), 'hr');
        $hiddenAnswer = app(AnswerService::class)->accept($this->answer($hiddenQ, $me), $asker);
        app(ModerationService::class)->hide($hiddenQ, $this->moderator(), 'Hidden for the test.');
        // Accepted on a question of one's own: never help given.
        $mine = $this->file($this->question($me, 'public', 'My own'), 'files');
        $self = $this->answer($mine, $me);
        $self->forceFill(['accepted_at' => now(), 'accepted_by' => $me->id])->save();
        // A solution verified but company-only.
        $this->file($this->solution($me, 'company'), 'files')->forceFill(['verified_at' => now(), 'verified_by' => $this->moderator('Elsewhere')->id])->save();

        $this->assertSame([], $this->titles($me));
        $this->assertNotNull($hiddenAnswer);
    }

    public function test_a_topic_that_has_been_switched_off_is_no_longer_suggested(): void
    {
        $me = $this->person('Me');
        $this->expertIn($me, 'billing');
        $this->open('A billing question', 'billing');
        $this->assertSame(['A billing question'], $this->titles($me));

        app(TopicService::class)->setActive($this->topic('billing'), $this->moderator(), false);
        $this->assertSame([], $this->titles($me));
    }

    public function test_it_never_suggests_own_answered_solved_hidden_or_company_only_questions(): void
    {
        $me = $this->person('Me');
        $helper = $this->person('Helper');
        $this->expertIn($me, 'billing');
        $mod = $this->moderator();

        $this->open('My own billing question', 'billing', $me);
        $answered = $this->open('Already answered', 'billing');
        $this->answer($answered, $helper);
        $solved = $this->open('Already solved', 'billing');
        $solved->forceFill(['status' => 1])->save();
        $hidden = $this->open('A hidden one', 'billing');
        app(ModerationService::class)->hide($hidden, $mod, 'Hidden for the test.');
        $this->open('Private to a company', 'billing', null, 'company');
        $this->open('The only fair one', 'billing');

        $this->assertSame(['The only fair one'], $this->titles($me));
    }

    public function test_an_answer_that_is_hidden_or_by_the_asker_does_not_make_a_question_answered(): void
    {
        $me = $this->person('Me');
        $this->expertIn($me, 'billing');
        $asker = $this->person('Asker');
        $selfAnswered = $this->open('Only the asker replied', 'billing', $asker);
        $this->answer($selfAnswered, $asker);
        $hiddenAnswered = $this->open('Only a hidden answer', 'billing');
        $answer = $this->answer($hiddenAnswered, $this->person('Helper'));
        app(ModerationService::class)->hide($answer, $this->moderator(), 'Hidden for the test.');

        $this->assertEqualsCanonicalizing(['Only the asker replied', 'Only a hidden answer'], $this->titles($me));
    }

    public function test_at_most_five_newest_first(): void
    {
        $me = $this->person('Me');
        $this->expertIn($me, 'billing');

        foreach (range(1, 7) as $n) {
            $q = $this->open("Billing question $n", 'billing');
            Questions::withoutGlobalScopes()->where('id', $q->id)->update(['created_at' => now()->subMinutes(10 - $n)]);
        }

        $this->assertSame(['Billing question 7', 'Billing question 6', 'Billing question 5', 'Billing question 4', 'Billing question 3'], $this->titles($me));
    }

    public function test_the_points_page_shows_the_list_and_what_it_is_based_on_to_the_person_only(): void
    {
        $me = $this->person('Me');
        $me->forceFill(['topic_suggestions' => true])->save();
        $this->expertIn($me, 'billing');
        $this->open('A billing question waiting', 'billing');
        $this->open('A tasks question waiting', 'tasks');
        $stranger = $this->person('Stranger');

        $section = $this->section($me);
        $this->assertStringContainsString('Based on your work in', $section);
        $this->assertStringContainsString('Dot.Billing', $section);
        $this->assertStringContainsString('A billing question waiting', $section);
        $this->assertStringNotContainsString('A tasks question waiting', $section);
        $this->assertSame(['A billing question waiting'], $this->actingAs($me)->get('/points')->viewData('suggestedQuestions')->pluck('question')->all());

        // Nobody else sees it: not on their own page, and not on a profile.
        $theirs = $this->section($stranger);
        $this->assertStringNotContainsString('A billing question waiting', $theirs);
        $this->assertStringNotContainsString('Based on your work in', $theirs);
        $this->actingAs($stranger)->get('/user/profile/'.$stranger->id)->assertOk()->assertDontSee('Questions you might be able to answer');
        $this->actingAs($me)->get('/user/profile/'.$me->id)->assertOk()->assertDontSee('Questions you might be able to answer');
    }

    public function test_it_sends_nothing(): void
    {
        Mail::fake();
        Notification::fake();
        $me = $this->person('Me');
        $me->forceFill(['topic_suggestions' => true])->save();
        $this->expertIn($me, 'billing');
        $this->open('A billing question waiting', 'billing');

        $this->assertStringContainsString('A billing question waiting', $this->section($me));
        $this->actingAs($me)->post(route('points.topic-suggestions'), ['topic_suggestions' => '1']);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }
}
