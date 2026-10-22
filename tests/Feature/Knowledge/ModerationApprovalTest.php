<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Moderation\Queue;
use App\Models\ModerationAction;
use App\Models\Questions;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** A moderator's approval is the approval: the moderator desk lists published questions that have none yet. */
class ModerationApprovalTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    public function test_the_desk_has_an_awaiting_approval_tab_that_lists_only_eligible_questions_with_excerpts(): void
    {
        $mod = $this->moderator();
        $eligible = $this->question($this->person('Asker'), 'public', 'How do I price a handmade product?');
        $approved = $this->question($this->person(), 'public', 'Already approved question');
        app(ModerationService::class)->approve($approved, $this->moderator('Other'));
        $hidden = $this->question($this->person(), 'public', 'Hidden question');
        app(ModerationService::class)->hide($hidden, $this->moderator('Hider'), 'Hidden for a test.');
        $this->question($this->person(), 'company', 'Company only question');
        $this->question($mod, 'public', 'My own question');

        Livewire::actingAs($mod)->withQueryParams(['tab' => 'approvals'])->test(Queue::class)
            ->assertSee('How do I price a handmade product?')
            ->assertSee('I have never done this before')
            ->assertSee('Asker')
            ->assertSee('Approve for reward')
            ->assertSee('1 waiting')
            ->assertDontSee('Already approved question')
            ->assertDontSee('Hidden question')
            ->assertDontSee('Company only question')
            ->assertDontSee('My own question');

        $this->assertSame([$eligible->id], app(ModerationService::class)->awaitingApproval($mod)->pluck('id')->all());
    }

    public function test_the_tab_says_so_when_nothing_is_waiting_and_is_paginated(): void
    {
        $mod = $this->moderator();
        Livewire::actingAs($mod)->withQueryParams(['tab' => 'approvals'])->test(Queue::class)->assertSee('No questions are waiting for approval.');

        $asker = $this->person();
        foreach (range(1, 12) as $n) {
            $this->question($asker, 'public', 'Question number '.$n.' about starting up');
        }

        $this->actingAs($mod)->get('/moderation?tab=approvals')->assertOk()
            ->assertSee('Question number 1 about')->assertDontSee('Question number 12 about')
            ->assertSee('12 waiting')->assertSee('aria-current="page"', false);
        $this->actingAs($mod)->get('/moderation?tab=approvals&page=2')->assertOk()->assertSee('Question number 12 about');
    }

    public function test_a_moderator_approves_from_the_desk_once_and_it_is_written_to_the_trail(): void
    {
        $mod = $this->moderator();
        $question = $this->question($this->person());
        $key = Queue::keyFor($question);

        Livewire::actingAs($mod)->test(Queue::class)->set('tab', 'approvals')->call('approve', $key)
            ->assertSee('Approved.')->assertSee('No questions are waiting for approval.');
        Livewire::actingAs($this->moderator('Late'))->test(Queue::class)->set('tab', 'approvals')->call('approve', $key)
            ->assertSee('already approved');

        $rows = ModerationAction::where('action', 'approved')->get();
        $this->assertCount(1, $rows, 'one approval per item');
        $this->assertSame($mod->id, $rows[0]->actor_id);
        $this->assertSame($mod->uuid, $rows[0]->actor_uuid);
        $this->assertSame((new Questions)->getMorphClass(), $rows[0]->subject_type);
        $this->assertSame($question->id, (int) $rows[0]->subject_id);
        $this->assertSame('Approved for reward', $rows[0]->label());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.contribution.approved')->count());
    }

    public function test_the_author_is_refused_with_403_even_as_a_moderator_and_never_sees_the_button(): void
    {
        $authorMod = $this->moderator('Author mod');
        $mine = $this->question($authorMod);

        Livewire::actingAs($authorMod)->test(Queue::class)->set('tab', 'approvals')->call('approve', Queue::keyFor($mine))->assertForbidden();
        Livewire::actingAs($authorMod)->withQueryParams(['tab' => 'approvals'])->test(Queue::class)->assertDontSee('Approve for reward')->assertSee('0 waiting');

        try {
            app(ModerationService::class)->approve($mine, $authorMod);
            $this->fail('the author approved their own question');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(0, ModerationAction::count());
        $this->assertSame(0, DB::table('reward_outbox')->where('type', 'infodot.contribution.approved')->count());
    }

    public function test_members_and_demoted_moderators_are_refused(): void
    {
        $question = $this->question($this->person());
        $key = Queue::keyFor($question);

        Livewire::actingAs($this->person())->test(Queue::class)->assertForbidden();
        $this->actingAs($this->person())->get('/moderation?tab=approvals')->assertForbidden();

        $mod = $this->moderator('Soon demoted');
        $component = Livewire::actingAs($mod)->test(Queue::class)->set('tab', 'approvals');
        $mod->forceFill(['is_moderator' => false])->save();
        $component->call('approve', $key)->assertForbidden();

        try {
            app(ModerationService::class)->approve($question, $this->person());
            $this->fail('a member approved');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(0, ModerationAction::count());
    }

    public function test_hidden_and_company_questions_and_other_kinds_of_content_cannot_be_approved(): void
    {
        $mod = $this->moderator();
        $service = app(ModerationService::class);
        $hidden = $this->question($this->person());
        $service->hide($hidden, $this->moderator('Hider'), 'Hidden for a test.');
        $company = $this->question($author = $this->person(), 'company');
        $author->currentTeam->users()->attach($mod, ['role' => 'editor']);
        $mod->forceFill(['current_team_id' => $author->current_team_id])->save();

        foreach ([$hidden, $company] as $question) {
            try {
                $service->approve($question, $mod->fresh());
                $this->fail('approved something that is not eligible');
            } catch (ValidationException) {
            }
        }

        $answer = $this->answer($this->question($this->person()), $this->person());
        Livewire::actingAs($mod)->test(Queue::class)->call('approve', Queue::keyFor($answer))->assertNotFound();
        $this->assertSame(0, ModerationAction::where('action', 'approved')->count());
    }

    public function test_the_trail_stays_append_only(): void
    {
        $this->expectException(\LogicException::class);

        app(ModerationService::class)->approve($this->question($this->person()), $this->moderator());
        ModerationAction::where('action', 'approved')->first()->delete();
    }
}
