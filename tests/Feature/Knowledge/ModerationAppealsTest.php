<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\HiddenNotice;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\User;
use App\Services\Knowledge\ModerationService;
use App\Support\Knowledge\ModerationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class ModerationAppealsTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private ModerationService $moderation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderation = app(ModerationService::class);
    }

    /** @return array{0: User, 1: User, 2: Questions} author, hider, hidden question */
    private function hidden(): array
    {
        $author = $this->person('Author');
        $hider = $this->moderator('Hider');
        $question = $this->question($author);
        $this->moderation->hide($question, $hider, 'Off topic for this community.');

        return [$author, $hider, $question];
    }

    public function test_the_author_appeals_once_and_the_appeal_waits_for_a_moderator(): void
    {
        [$author, $hider, $question] = $this->hidden();

        $appeal = $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');

        $this->assertSame('open', $appeal->status);
        $this->assertSame($author->id, $appeal->appellant_id);
        $this->assertSame($hider->id, $appeal->hidden_by);
        $this->assertSame(ModerationAction::where('action', 'hidden')->value('id'), $appeal->hide_action_id);

        try {
            $this->moderation->fileAppeal($question, $author, 'Please look at it again, I think you were wrong.');
            $this->fail('a second appeal was accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already appealed', $e->errors()['appeal'][0]);
        }
        $this->assertSame(1, ModerationAppeal::count());
    }

    public function test_only_the_author_can_appeal_and_only_hidden_content_and_with_a_text(): void
    {
        [$author, , $question] = $this->hidden();
        $visible = $this->question($author, 'public', 'Another question that stays visible');

        try {
            $this->moderation->fileAppeal($question, $this->person('Stranger'), 'I think this should be shown again.');
            $this->fail('a stranger appealed');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        try {
            $this->moderation->fileAppeal($visible, $author, 'Nothing is hidden, but I am appealing anyway.');
            $this->fail('appealed visible content');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('appeal', $e->errors());
        }

        try {
            $this->moderation->fileAppeal($question, $author, '  ');
            $this->fail('appealed without a text');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('appeal', $e->errors());
        }

        $this->assertSame(0, ModerationAppeal::count());
    }

    public function test_the_moderator_who_hid_it_cannot_decide_the_appeal_nor_can_the_author(): void
    {
        [$author, $hider, $question] = $this->hidden();
        $appeal = $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');
        $authorAsModerator = $author->forceFill(['is_moderator' => true]);

        $this->assertFalse($this->moderation->canDecide($appeal, $hider));
        $this->assertFalse($this->moderation->canDecide($appeal, $authorAsModerator));
        $this->assertFalse($this->moderation->canDecide($appeal, $this->person('Not a moderator')));
        $this->assertTrue($this->moderation->canDecide($appeal, $this->moderator('Second')));

        foreach ([$hider, $authorAsModerator, $this->person('Plain')] as $who) {
            try {
                $this->moderation->decideAppeal($appeal, $who, true, null);
                $this->fail('the wrong person decided the appeal');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame('open', $appeal->fresh()->status);
        $this->assertTrue(Questions::withoutGlobalScopes()->find($question->id)->isHidden());
    }

    public function test_a_different_moderator_upholds_and_the_content_is_visible_again(): void
    {
        [$author, , $question] = $this->hidden();
        $appeal = $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');
        $judge = $this->moderator('Judge');

        $decided = $this->moderation->decideAppeal($appeal, $judge, true, 'You are right, sorry.');

        $this->assertSame('upheld', $decided->status);
        $this->assertSame($judge->id, $decided->decided_by);
        $this->assertNotNull($decided->decided_at);
        $fresh = Questions::withoutGlobalScopes()->find($question->id);
        $this->assertFalse($fresh->isHidden());
        $this->assertNull($fresh->hidden_reason);
        $this->assertSame(['hidden', 'appeal_upheld'], ModerationAction::orderBy('id')->pluck('action')->all());
        $this->assertSame($appeal->id, ModerationAction::where('action', 'appeal_upheld')->value('appeal_id'));
    }

    public function test_a_denial_needs_a_note_keeps_it_hidden_and_is_final(): void
    {
        [$author, , $question] = $this->hidden();
        $appeal = $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');
        $judge = $this->moderator('Judge');

        try {
            $this->moderation->decideAppeal($appeal, $judge, false, '');
            $this->fail('denied without a note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }
        $this->assertSame('open', $appeal->fresh()->status);

        $this->moderation->decideAppeal($appeal, $judge, false, 'It is a general chat question, not about running a business.');

        $this->assertSame('denied', $appeal->fresh()->status);
        $this->assertTrue(Questions::withoutGlobalScopes()->find($question->id)->isHidden());
        $this->assertSame(['hidden', 'appeal_denied'], ModerationAction::orderBy('id')->pluck('action')->all());

        // Final: it cannot be decided again, and the author cannot appeal again.
        try {
            $this->moderation->decideAppeal($appeal, $this->moderator('Third'), true, 'Let us show it.');
            $this->fail('a decided appeal was decided again');
        } catch (ValidationException) {
        }

        try {
            $this->moderation->fileAppeal($question, $author, 'I still think you are wrong about this.');
            $this->fail('re-appealed after a decision');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already appealed', $e->errors()['appeal'][0]);
        }

        $this->assertSame('denied', $appeal->fresh()->status);
        $this->assertSame(2, ModerationAction::count());
    }

    public function test_deciding_twice_changes_nothing_the_second_time(): void
    {
        [$author, , $question] = $this->hidden();
        $appeal = $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');
        $judge = $this->moderator('Judge');
        $this->moderation->decideAppeal($appeal, $judge, true, null);

        try {
            $this->moderation->decideAppeal($appeal, $judge, true, null);
            $this->fail('decided twice');
        } catch (ValidationException) {
        }

        $this->assertSame(1, ModerationAction::where('action', 'appeal_upheld')->count());
    }

    public function test_restoring_directly_closes_an_open_appeal_as_upheld(): void
    {
        [$author, , $question] = $this->hidden();
        $appeal = $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');

        $this->moderation->restore($question, $this->moderator('Other'), null);

        $this->assertSame('upheld', $appeal->fresh()->status);
        $this->assertStringContainsString('restored', $appeal->fresh()->decision_note);
    }

    public function test_hiding_again_after_a_restore_can_be_appealed_once_more(): void
    {
        [$author, $hider, $question] = $this->hidden();
        $this->moderation->fileAppeal($question, $author, 'It is about registering a business, which is on topic.');
        $this->moderation->restore($question, $this->moderator('Other'), null);

        $this->moderation->hide($question, $hider, 'Reported again, and it is still off topic.');

        $this->assertSame(0, ModerationAppeal::where('status', 'open')->count(), 'the first appeal was closed by the restore');
        $second = $this->moderation->fileAppeal($question, $author, 'This is a new decision, so I am appealing it.');
        $this->assertSame('open', $second->status);
        $this->assertSame(2, ModerationAppeal::count());
    }

    public function test_the_author_sees_why_and_can_appeal_from_the_item_page_and_then_sees_the_outcome(): void
    {
        [$author, , $question] = $this->hidden();

        $this->actingAs($author)->get('/question/view/'.$question->id)->assertOk()
            ->assertSee('Hidden by a moderator')->assertSee('Off topic for this community.')->assertSee('Appeal this decision');

        Livewire::actingAs($author)->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $question->id])
            ->set('appealText', 'x')->call('fileAppeal')->assertHasErrors('appealText')
            ->set('appealText', 'It is about registering a business, which is on topic.')->call('fileAppeal')->assertHasNoErrors()
            ->assertSee('Your appeal is with the moderators')->assertDontSee('Send appeal');

        $appeal = ModerationAppeal::firstOrFail();
        $this->moderation->decideAppeal($appeal, $this->moderator('Judge'), false, 'A general chat question, not about business.');

        Livewire::actingAs($author)->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $question->id])
            ->assertSee('Your appeal was denied')->assertSee('A general chat question, not about business.')->assertSee('This decision is final');
    }

    public function test_the_notice_is_empty_for_everyone_who_is_not_the_author_or_a_moderator(): void
    {
        [, , $question] = $this->hidden();
        $visible = $this->question($this->person(), 'public', 'A visible question about stock');

        Livewire::actingAs($this->person('Reader'))->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $visible->id])
            ->assertDontSee('Hidden by a moderator');
        $this->actingAs($this->person('Reader2'));
        $this->assertSame(404, $this->get('/question/view/'.$question->id)->getStatusCode());
    }

    public function test_a_moderator_can_restore_from_the_notice_but_not_their_own_content(): void
    {
        [$author, , $question] = $this->hidden();
        $other = $this->moderator('Other');

        Livewire::actingAs($other)->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $question->id])
            ->assertSee('Restore it')->call('restore')->assertRedirect(route('questions.view', ['qid' => $question->id]));
        $this->assertFalse(Questions::withoutGlobalScopes()->find($question->id)->isHidden());

        $mine = $this->question($other, 'public', 'A moderator question');
        $this->moderation->hide($mine, $this->moderator('Third'), 'Off topic for this community.');
        Livewire::actingAs($other)->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $mine->id])
            ->assertSee('Appeal this decision')->assertDontSee('Restore it')->call('restore')->assertForbidden();
    }

    public function test_the_profile_shows_the_owner_what_is_hidden_and_where_each_appeal_stands(): void
    {
        [$author, , $question] = $this->hidden();
        $other = $this->question($author, 'public', 'Second question that gets restored');
        $this->moderation->hide($other, $this->moderator('Hider2'), 'Duplicate of another question.');
        $appeal = $this->moderation->fileAppeal($other, $author, 'It asks about a different permit than the other one.');
        $this->moderation->decideAppeal($appeal, $this->moderator('Judge'), true, null);

        $rows = ModerationStatus::for($author);
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->firstWhere('hidden', true)['hidden']);

        $page = $this->actingAs($author)->get('/user/profile/'.$author->id)->assertOk();
        $page->assertSee('Moderation of your content')->assertSee('Off topic for this community.')->assertSee('No appeal sent yet.')->assertSee('Appeal upheld');

        $viewer = $this->person('Teammate');
        $author->currentTeam->users()->attach($viewer, ['role' => 'editor']);
        $this->actingAs($viewer)->get('/user/profile/'.$author->id)->assertOk()->assertDontSee('Moderation of your content')->assertDontSee('Off topic for this community.');
    }
}
