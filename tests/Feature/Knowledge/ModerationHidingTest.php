<?php

namespace Tests\Feature\Knowledge;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Livewire\Search;
use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\ModerationAction;
use App\Models\Questions;
use App\Models\Solutions;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Support\Knowledge\ContributionStats;
use App\Support\Related;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class ModerationHidingTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private ModerationService $moderation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderation = app(ModerationService::class);
    }

    private function report($item, $reporter, string $reason = 'spam'): ContentReport
    {
        return app(AnswerService::class)->report($item, $reporter, $reason);
    }

    public function test_a_moderator_hides_content_and_every_open_report_on_it_is_resolved_with_the_reason(): void
    {
        $author = $this->person();
        $mod = $this->moderator();
        $answer = $this->answer($this->question($author), $this->person());
        $first = $this->report($answer, $this->person());
        $second = $this->report($answer, $this->person(), 'harmful');

        $this->assertTrue($this->moderation->hide($answer, $mod, 'This is an advert, not an answer.'));

        $answer->refresh();
        $this->assertTrue($answer->isHidden());
        $this->assertSame($mod->id, $answer->hidden_by);
        $this->assertSame('This is an advert, not an answer.', $answer->hidden_reason);
        foreach ([$first, $second] as $report) {
            $report->refresh();
            $this->assertSame('actioned', $report->status);
            $this->assertSame($mod->id, $report->resolved_by);
            $this->assertNotNull($report->resolved_at);
            $this->assertSame('This is an advert, not an answer.', $report->resolution_note);
        }
        $this->assertSame(['hidden'], ModerationAction::pluck('action')->all());
        $this->assertSame($mod->uuid, ModerationAction::first()->actor_uuid);
    }

    public function test_a_reason_is_required_to_hide_and_a_note_to_dismiss(): void
    {
        $mod = $this->moderator();
        $answer = $this->answer($this->question($this->person()), $this->person());
        $this->report($answer, $this->person());

        foreach (['', '   ', 'no'] as $bad) {
            try {
                $this->moderation->hide($answer, $mod, $bad);
                $this->fail('hid without a reason');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('reason', $e->errors());
            }
        }

        try {
            $this->moderation->dismiss($answer, $mod, '');
            $this->fail('dismissed without a note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('note', $e->errors());
        }

        $this->assertFalse($answer->fresh()->isHidden());
        $this->assertSame(0, ModerationAction::count());
        $this->assertSame(1, ContentReport::where('status', 'open')->count());
    }

    public function test_dismissing_closes_the_reports_leaves_the_content_alone_and_is_recorded_once(): void
    {
        $mod = $this->moderator();
        $answer = $this->answer($this->question($this->person()), $this->person());
        $this->report($answer, $this->person());
        $this->report($answer, $this->person());

        $this->assertSame(2, $this->moderation->dismiss($answer, $mod, 'This is a fair answer, just short.'));
        $this->assertSame(0, $this->moderation->dismiss($answer, $mod, 'This is a fair answer, just short.'));

        $this->assertFalse($answer->fresh()->isHidden());
        $this->assertSame(2, ContentReport::where('status', 'dismissed')->count());
        $this->assertSame(['dismissed'], ModerationAction::pluck('action')->all());
    }

    public function test_restoring_shows_the_content_again_and_hiding_twice_writes_one_row(): void
    {
        $mod = $this->moderator();
        $other = $this->moderator('Other');
        $question = $this->question($this->person());

        $this->assertTrue($this->moderation->hide($question, $mod, 'Off topic for this community.'));
        $this->assertFalse($this->moderation->hide($question, $other, 'Off topic for this community.'), 'a second hide changes nothing');
        $this->assertSame(1, ModerationAction::where('action', 'hidden')->count());
        $this->assertSame($mod->id, $question->fresh()->hidden_by, 'the first moderator stays on record');

        $this->assertTrue($this->moderation->restore($question, $other, 'On reflection it is fine.'));
        $this->assertFalse($this->moderation->restore($question, $other), 'a second restore changes nothing');

        $fresh = Questions::withoutGlobalScopes()->find($question->id);
        $this->assertFalse($fresh->isHidden());
        $this->assertNull($fresh->hidden_reason);
        $this->assertSame(['hidden', 'restored'], ModerationAction::orderBy('id')->pluck('action')->all());
    }

    public function test_hiding_the_accepted_answer_removes_the_acceptance_and_asks_for_one_reversal(): void
    {
        Http::fake();
        $asker = $this->person();
        $answerer = $this->person();
        $mod = $this->moderator();
        $service = app(AnswerService::class);
        $question = $this->question($asker);
        $answer = $service->accept($this->answer($question, $answerer), $asker);
        $this->assertSame(1, (int) $question->fresh()->status);

        $this->moderation->hide($answer, $mod, 'Copied from another site without credit.');
        $this->moderation->hide($answer, $mod, 'Copied from another site without credit.');
        $this->moderation->hide($answer->fresh(), $this->moderator('Second'), 'Copied from another site without credit.');

        $fresh = Answer::withoutGlobalScopes()->find($answer->id);
        $this->assertFalse($fresh->isAccepted());
        $this->assertSame(0, (int) $question->fresh()->status, 'the question is open again');
        $this->assertSame(['hidden', 'acceptance_revoked'], ModerationAction::orderBy('id')->pluck('action')->all());

        $reversals = DB::table('reward_outbox')->where('type', 'infodot.contribution.reward_reversed')->get();
        $this->assertCount(1, $reversals, 'hiding it three times asks the ledger to reverse once');
        $payload = json_decode($reversals->first()->payload, true);
        $this->assertSame($answerer->uuid, $payload['user_uuid']);
        $this->assertSame($mod->uuid, $payload['actor_uuid']);
        $this->assertSame('infodot:answer:'.$answer->id, $payload['contribution_ref']);
        $this->assertSame('infodot.answer.accepted', $payload['facts']['rule_key']);
    }

    public function test_hiding_an_answer_that_was_never_accepted_raises_no_reversal(): void
    {
        Event::fake([AnswerAcceptanceRevoked::class]);
        $answer = $this->answer($this->question($this->person()), $this->person());

        $this->moderation->hide($answer, $this->moderator(), 'This is spam from a link farm.');

        Event::assertNotDispatched(AnswerAcceptanceRevoked::class);
        $this->assertSame(['hidden'], ModerationAction::pluck('action')->all());
    }

    public function test_restoring_does_not_bring_the_acceptance_or_the_points_back(): void
    {
        Http::fake();
        $asker = $this->person();
        $mod = $this->moderator();
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $this->person()), $asker);
        $this->moderation->hide($answer, $mod, 'Wrong, the steps are out of order.');

        $this->moderation->restore($answer, $this->moderator('Second'), null);

        $fresh = Answer::find($answer->id);
        $this->assertFalse($fresh->isHidden());
        $this->assertFalse($fresh->isAccepted());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.answer.accepted')->count(), 'restoring does not award again');
    }

    public function test_a_hidden_answer_cannot_be_accepted_and_a_hidden_question_cannot_be_answered(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $mod = $this->moderator();
        $service = app(AnswerService::class);
        $question = $this->question($asker);
        $answer = $this->answer($question, $answerer);

        $this->moderation->hide($answer, $mod, 'This is spam from a link farm.');
        $this->assertFalse($service->canAccept($answer->fresh(), $asker));

        $this->moderation->hide($question, $mod, 'Off topic for this community.');
        $this->expectException(HttpException::class);
        $service->publish($question->fresh(), $this->person(), self::GOOD.' With more detail on the tax side.');
    }

    public function test_a_moderator_cannot_hide_dismiss_restore_or_verify_their_own_content(): void
    {
        $mod = $this->moderator();
        $other = $this->moderator('Other');
        $own = $this->question($mod);
        $this->moderation->hide($own, $other, 'Off topic for this community.');
        $answer = $this->answer($this->question($this->person()), $mod);
        $solution = $this->solution($mod);

        $calls = [
            fn () => $this->moderation->hide($answer, $mod, 'I do not like my own answer.'),
            fn () => $this->moderation->dismiss($answer, $mod, 'My own answer is fine, thanks.'),
            fn () => $this->moderation->restore($own, $mod, null),
            fn () => $this->moderation->verify($solution, $mod),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('a moderator actioned their own content');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertTrue($own->fresh()->isHidden() || Questions::withoutGlobalScopes()->find($own->id)->isHidden());
        $this->assertFalse($answer->fresh()->isHidden());
    }

    public function test_only_moderators_can_moderate_and_not_content_they_cannot_see(): void
    {
        $author = $this->person();
        $answer = $this->answer($this->question($author), $this->person());
        $stranger = $this->person('Stranger');

        $this->expectException(HttpException::class);
        $this->moderation->hide($answer, $stranger, 'I do not like this one.');
    }

    public function test_a_moderator_outside_the_company_cannot_act_on_company_only_content(): void
    {
        $author = $this->person();
        $private = $this->question($author, 'company');
        $mod = $this->moderator();

        $this->assertFalse($this->moderation->canModerate($private, $mod));
        $this->expectException(HttpException::class);
        $this->moderation->hide($private, $mod, 'Looks wrong from the outside.');
    }

    public function test_the_audit_trail_is_append_only(): void
    {
        $mod = $this->moderator();
        $question = $this->question($this->person());
        $this->moderation->hide($question, $mod, 'Off topic for this community.');
        $row = ModerationAction::firstOrFail();

        try {
            $row->update(['reason' => 'rewritten']);
            $this->fail('a trail row was changed');
        } catch (LogicException) {
        }

        try {
            $row->delete();
            $this->fail('a trail row was deleted');
        } catch (LogicException) {
        }

        $this->assertSame('Off topic for this community.', $row->fresh()->reason);
        $this->assertSame(1, ModerationAction::count());
    }

    public function test_the_trail_keeps_the_actors_uuid_when_the_account_is_deleted(): void
    {
        $mod = $this->moderator();
        $this->moderation->hide($this->question($this->person()), $mod, 'Off topic for this community.');

        $mod->delete();

        $row = ModerationAction::firstOrFail();
        $this->assertNull($row->actor_id);
        $this->assertSame($mod->uuid, $row->actor_uuid);
    }

    public function test_history_lists_what_was_done_oldest_first_and_only_for_that_item(): void
    {
        $mod = $this->moderator();
        $other = $this->moderator('Other');
        $one = $this->question($this->person());
        $two = $this->question($this->person());
        $this->moderation->hide($one, $mod, 'Off topic for this community.');
        $this->moderation->restore($one, $other, 'Fine after all.');
        $this->moderation->hide($two, $mod, 'Off topic for this community.');

        $this->assertSame(['hidden', 'restored'], $this->moderation->history($one)->pluck('action')->all());
        $this->assertSame(['hidden'], $this->moderation->history($two)->pluck('action')->all());
    }

    public function test_hidden_questions_and_solutions_leave_every_list_for_everyone_but_their_author_and_moderators(): void
    {
        $author = $this->person('Author');
        $reader = $this->person('Reader');
        $mod = $this->moderator();
        $question = $this->question($author, 'public', 'How do I register a clothing brand?');
        $solution = $this->solution($author);
        $this->moderation->hide($question, $mod, 'Off topic for this community.');
        $this->moderation->hide($solution, $mod, 'Off topic for this community.');

        $this->actingAs($reader)->get('/questions')->assertOk()->assertDontSee('register a clothing brand');
        $this->actingAs($reader)->get('/solutions')->assertOk()->assertDontSee('Register a company with CIPC');
        $this->actingAs($reader)->get('/question/view/'.$question->id)->assertNotFound();
        $this->actingAs($reader)->get('/solution/view/'.$solution->id)->assertNotFound();
        $this->actingAs($reader)->get('/solution-results?search=CIPC')->assertOk()->assertDontSee('Register a company with CIPC');

        $this->actingAs($reader);
        $this->assertFalse(Questions::whereKey($question->id)->exists());
        $this->assertFalse(Solutions::whereKey($solution->id)->exists());
        $this->assertCount(0, Related::questions($this->question($reader, 'public', 'Clothing brand registration steps')));

        foreach ([$author, $mod] as $seer) {
            $this->actingAs($seer);
            $this->assertTrue(Questions::whereKey($question->id)->exists());
            $this->assertTrue(Solutions::whereKey($solution->id)->exists());
        }

        $this->actingAs($author)->get('/question/view/'.$question->id)->assertOk()->assertSee('Hidden by a moderator')->assertSee('Off topic for this community.');
        $this->actingAs($mod)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Hidden by a moderator');
    }

    public function test_the_header_search_does_not_offer_hidden_content(): void
    {
        $author = $this->person();
        $reader = $this->person();
        $question = $this->question($author, 'public', 'Zebra crossing permits');
        $this->moderation->hide($question, $this->moderator(), 'Off topic for this community.');

        Livewire::actingAs($reader)->test(Search::class)->set('query', 'Zebra')->assertDontSee('crossing permits');
        Livewire::actingAs($author)->test(Search::class)->set('query', 'Zebra')->assertSee('crossing permits');
    }

    public function test_hidden_content_leaves_the_search_index_and_comes_back_when_restored(): void
    {
        $mod = $this->moderator();
        $question = $this->question($this->person());
        $solution = $this->solution($this->person());

        $this->assertTrue($question->shouldBeSearchable());
        $this->moderation->hide($question, $mod, 'Off topic for this community.');
        $this->moderation->hide($solution, $mod, 'Off topic for this community.');
        $this->assertFalse(Questions::withoutGlobalScopes()->find($question->id)->shouldBeSearchable());
        $this->assertFalse(Solutions::withoutGlobalScopes()->find($solution->id)->shouldBeSearchable());

        $this->moderation->restore($question, $this->moderator('Other'));
        $this->assertTrue(Questions::find($question->id)->shouldBeSearchable());
    }

    public function test_a_hidden_answer_is_shown_to_its_author_and_moderators_with_a_notice_and_to_nobody_else(): void
    {
        $asker = $this->person();
        $answerer = $this->person('Answerer');
        $reader = $this->person('Reader');
        $mod = $this->moderator();
        $question = $this->question($asker);
        $answer = $this->answer($question, $answerer, 'Unique body text about customs brokers and bonded warehouses.');
        $this->moderation->hide($answer, $mod, 'Contains an advert for a paid service.');

        $this->actingAs($reader)->get('/question/view/'.$question->id)->assertOk()->assertDontSee('customs brokers')->assertSee('No one has answered yet');
        $this->actingAs($asker)->get('/question/view/'.$question->id)->assertDontSee('customs brokers');
        foreach ([$answerer, $mod] as $seer) {
            $this->actingAs($seer)->get('/question/view/'.$question->id)->assertOk()
                ->assertSee('customs brokers')->assertSee('Hidden by a moderator')->assertSee('Contains an advert for a paid service.');
        }
    }

    public function test_profile_counts_exclude_hidden_content_for_other_people(): void
    {
        $author = $this->person();
        $viewer = $this->person();
        $question = $this->question($author);
        $answer = $this->answer($this->question($viewer), $author);
        $this->moderation->hide($question, $this->moderator(), 'Off topic for this community.');
        $this->moderation->hide($answer, $this->moderator('Other'), 'This is spam from a link farm.');

        $this->assertSame(['questions' => 0, 'answers' => 0, 'accepted' => 0], ContributionStats::for($author, false));
        $this->actingAs($author);
        $this->assertSame(1, ContributionStats::for($author, true)['questions']);
    }
}
