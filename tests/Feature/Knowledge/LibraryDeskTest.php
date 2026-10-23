<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Moderation\Library;
use App\Models\LibraryEntry;
use App\Models\LibraryOutcome;
use App\Models\ModerationAction;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Library\LibraryOutcomeOutbox;
use App\Services\Library\LibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/** The moderators' "Recommended" tab: what Brain suggests next to the real content, and the decisions made from it. */
class LibraryDeskTest extends TestCase
{
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'knowledge.memory_url' => 'https://memory.test', 'knowledge.infodot_secret' => 'infodot-secret-for-tests',
            'services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'dopemine-secret',
        ]);
    }

    /** Replace every earlier stub: Http::fake() adds to them, and the first match wins. */
    private function memory(mixed $items = [], int $status = 200, array $extra = ['next_cursor' => null]): void
    {
        Http::swap(new Factory);
        Http::fake(fn (Request $request) => str_contains($request->url(), 'memory.test')
            ? ($request->method() === 'GET' ? ($items instanceof \Closure ? $items() : Http::response(['items' => $items] + $extra, $status)) : Http::response(['ok' => true], 200))
            : Http::response(['status' => 'awarded'], 202));
    }

    /** @return array<string, mixed> */
    private function rec(string $id, string $ref, array $over = []): array
    {
        return $over + [
            'decision_event_id' => $id, 'contribution_ref' => $ref, 'verdict' => 'recommend_for_library', 'confidence' => 0.82,
            'rationale' => [
                ['rule' => 'independent_acceptance', 'effect' => '+0.40', 'detail' => 'Accepted by someone other than the author.'],
                ['rule' => 'reuse_signal', 'effect' => '+0.10', 'detail' => 'Two more people marked it helpful.'],
            ],
            'rule_version' => 'v3', 'decided_at' => now()->subHour()->toIso8601String(), 'status' => 'open', 'outcome_at' => null,
        ];
    }

    private function acceptedAnswer(string $questionTitle = 'How do I register a clothing brand?', string $body = 'Register with CIPC first, then open a business bank account, then ask suppliers for written terms.'): array
    {
        $asker = $this->person('Asker Person');
        $author = $this->person('Answer Author');
        $question = $this->file($this->question($asker, 'public', $questionTitle), 'getting-started');
        $answer = app(AnswerService::class)->accept($this->answer($question, $author, $body), $asker);

        return [$answer->fresh(), $author, $asker];
    }

    private function verifiedSolution(string $title = 'Register a company with CIPC'): Solutions
    {
        $solution = $this->file($this->solution($this->person('Solution Writer'), 'public', $title), 'integrations');
        app(ModerationService::class)->verify($solution, $this->moderator('Verifier Person'));

        return $solution->fresh();
    }

    private function desk(User $mod)
    {
        return Livewire::actingAs($mod)->test(Library::class);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------------------------------------------------------

    public function test_guests_go_to_sign_in_members_get_403_and_moderators_get_the_desk(): void
    {
        $this->memory();

        $this->get('/moderation/library')->assertRedirect();
        $this->actingAs($this->person())->get('/moderation/library')->assertForbidden();
        $this->actingAs($this->moderator())->get('/moderation/library')->assertOk()->assertSee('Verified library')->assertSee('Recommended by Dot.Brain');
    }

    public function test_the_recommended_tab_is_in_the_moderation_navigation(): void
    {
        $this->memory();
        $mod = $this->moderator();

        $this->actingAs($mod)->get('/moderation')->assertOk()->assertSee(route('moderation.library'), false)->assertSee('Recommended');
        $this->actingAs($mod)->get('/moderation/topics')->assertOk()->assertSee(route('moderation.library'), false);
    }

    public function test_a_member_cannot_mount_the_desk_and_a_demoted_moderator_loses_every_action_at_once(): void
    {
        $this->memory();
        Livewire::actingAs($this->person())->test(Library::class)->assertForbidden();

        [$answer] = $this->acceptedAnswer();
        $mod = $this->moderator();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $component = $this->desk($mod);
        $mod->forceFill(['is_moderator' => false])->save();

        $component->call('adopt', 'dec-1')->assertForbidden();
        $this->assertSame(0, LibraryEntry::count());
        $this->assertSame(0, LibraryOutcome::count());
    }

    // ---------------------------------------------------------------------------------------------------------------
    // What is shown
    // ---------------------------------------------------------------------------------------------------------------

    public function test_a_recommendation_is_shown_with_the_real_content_topics_corroboration_and_plain_reasons(): void
    {
        [$answer, $author, $asker] = $this->acceptedAnswer();
        $solution = $this->verifiedSolution();
        $this->memory([$this->rec('dec-a', 'infodot:answer:'.$answer->id), $this->rec('dec-s', 'infodot:solution:'.$solution->id, ['confidence' => 'high'])]);

        $this->desk($this->moderator('Curator'))
            ->assertSee('Answer to: How do I register a clothing brand?')
            ->assertSee('Register with CIPC first, then open a business bank account')
            ->assertSee('Getting started')
            ->assertSee('Accepted by Asker Person')
            ->assertSee('Answer Author')
            ->assertSee('independent_acceptance')
            ->assertSee('+0.40')
            ->assertSee('Accepted by someone other than the author.')
            ->assertSee('Two more people marked it helpful.')
            ->assertSee('Confidence 82%')
            ->assertSee('Rules version v3')
            ->assertSee(route('questions.view', ['qid' => $answer->question_id]).'#answer-'.$answer->id, false)
            ->assertSee('Register a company with CIPC')
            ->assertSee('Integrations')
            ->assertSee('Verified by Verifier Person')
            ->assertSee('Confidence high')
            ->assertSee(route('solutions.view', ['id' => $solution->id]), false)
            ->assertSee('Adopt into library')
            ->assertSee('Decline');
    }

    public function test_it_asks_memory_for_open_library_recommendations_with_a_signed_request(): void
    {
        $this->memory();

        $this->desk($this->moderator());

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_contains($request->url(), 'memory.test/api/knowledge-recommendations')
                && $query['status'] === 'open' && $query['verdict'] === 'recommend_for_library'
                && $request->hasHeader('X-InfoDot-Signature') && $request->hasHeader('X-InfoDot-Timestamp');
        });
    }

    public function test_when_memory_is_unreachable_the_page_says_so_and_shows_nothing_made_up(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory(fn () => throw new ConnectionException('timeout'));

        $this->desk($this->moderator())
            ->assertSee('Recommendations are unavailable right now')
            ->assertDontSee('Adopt into library')
            ->assertDontSee('Answer to:')
            ->assertDontSee('No open recommendations')
            ->assertDontSee('has no open recommendations');
    }

    public function test_every_kind_of_failure_gets_the_honest_state(): void
    {
        foreach ([
            'server error' => [500, ['code' => 'x', 'message' => 'x']],
            'bad signature' => [401, ['code' => 'invalid_signature', 'message' => 'x']],
            'rubbish' => [200, ['unexpected' => true]],
        ] as $name => [$status, $body]) {
            Http::swap(new Factory);
            Http::fake(['memory.test/*' => Http::response($body, $status)]);

            $this->desk($this->moderator())->assertSee('Recommendations are unavailable right now')->assertDontSee('Adopt into library');
        }
    }

    public function test_when_memory_is_not_configured_the_page_says_so_and_sends_nothing(): void
    {
        config(['knowledge.infodot_secret' => null]);
        Http::swap(new Factory);
        Http::fake();

        $this->desk($this->moderator())->assertSee('Recommendations are unavailable right now')->assertSee('Dot.Memory is not connected');

        Http::assertNothingSent();
    }

    public function test_an_empty_list_is_said_plainly(): void
    {
        $this->memory([]);

        $this->desk($this->moderator())->assertSee('no open recommendations')->assertDontSee('Recommendations are unavailable');
    }

    public function test_items_from_memory_that_break_the_contract_are_counted_and_left_out(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id), $this->rec('dec-q', 'infodot:question:4'), ['nonsense' => 1]]);

        $this->desk($this->moderator())->assertSee('Adopt into library')->assertSee('2 items from Dot.Memory could not be understood');
    }

    public function test_company_only_content_is_never_shown_even_if_memory_names_it(): void
    {
        $asker = $this->person('Company Asker');
        $author = $this->person('Company Author');
        $asker->currentTeam->users()->attach($author->id, ['role' => 'editor']);
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker, 'company', 'A secret company question?'), $author, 'A secret company answer that must stay private.'), $asker);
        $this->memory([$this->rec('dec-c', 'infodot:answer:'.$answer->id)]);

        $this->desk($this->moderator())
            ->assertDontSee('secret company')
            ->assertSee('private to it')
            ->assertDontSee('Adopt into library')
            ->assertSee('Decline');
    }

    public function test_an_item_that_cannot_be_adopted_says_why_and_offers_no_adopt_button(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $desk = $this->desk($this->moderator());
        $desk->assertSee('Adopt into library');

        DB::table('answers')->where('id', $answer->id)->update(['hidden_at' => now()]);

        $this->desk($this->moderator())->assertDontSee('Adopt into library')->assertSee('It cannot be adopted now')->assertSee('Hidden');
    }

    public function test_your_own_content_is_listed_but_another_moderator_must_decide(): void
    {
        $authorMod = $this->moderator('Author Moderator');
        $asker = $this->person('Asker');
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $authorMod), $asker);
        $this->memory([$this->rec('dec-own', 'infodot:answer:'.$answer->id)]);

        $this->desk($authorMod)->assertSee('This is your own content, so another moderator has to decide')->assertDontSee('Adopt into library');
        $this->desk($this->moderator('Someone else'))->assertSee('Adopt into library');
    }

    public function test_a_recommendation_already_decided_here_is_not_offered_again_even_if_memory_still_says_open(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $mod = $this->moderator('Curator');
        $this->desk($mod)->call('decline', 'dec-1');

        $this->desk($mod)->assertDontSee('Adopt into library')->assertDontSee('Answer to:');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Deciding
    // ---------------------------------------------------------------------------------------------------------------

    public function test_adopting_from_the_desk_writes_the_entry_the_audit_row_the_ledger_event_and_queues_the_outcome(): void
    {
        [$answer, $author] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $mod = $this->moderator('Curator');

        $this->desk($mod)->call('adopt', 'dec-1')->assertSee('Adopted into the library');

        $entry = LibraryEntry::sole();
        $this->assertSame('dec-1', $entry->recommendation_event_id);
        $this->assertSame($mod->id, $entry->adopted_by);
        $this->assertSame(1, ModerationAction::where('action', 'library_adopted')->count());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.knowledge.adopted')->count());
        $outcome = LibraryOutcome::sole();
        $this->assertSame(['dec-1', 'adopted', $mod->uuid], [$outcome->decision_event_id, $outcome->outcome, $outcome->actor_ref]);

        // The scheduled flush then tells Memory.
        app(LibraryOutcomeOutbox::class)->flush();
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/knowledge-recommendations/dec-1/outcome') && $r->data() === ['outcome' => 'adopted', 'actor_ref' => $mod->uuid]);
        $this->assertNotNull($outcome->fresh()->sent_at);
    }

    public function test_clicking_adopt_twice_changes_nothing_the_second_time(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $desk = $this->desk($this->moderator('Curator'));

        $desk->call('adopt', 'dec-1')->call('adopt', 'dec-1')->assertSee('already decided');

        $this->assertSame(1, LibraryEntry::count());
        $this->assertSame(1, ModerationAction::where('action', 'library_adopted')->count());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.knowledge.adopted')->count());
        $this->assertSame(1, LibraryOutcome::count());
    }

    public function test_declining_records_it_without_asking_for_a_reason(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $mod = $this->moderator('Curator');

        $this->desk($mod)->call('decline', 'dec-1')->assertSee('Declined')->assertHasNoErrors();

        $this->assertSame(0, LibraryEntry::count());
        $this->assertSame('declined', LibraryOutcome::sole()->outcome);
        $audit = ModerationAction::where('action', 'library_declined')->sole();
        $this->assertSame($mod->id, $audit->actor_id);
        $this->assertNull($audit->report_id);
        $this->assertSame(0, DB::table('reward_outbox')->where('type', 'infodot.knowledge.adopted')->count());
    }

    public function test_the_item_is_checked_again_at_the_moment_of_the_click(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $desk = $this->desk($this->moderator('Curator'));
        $desk->assertSee('Adopt into library');

        // Hidden after the page was drawn, before the click.
        DB::table('answers')->where('id', $answer->id)->update(['hidden_at' => now()]);
        $desk->call('adopt', 'dec-1')->assertHasErrors(['adopt.dec-1']);

        $this->assertSame(0, LibraryEntry::count());
        $this->assertSame(0, LibraryOutcome::count(), 'a refused adoption reports nothing to Memory');
        $this->assertSame(0, ModerationAction::where('action', 'library_adopted')->count());
        $this->assertSame(0, DB::table('reward_outbox')->where('type', 'infodot.knowledge.adopted')->count());
    }

    public function test_a_decision_still_works_and_is_kept_when_memory_goes_down_after_the_page_was_drawn(): void
    {
        [$answer] = $this->acceptedAnswer();
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$answer->id)]);
        $mod = $this->moderator('Curator');
        $desk = $this->desk($mod);

        Http::swap(new Factory);
        Http::fake(['memory.test/*' => fn () => throw new ConnectionException('down'), '*' => Http::response(['status' => 'awarded'], 202)]);
        $desk->call('adopt', 'dec-1');

        $this->assertSame(1, LibraryEntry::count());
        $this->assertNull(LibraryOutcome::sole()->sent_at);
        $desk->assertSee('Recommendations are unavailable right now')->assertSee('1 decision is saved here and still on the way to Dot.Memory');
    }

    public function test_a_browser_cannot_decide_a_different_item_than_the_one_on_screen(): void
    {
        [$shown] = $this->acceptedAnswer();
        [$other] = $this->acceptedAnswer('Another question entirely?');
        $this->memory([$this->rec('dec-1', 'infodot:answer:'.$shown->id)]);
        $desk = $this->desk($this->moderator('Curator'));

        try {
            $desk->set('refs', ['dec-1' => 'infodot:answer:'.$other->id]);
            $this->fail('the locked map was changed from the browser');
        } catch (CannotUpdateLockedPropertyException) {
            $this->addToAssertionCount(1);
        }

        $this->desk($this->moderator('Another'))->call('adopt', 'dec-never-shown')->assertNotFound();
        $this->desk($this->moderator('Yet another'))->call('decline', 'dec-never-shown')->assertNotFound();
        $this->assertSame(0, LibraryEntry::count());
    }

    public function test_a_second_page_of_recommendations_is_read_with_memorys_cursor(): void
    {
        $this->memory([], 200, ['next_cursor' => 'cur-2']);
        $desk = $this->desk($this->moderator());
        $desk->assertSee('Next recommendations');

        $desk->call('nextRecommendations', 'cur-2');

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), 'cursor=cur-2'));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Entries and withdrawing
    // ---------------------------------------------------------------------------------------------------------------

    public function test_entries_are_listed_and_a_moderator_withdraws_one_with_a_reason(): void
    {
        $this->memory([]);
        [$answer, $author] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');
        $entry = app(LibraryService::class)->adopt($answer, $mod)['entry'];
        $withdrawer = $this->moderator('Withdrawer');

        $desk = $this->desk($withdrawer)->assertSee('In the library')->assertSee('Answer to: How do I register a clothing brand?')->assertSee('adopted')->assertSee('Curator');

        $desk->call('withdraw', $entry->id)->assertHasErrors(['withdrawReasons.'.$entry->id]);
        $this->assertNull($entry->fresh()->withdrawn_at);

        $desk->set('withdrawReasons.'.$entry->id, 'The answer is out of date.')->call('withdraw', $entry->id)->assertHasNoErrors()->assertSee('Withdrawn');

        $this->assertNotNull($entry->fresh()->withdrawn_at);
        $this->assertSame('The answer is out of date.', ModerationAction::where('action', 'library_withdrawn')->sole()->reason);
        $reversal = DB::table('reward_outbox')->where('type', 'infodot.contribution.reward_reversed')->get();
        $this->assertCount(1, $reversal);
        $this->assertSame('infodot.knowledge.adopted', json_decode($reversal[0]->payload, true)['facts']['rule_key']);

        $desk->assertDontSee('Answer to: How do I register');
    }

    public function test_withdrawing_twice_reverses_the_points_only_once(): void
    {
        $this->memory([]);
        [$answer] = $this->acceptedAnswer();
        $entry = app(LibraryService::class)->adopt($answer, $this->moderator('Curator'))['entry'];
        $desk = $this->desk($this->moderator('Withdrawer'));

        $desk->set('withdrawReasons.'.$entry->id, 'Out of date now.')->call('withdraw', $entry->id);
        $desk->set('withdrawReasons.'.$entry->id, 'Out of date now.')->call('withdraw', $entry->id);

        $this->assertSame(1, ModerationAction::where('action', 'library_withdrawn')->count());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.contribution.reward_reversed')->count());
    }

    public function test_an_entry_whose_source_was_hidden_says_it_is_not_showing(): void
    {
        $this->memory([]);
        [$answer] = $this->acceptedAnswer();
        app(LibraryService::class)->adopt($answer, $this->moderator('Curator'));
        DB::table('answers')->where('id', $answer->id)->update(['hidden_at' => now()]);

        $this->desk($this->moderator('Another'))->assertSee('Not showing');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Adding without a recommendation
    // ---------------------------------------------------------------------------------------------------------------

    public function test_a_moderator_adds_a_solution_by_reference_without_a_recommendation(): void
    {
        $solution = $this->verifiedSolution();
        $mod = $this->moderator('Curator');

        $this->actingAs($mod)->post(route('moderation.library.add'), ['ref' => ' infodot:solution:'.$solution->id.' '])
            ->assertRedirect(route('moderation.library'))
            ->assertSessionHas('moderation-status', fn ($m) => str_contains($m, 'Added to the library'));

        $entry = LibraryEntry::sole();
        $this->assertSame('infodot:solution:'.$solution->id, $entry->contribution_ref);
        $this->assertNull($entry->recommendation_event_id);
        $this->assertSame(1, ModerationAction::where('action', 'library_adopted')->count());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.knowledge.adopted')->count());
        $this->assertSame(0, LibraryOutcome::count(), 'there is no recommendation to answer');
    }

    public function test_adding_twice_changes_nothing_and_says_so(): void
    {
        [$answer] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');

        $this->actingAs($mod)->post(route('moderation.library.add'), ['ref' => 'infodot:answer:'.$answer->id]);
        $this->actingAs($mod)->post(route('moderation.library.add'), ['ref' => 'infodot:answer:'.$answer->id])
            ->assertSessionHas('moderation-status', fn ($m) => str_contains($m, 'already in the library'));

        $this->assertSame(1, LibraryEntry::count());
        $this->assertSame(1, DB::table('reward_outbox')->where('type', 'infodot.knowledge.adopted')->count());
    }

    public function test_manual_adding_is_for_moderators_only_and_refuses_what_adoption_refuses(): void
    {
        [$answer] = $this->acceptedAnswer();
        $uncorroborated = $this->answer($this->question($this->person()), $this->person());
        $question = $this->question($this->person());
        $mod = $this->moderator('Curator');

        $this->post(route('moderation.library.add'), ['ref' => 'infodot:answer:'.$answer->id])->assertRedirect();
        $this->actingAs($this->person())->post(route('moderation.library.add'), ['ref' => 'infodot:answer:'.$answer->id])->assertForbidden();

        foreach ([
            'infodot:answer:'.$uncorroborated->id => 'accepted',
            'infodot:question:'.$question->id => 'not the reference',
            'infodot:answer:9999999' => 'not the reference',
            'rubbish' => 'not the reference',
        ] as $ref => $why) {
            $this->actingAs($mod)->from(route('moderation.library'))->post(route('moderation.library.add'), ['ref' => $ref])
                ->assertRedirect(route('moderation.library'))
                ->assertSessionHasErrors('ref');
            $this->assertStringContainsStringIgnoringCase($why, session('errors')->first('ref'));
        }

        $this->actingAs($mod)->post(route('moderation.library.add'), [])->assertSessionHasErrors('ref');
        $this->assertSame(0, LibraryEntry::count());
    }

    public function test_a_moderator_cannot_add_their_own_answer_by_reference(): void
    {
        $authorMod = $this->moderator('Author Moderator');
        $asker = $this->person('Asker');
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $authorMod), $asker);

        $this->actingAs($authorMod)->post(route('moderation.library.add'), ['ref' => 'infodot:answer:'.$answer->id])->assertForbidden();

        $this->assertSame(0, LibraryEntry::count());
    }
}
