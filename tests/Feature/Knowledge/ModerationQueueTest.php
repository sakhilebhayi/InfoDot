<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Moderation\Queue;
use App\Models\ContentReport;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class ModerationQueueTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private function report($item, $reporter, string $reason = 'spam', ?string $detail = null): ContentReport
    {
        return app(AnswerService::class)->report($item, $reporter, $reason, $detail);
    }

    public function test_guests_go_to_sign_in_members_get_403_and_moderators_get_the_page(): void
    {
        $this->get('/moderation')->assertRedirect();
        $this->assertStringContainsString('login', (string) $this->get('/moderation')->headers->get('Location'));

        $this->actingAs($this->person())->get('/moderation')->assertForbidden();
        $this->actingAs($this->moderator())->get('/moderation')->assertOk()->assertSee('Moderation')->assertSee('No open reports.');
    }

    public function test_the_queue_link_is_in_the_navigation_for_moderators_only(): void
    {
        $this->actingAs($this->person())->get('/questions')->assertOk()->assertDontSee(route('moderation'), false);
        $this->actingAs($this->moderator())->get('/questions')->assertOk()->assertSee(route('moderation'), false);
    }

    public function test_the_desk_cannot_be_mounted_by_a_member_and_every_action_is_refused_once_a_moderator_is_demoted(): void
    {
        $question = $this->question($this->person());
        $this->report($question, $this->person());
        $key = 'q-'.$question->id;

        Livewire::actingAs($this->person())->test(Queue::class)->assertForbidden();

        foreach (['dismiss', 'hide', 'restore'] as $method) {
            $mod = $this->moderator('Mod '.$method);
            $component = Livewire::actingAs($mod)->test(Queue::class)
                ->set('dismissNotes.'.$key, 'A note long enough.')->set('hideReasons.'.$key, 'A reason long enough.');
            $mod->forceFill(['is_moderator' => false])->save();
            $component->call($method, $key)->assertForbidden();
        }

        $this->assertFalse(Questions::find($question->id)->isHidden());
        $this->assertSame(1, ContentReport::where('status', 'open')->count());
        $this->assertSame(0, ModerationAction::count());
    }

    public function test_the_queue_lists_one_row_per_item_with_the_count_reasons_reporters_and_age(): void
    {
        $author = $this->person('Quiet Author');
        $answer = $this->answer($this->question($author, 'public', 'Where do I register a brand?'), $this->person('Helpful Hand'), 'Answer text about bank accounts and CIPC registration order.');
        $this->report($answer, $this->person('Reporter One'), 'spam', 'Looks like an advert');
        $this->report($answer, $this->person('Reporter Two'), 'low_quality');
        $this->report($this->question($author), $this->person('Reporter Three'), 'harmful');

        Livewire::actingAs($this->moderator())->test(Queue::class)
            ->assertSee('2 reports')->assertSee('1 report')
            ->assertSee('Answer to: Where do I register a brand?')->assertSee('Answer text about bank accounts')
            ->assertSee('Helpful Hand')->assertSee('Reporter One')->assertSee('Reporter Two')->assertSee('Reporter Three')
            ->assertSee('Looks like an advert')->assertSee('Spam')->assertSee('Low quality')->assertSee('Harmful or misleading')
            ->assertSee('2 open');
    }

    public function test_the_queue_filters_by_status_and_reason_and_pages(): void
    {
        $mod = $this->moderator();
        $spam = $this->question($this->person(), 'public', 'Cheap watches click here');
        $thin = $this->question($this->person(), 'public', 'Thin question about stock');
        $old = $this->question($this->person(), 'public', 'Already dismissed question');
        $this->report($spam, $this->person(), 'spam');
        $this->report($thin, $this->person(), 'low_quality');
        $this->report($old, $this->person(), 'other');
        app(ModerationService::class)->dismiss($old, $mod, 'It is a fine question.');

        $page = Livewire::actingAs($mod)->test(Queue::class);
        $page->assertSee('Cheap watches')->assertSee('Thin question')->assertDontSee('Already dismissed');
        $page->set('reason', 'spam')->assertSee('Cheap watches')->assertDontSee('Thin question');
        $page->set('reason', '')->set('status', 'dismissed')->assertSee('Already dismissed')->assertDontSee('Cheap watches')->assertSee('It is a fine question.');

        for ($i = 1; $i <= 12; $i++) {
            $this->report($this->question($this->person(), 'public', "Paged question number $i"), $this->person());
        }
        Livewire::actingAs($mod)->withQueryParams(['page' => 2])->test(Queue::class)->assertSee('Paged question number 12')->assertDontSee('Cheap watches');
    }

    public function test_dismissing_from_the_queue_needs_a_note_resolves_the_reports_and_leaves_the_item(): void
    {
        $mod = $this->moderator();
        $question = $this->question($this->person());
        $this->report($question, $this->person());
        $key = 'q-'.$question->id;

        Livewire::actingAs($mod)->test(Queue::class)
            ->call('dismiss', $key)->assertHasErrors('dismissNotes.'.$key)
            ->set('dismissNotes.'.$key, 'A fair question that is on topic.')->call('dismiss', $key)->assertHasNoErrors()
            ->assertSee('Dismissed.')->assertSee('No open reports.');

        $report = ContentReport::firstOrFail();
        $this->assertSame('dismissed', $report->status);
        $this->assertSame($mod->id, $report->resolved_by);
        $this->assertSame('A fair question that is on topic.', $report->resolution_note);
        $this->assertFalse(Questions::find($question->id)->isHidden());
        $this->assertSame(['dismissed'], ModerationAction::pluck('action')->all());
    }

    public function test_hiding_from_the_queue_needs_a_reason_hides_the_item_and_shows_it_under_actioned_with_restore(): void
    {
        $mod = $this->moderator();
        $question = $this->question($this->person());
        $this->report($question, $this->person());
        $key = 'q-'.$question->id;

        $page = Livewire::actingAs($mod)->test(Queue::class)
            ->call('hide', $key)->assertHasErrors('hideReasons.'.$key)
            ->set('hideReasons.'.$key, 'This is an advert for a paid service.')->call('hide', $key)->assertHasNoErrors()
            ->assertSee('Hidden. Its author can see why');

        $this->assertTrue(Questions::withoutGlobalScopes()->find($question->id)->isHidden());

        $page->set('status', 'actioned')->assertSee('Restore')->assertSee('This is an advert for a paid service.');
        $page->call('restore', $key)->assertSee('Restored.');
        $this->assertFalse(Questions::find($question->id)->isHidden());
        $this->assertSame(['hidden', 'restored'], ModerationAction::orderBy('id')->pluck('action')->all());
    }

    public function test_hiding_the_accepted_answer_from_the_queue_warns_and_reverses_once_even_if_clicked_twice(): void
    {
        Http::fake();
        $asker = $this->person();
        $mod = $this->moderator();
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $this->person()), $asker);
        $this->report($answer, $this->person());
        $key = 'a-'.$answer->id;

        $page = Livewire::actingAs($mod)->test(Queue::class)->assertSee('Accepted answer')->assertSee('takes back the points')
            ->set('hideReasons.'.$key, 'Copied from another site without credit.');
        $page->call('hide', $key);
        $page->call('hide', $key);

        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.contribution.reward_reversed')->count());
        $this->assertSame(1, ModerationAction::where('action', 'acceptance_revoked')->count());
        $this->assertSame(1, ModerationAction::where('action', 'hidden')->count());
    }

    public function test_a_moderator_cannot_action_a_report_on_their_own_content_and_is_told_why(): void
    {
        $mod = $this->moderator();
        $mine = $this->question($mod);
        $this->report($mine, $this->person());
        $key = 'q-'.$mine->id;

        Livewire::actingAs($mod)->test(Queue::class)->assertSee('This is your own content, so another moderator has to handle it.')->assertDontSee('Dismiss reports');
        Livewire::actingAs($mod)->test(Queue::class)->set('dismissNotes.'.$key, 'It is fine, honestly.')->call('dismiss', $key)->assertForbidden();
        Livewire::actingAs($mod)->test(Queue::class)->set('hideReasons.'.$key, 'I would rather it was gone.')->call('hide', $key)->assertForbidden();

        $this->assertFalse(Questions::find($mine->id)->isHidden());
        $this->assertSame(1, ContentReport::where('status', 'open')->count());
    }

    public function test_company_only_content_of_another_company_is_never_shown_in_the_queue(): void
    {
        $private = $this->question($this->person(), 'company', 'Secret supplier pricing plan');
        // A teammate of the author reported it; this moderator belongs to a different company.
        DB::table('content_reports')->insert([
            'reportable_type' => $private->getMorphClass(), 'reportable_id' => $private->id, 'reporter_id' => $this->person()->id,
            'reason' => 'spam', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::actingAs($this->moderator())->test(Queue::class)
            ->assertDontSee('Secret supplier pricing plan')->assertSee('private to another company')->assertDontSee('Dismiss reports');
    }

    public function test_appeals_tab_lists_open_appeals_and_a_different_moderator_decides_them(): void
    {
        $author = $this->person('Author');
        $hider = $this->moderator('Hider');
        $judge = $this->moderator('Judge');
        $question = $this->question($author, 'public', 'The appealed question');
        $moderation = app(ModerationService::class);
        $moderation->hide($question, $hider, 'Off topic for this community.');
        $appeal = $moderation->fileAppeal($question, $author, 'It is about registering a business, honestly.');

        // The moderator who hid it sees it but may not decide it.
        Livewire::actingAs($hider)->withQueryParams(['tab' => 'appeals'])->test(Queue::class)
            ->assertSee('The appealed question')->assertSee('It is about registering a business, honestly.')
            ->assertSee('You hid this, so a different moderator has to decide the appeal.')->assertDontSee('Deny the appeal')
            ->call('uphold', $appeal->id)->assertForbidden();

        $page = Livewire::actingAs($judge)->withQueryParams(['tab' => 'appeals'])->test(Queue::class)
            ->assertSee('Hidden by')->assertSee('Hider')->assertSee('Deny the appeal')
            ->call('deny', $appeal->id)->assertHasErrors('appealNotes.'.$appeal->id)
            ->set('appealNotes.'.$appeal->id, 'The question is general chat.')->call('deny', $appeal->id)->assertSee('Appeal denied.');

        $this->assertSame('denied', $appeal->fresh()->status);
        $page->set('status', 'denied')->assertSee('Denied by Judge')->assertSee('The question is general chat.');
        $this->assertSame(['hidden', 'appeal_denied'], ModerationAction::orderBy('id')->pluck('action')->all());
    }

    public function test_upholding_from_the_appeals_tab_restores_the_content(): void
    {
        $author = $this->person();
        $question = $this->question($author);
        $moderation = app(ModerationService::class);
        $moderation->hide($question, $this->moderator('Hider'), 'Off topic for this community.');
        $appeal = $moderation->fileAppeal($question, $author, 'It is about registering a business, honestly.');

        Livewire::actingAs($this->moderator('Judge'))->withQueryParams(['tab' => 'appeals'])->test(Queue::class)
            ->call('uphold', $appeal->id)->assertSee('Appeal upheld.')->assertSee('No appeals are waiting.');

        $this->assertFalse(Questions::find($question->id)->isHidden());
        $this->assertSame('upheld', ModerationAppeal::first()->status);
    }

    public function test_each_row_shows_the_items_history_to_moderators(): void
    {
        $mod = $this->moderator('Hider');
        $question = $this->question($this->person());
        $moderation = app(ModerationService::class);
        $moderation->hide($question, $mod, 'Off topic for this community.');
        $moderation->restore($question, $this->moderator('Restorer'), 'Fine after all.');
        $this->report($question, $this->person(), 'other');

        Livewire::actingAs($this->moderator('Viewer'))->test(Queue::class)
            ->assertSee('History (2)')->assertSee('Hidden')->assertSee('Restored')->assertSee('Restorer')->assertSee('Fine after all.');
    }
}
