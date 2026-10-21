<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Answers;
use App\Livewire\HiddenNotice;
use App\Livewire\Moderation\Queue;
use App\Livewire\VerifySolution;
use App\Models\Comment;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/**
 * What a real-browser pass over the answers, reporting, moderation and verification screens turned up. Each test names
 * the thing that was wrong on screen.
 */
class BrowserQaFixesTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    public function test_a_hidden_question_and_solution_say_only_the_author_and_moderators_can_see_them(): void
    {
        $author = $this->person('Author');
        $mod = $this->moderator('Mod');
        $question = $this->question($author);
        $solution = $this->solution($author);

        $this->actingAs($author)->get('/question/view/'.$question->id)->assertOk()->assertSee('Everyone')->assertDontSee('You and moderators');
        $this->actingAs($author)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Everyone')->assertDontSee('You and moderators');

        app(ModerationService::class)->hide($question, $mod, 'Off topic for this community.');
        app(ModerationService::class)->hide($solution, $mod, 'Advice that breaks the tax rules.');

        $this->actingAs($author)->get('/question/view/'.$question->id)->assertOk()->assertSee('You and moderators')->assertDontSee('Everyone');
        $this->actingAs($author)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('You and moderators')->assertDontSee('Everyone');
    }

    public function test_an_empty_answer_list_on_a_hidden_question_does_not_invite_an_answer_nobody_can_post(): void
    {
        $author = $this->person('Author');
        $question = $this->question($author);

        Livewire::actingAs($author)->test(Answers::class, ['question' => $question])
            ->assertSee('tell the next person how')->assertSee('Post your answer');

        app(ModerationService::class)->hide($question, $this->moderator(), 'Off topic for this community.');

        Livewire::actingAs($author)->test(Answers::class, ['question' => $question->fresh()])
            ->assertSee('a hidden question cannot be answered')->assertDontSee('tell the next person how')->assertDontSee('Post your answer');
    }

    public function test_the_questions_list_counts_formal_answers_as_replies(): void
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker);
        $this->answer($question, $this->person('One'));
        $this->answer($question, $this->person('Two'), 'A second answer that is long enough to be accepted by the rules.');

        $comment = new Comment(['body' => 'A short reply in the thread.']);
        $comment->user_id = $asker->id;
        $question->comments()->save($comment);

        $this->actingAs($asker)->get('/questions')->assertOk()->assertSee('aria-label="3 replies"', false);
    }

    public function test_reporting_an_answer_can_be_cancelled(): void
    {
        $answer = $this->answer($this->question($this->person('Asker')), $this->person('Helper'));

        Livewire::actingAs($this->person('Reader'))->test(Answers::class, ['question' => $answer->question])
            ->call('startReport', $answer->id)->assertSee('Send report')->assertSee('Cancel')
            ->set('reporting', null)->assertDontSee('Send report');
    }

    public function test_accepting_and_removing_acceptance_tell_the_page_which_answer_changed_so_focus_can_follow_it(): void
    {
        $asker = $this->person('Asker');
        $answer = $this->answer($this->question($asker), $this->person('Helper'));

        Livewire::actingAs($asker)->test(Answers::class, ['question' => $answer->question])
            ->call('accept', $answer->id)->assertDispatched('answer-changed', id: $answer->id)
            ->call('revoke', $answer->id)->assertDispatched('answer-changed', id: $answer->id);
    }

    public function test_verifying_tells_the_page_so_the_chip_under_the_title_follows_the_rail(): void
    {
        $solution = $this->solution($this->person('Author'));

        Livewire::actingAs($this->moderator('Checker'))->test(VerifySolution::class, ['solutionId' => $solution->id])
            ->call('verify')->assertDispatched('solution-verification', label: 'Verified by moderator')
            ->set('note', 'The steps no longer match the rules.')->call('unverify')->assertDispatched('solution-verification', label: '');
    }

    public function test_the_verified_chip_is_in_the_page_only_when_the_solution_is_verified(): void
    {
        $reader = $this->person('Reader');
        $solution = $this->solution($this->person('Author'));

        $this->actingAs($reader)->get('/solution/view/'.$solution->id)->assertOk()->assertDontSee('Verified by moderator');

        app(ModerationService::class)->verify($solution, $this->moderator('Checker'));

        $this->actingAs($reader)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Verified by moderator');
    }

    public function test_the_desks_forms_keep_themselves_open_when_the_page_redraws_so_a_refusal_can_be_read(): void
    {
        $question = $this->question($this->person('Author'));
        app(AnswerService::class)->report($question, $this->person('Reporter'), 'spam');
        $key = 'q-'.$question->id;

        $html = Livewire::actingAs($this->moderator())->test(Queue::class)
            ->set('dismissNotes.'.$key, 'ok')->call('dismiss', $key)->assertHasErrors('dismissNotes.'.$key)
            ->html();

        // The refusal sits inside the form, which sits inside a <details>. Livewire must not redraw that element's own
        // attributes, or the browser closes it and the moderator never sees why nothing happened.
        $this->assertMatchesRegularExpression('/<details class="ix-mod__act" wire:ignore\.self[^>]*>\s*<summary class="ix-btn">Dismiss<\/summary>.*?at least 5 characters/s', $html);
    }

    public function test_an_appeal_outcome_note_is_shown_on_its_own_line_and_never_glued_to_the_next_sentence(): void
    {
        $author = $this->person('Author');
        $hider = $this->moderator('Hider');
        $decider = $this->moderator('Decider');
        $service = app(ModerationService::class);
        $question = $this->question($author);

        $service->hide($question, $hider, 'Off topic for this community.');
        $service->decideAppeal($service->fileAppeal($question, $author, 'It is about registering a business.'), $decider, false, 'Please add the steps you tried.');

        $this->actingAs($author)->get('/user/profile/'.$author->id)->assertOk()
            ->assertSee('Appeal denied')->assertSee("Moderator's note: Please add the steps you tried.", false)->assertDontSee('tried.. This');

        Livewire::actingAs($author)->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $question->id])
            ->assertSee('Your appeal was denied.')->assertSee('This decision is final.')->assertSee("Moderator's note: Please add the steps you tried.", false);
    }
}
