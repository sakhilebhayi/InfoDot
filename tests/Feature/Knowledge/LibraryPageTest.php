<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\LibraryEntry;
use App\Models\Solutions;
use App\Models\Topic;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Library\LibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/** /library: what everyone signed in may read, always from the live source. */
class LibraryPageTest extends TestCase
{
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    private User $curator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->curator = $this->moderator('Curator');
    }

    /** @return array{0: LibraryEntry, 1: Answer} */
    private function adoptedAnswer(string $title = 'How do I register a clothing brand?', string $topic = 'getting-started', string $body = 'First register the business with CIPC, then open a bank account, then ask your supplier for terms in writing.'): array
    {
        $asker = $this->person('Asker Name');
        $question = $this->file($this->question($asker, 'public', $title), $topic);
        $answer = app(AnswerService::class)->accept($this->answer($question, $this->person('Answer Author Name'), $body), $asker);

        return [app(LibraryService::class)->adopt($answer->fresh(), $this->curator)['entry'], $answer->fresh()];
    }

    /** @return array{0: LibraryEntry, 1: Solutions} */
    private function adoptedSolution(string $title = 'Register a company with CIPC', string $topic = 'integrations'): array
    {
        $solution = $this->file($this->solution($this->person('Solution Author Name'), 'public', $title), $topic);
        app(ModerationService::class)->verify($solution, $this->moderator('Verifier'));

        return [app(LibraryService::class)->adopt($solution->fresh(), $this->curator)['entry'], $solution->fresh()];
    }

    /**
     * The titles the library lists, in order. The page also carries the search box, which lists recent questions of the
     * viewer's company, so assertions about the library look only at its own links.
     *
     * @return list<string>
     */
    private function titles(string $url, User $viewer): array
    {
        $html = $this->actingAs($viewer)->get($url)->assertOk()->getContent();
        preg_match_all('/<a class="ix-lib__title"[^>]*>(.*?)<\/a>/s', $html, $m);

        return array_map(fn ($t) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($t)))), $m[1]);
    }

    public function test_guests_go_to_sign_in_and_any_signed_in_member_may_read_it(): void
    {
        $this->get('/library')->assertRedirect();
        $this->assertStringContainsString('login', (string) $this->get('/library')->headers->get('Location'));

        $this->actingAs($this->person())->get('/library')->assertOk()->assertSee('Verified library');
        $this->actingAs($this->curator)->get('/library')->assertOk();
    }

    public function test_the_navigation_links_to_it_for_every_signed_in_person(): void
    {
        $this->actingAs($this->person())->get('/questions')->assertOk()->assertSee(route('library'), false)->assertSee('Library');
        $this->actingAs($this->curator)->get('/questions')->assertOk()->assertSee(route('library'), false);
    }

    public function test_it_sits_in_the_knowledge_area_with_its_band_trail_and_sub_navigation(): void
    {
        $page = $this->actingAs($this->person())->get('/library')->assertOk();

        $page->assertSee('data-area="knowledge"', false)->assertSee('KNOW')->assertSee('aria-label="Knowledge"', false)
            ->assertSee('<span class="here" aria-current="page">Library</span>', false);
        $this->assertStringContainsString('<title>Knowledge · InfoDot</title>', $page->getContent());
    }

    public function test_the_moderators_desk_has_its_own_trail(): void
    {
        $this->actingAs($this->curator)->get('/moderation/library')->assertOk()
            ->assertSee('MODR')->assertSee('<span class="here" aria-current="page">Verified library</span>', false);
    }

    public function test_an_empty_library_says_so(): void
    {
        $this->actingAs($this->person())->get('/library')->assertOk()->assertSee('The library is empty so far');
    }

    public function test_entries_show_the_live_title_topic_link_and_the_verified_badge(): void
    {
        [, $answer] = $this->adoptedAnswer();
        [, $solution] = $this->adoptedSolution();

        $this->actingAs($this->person())->get('/library')->assertOk()
            ->assertSee('How do I register a clothing brand?')
            ->assertSee('Register a company with CIPC')
            ->assertSee('Getting started')
            ->assertSee('Integrations')
            ->assertSee('Verified by moderators')
            ->assertSee(route('questions.view', ['qid' => $answer->question_id]).'#answer-'.$answer->id, false)
            ->assertSee(route('solutions.view', ['id' => $solution->id]), false);
    }

    public function test_no_author_name_email_or_body_text_is_shown_and_nothing_is_copied_into_the_entry(): void
    {
        [$entry, $answer] = $this->adoptedAnswer();
        $this->adoptedSolution();

        $page = $this->actingAs($this->person('Reader'))->get('/library')->assertOk();

        foreach (['Answer Author Name', 'Solution Author Name', 'Asker Name', 'Verifier', 'Curator', 'First register the business', 'order of the steps matters', User::where('name', 'Answer Author Name')->value('email')] as $forbidden) {
            $page->assertDontSee($forbidden);
        }
        // The row holds a reference and who adopted it: no title, no text.
        $this->assertEqualsCanonicalizing(
            ['id', 'contribution_ref', 'kind', 'answer_id', 'solution_id', 'adopted_by', 'adopted_at', 'recommendation_event_id', 'withdrawn_at', 'created_at', 'updated_at'],
            array_keys(DB::table('library_entries')->first() ? (array) DB::table('library_entries')->first() : []),
        );
    }

    public function test_it_reads_the_source_live_so_a_renamed_title_never_shows_stale(): void
    {
        [, $solution] = $this->adoptedSolution('Old solution title');
        [, $answer] = $this->adoptedAnswer('Old question title?');

        $solution->forceFill(['solution_title' => 'Brand new solution title'])->save();
        $answer->question->forceFill(['question' => 'Brand new question title?'])->save();

        $titles = implode(' | ', $this->titles('/library', $this->person()));

        $this->assertStringContainsString('Brand new solution title', $titles);
        $this->assertStringContainsString('Brand new question title?', $titles);
        $this->assertStringNotContainsString('Old solution title', $titles);
        $this->assertStringNotContainsString('Old question title?', $titles);
    }

    public function test_hidden_deleted_company_and_withdrawn_sources_are_left_out_for_everyone(): void
    {
        [, $hiddenAnswer] = $this->adoptedAnswer('Hidden answer question?');
        [, $hiddenQuestionAnswer] = $this->adoptedAnswer('Hidden question question?');
        [, $deletedAnswer] = $this->adoptedAnswer('Deleted answer question?');
        [, $companyAnswer] = $this->adoptedAnswer('Went company question?');
        [, $hiddenSolution] = $this->adoptedSolution('Hidden solution');
        [, $companySolution] = $this->adoptedSolution('Went company solution');
        [$withdrawnEntry] = $this->adoptedSolution('Withdrawn solution');
        [, $keptSolution] = $this->adoptedSolution('Kept solution');
        [, $keptAnswer] = $this->adoptedAnswer('Kept question?');

        DB::table('answers')->where('id', $hiddenAnswer->id)->update(['hidden_at' => now()]);
        DB::table('questions')->where('id', $hiddenQuestionAnswer->question_id)->update(['hidden_at' => now()]);
        $deletedAnswer->delete();
        DB::table('questions')->where('id', $companyAnswer->question_id)->update(['visibility' => 'company']);
        DB::table('solutions')->where('id', $hiddenSolution->id)->update(['hidden_at' => now()]);
        DB::table('solutions')->where('id', $companySolution->id)->update(['visibility' => 'company']);
        app(LibraryService::class)->withdraw($withdrawnEntry, $this->moderator('Withdrawer'), 'Not needed any more.');

        $author = User::find($keptAnswer->user_id);

        foreach ([$this->person('Reader'), $this->curator, $author, User::find($hiddenAnswer->user_id)] as $viewer) {
            $titles = $this->titles('/library', $viewer);
            $this->assertEqualsCanonicalizing(['Kept solution', 'Kept question?'], array_map(fn ($t) => preg_replace('/^(Answer to: |Solution: )/', '', $t), $titles), 'viewer '.$viewer->name);
        }
    }

    public function test_content_that_is_restored_comes_back(): void
    {
        [, $answer] = $this->adoptedAnswer('Comes back question?');
        $mod = $this->moderator('Hider');
        app(ModerationService::class)->hide($answer->fresh(), $mod, 'Hidden for the moment.');

        $this->assertSame([], $this->titles('/library', $this->person()));

        app(ModerationService::class)->restore($answer->fresh(), $mod);

        $this->assertSame(['Answer to: Comes back question?'], $this->titles('/library', $this->person()));
    }

    public function test_the_topic_filter_narrows_the_list_and_an_answer_is_filed_where_its_question_is(): void
    {
        $this->adoptedAnswer('Answer in getting started?', 'getting-started');
        $this->adoptedAnswer('Answer in integrations?', 'integrations');
        $this->adoptedSolution('Solution in integrations', 'integrations');
        $this->adoptedSolution('Solution in billing', 'billing-and-payments');
        $reader = $this->person('Reader');

        $this->assertEqualsCanonicalizing(['Answer to: Answer in integrations?', 'Solution: Solution in integrations'], $this->titles('/library?topic=integrations', $reader));
        $this->assertSame(['Solution: Solution in billing'], $this->titles('/library?topic=billing-and-payments', $reader));
        $this->assertSame(['Answer to: Answer in getting started?'], $this->titles('/library?topic=getting-started', $reader));
        $this->assertCount(4, $this->titles('/library', $reader));
    }

    public function test_a_topic_with_nothing_in_the_library_says_so_and_an_unknown_topic_is_not_found(): void
    {
        $this->adoptedSolution('Solution in integrations', 'integrations');
        $reader = $this->person('Reader');

        $this->actingAs($reader)->get('/library?topic=getting-started')->assertOk()->assertSee('Nothing in the library under Getting started yet');
        $this->assertSame([], $this->titles('/library?topic=getting-started', $reader));
        $this->actingAs($reader)->get('/library?topic=no-such-topic')->assertNotFound();
    }

    public function test_a_retired_topic_can_still_be_filtered_by(): void
    {
        $this->adoptedSolution('Old topic solution', 'integrations');
        Topic::where('slug', 'integrations')->update(['active' => false]);

        $this->actingAs($this->person())->get('/library?topic=integrations')->assertOk()->assertSee('Old topic solution')->assertSee('(retired)');
    }

    public function test_the_newest_adoption_is_first_and_the_list_is_paged(): void
    {
        foreach (range(1, 22) as $i) {
            $this->adoptedSolution('Solution number '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            DB::table('library_entries')->where('contribution_ref', 'like', '%')->orderByDesc('id')->limit(1)->update(['adopted_at' => now()->addMinutes($i)]);
        }
        $reader = $this->person('Reader');

        $first = $this->titles('/library', $reader);
        $this->assertCount(20, $first);
        $this->assertSame('Solution: Solution number 22', $first[0]);
        $this->assertSame('Solution: Solution number 03', $first[19]);
        $this->assertSame(['Solution: Solution number 02', 'Solution: Solution number 01'], $this->titles('/library?page=2', $reader));
    }
}
