<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** The signed-in search results page: in the Knowledge area, with counts, rows like the rest of Knowledge, and a way forward when nothing matches. */
class SearchResultsPageTest extends TestCase
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

    public function test_results_are_listed_as_rows_in_the_knowledge_area_with_counts(): void
    {
        $me = $this->person();
        $question = $this->question($me, 'public', 'How do I register a clothing brand?');
        $this->solution($me, 'public', 'Register a company with CIPC');

        $html = $this->actingAs($me)->get('/solution-results?search=register')->assertOk()->getContent();

        $this->assertStringContainsString('data-area="knowledge"', $html);
        $this->assertStringContainsString('<h1 class="ix-h1">Search</h1>', $html);
        $this->assertStringContainsString('2 results for', $html);
        $this->assertStringContainsString('1 question and 1 solution', $html);
        $this->assertStringContainsString('class="ix-qlist"', $html);
        $this->assertStringContainsString('class="ix-library"', $html);
        $this->assertStringContainsString('How do I register a clothing brand?', $html);
        $this->assertStringContainsString(route('questions.view', ['qid' => $question->id]), $html);
        $this->assertStringContainsString('<title>Knowledge · InfoDot</title>', $html);
    }

    public function test_reply_counts_are_shown(): void
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker, 'public', 'How do I register a clothing brand?');
        $this->answer($question, $this->person('Helper'));

        $html = $this->actingAs($asker)->get('/solution-results?search=register')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/ix-stencil-num">01<\/span>\s*<small>reply<\/small>/', $html);
        $this->assertNotNull(Answer::first());
    }

    public function test_a_search_with_no_match_offers_to_ask_it_with_the_words_filled_in(): void
    {
        $html = $this->actingAs($this->person())->get('/solution-results?search=zzqxw+nothing')->assertOk()->getContent();

        $this->assertStringContainsString('No match yet', $html);
        $this->assertStringContainsString('Nothing matched', $html);
        $this->assertStringContainsString(e(route('seek', ['question' => 'zzqxw nothing'])), $html);
        $this->assertStringContainsString(route('topics'), $html);
    }

    public function test_questions_and_solutions_page_separately(): void
    {
        $me = $this->person();
        foreach (range(1, 12) as $i) {
            $this->question($me, 'public', 'Register question '.$i);
        }

        $html = $this->actingAs($me)->get('/solution-results?search=Register')->assertOk()->getContent();

        $this->assertStringContainsString('qpage=2', $html);
        $this->assertStringContainsString('Page 1 of 2', $html);
        $this->assertStringNotContainsString('spage=2', $html, 'the solutions list has its own page counter');
        $this->assertSame(10, substr_count($html, 'class="ix-q__title"'));
    }

    public function test_a_company_only_question_is_not_found_by_another_company(): void
    {
        $owner = $this->person('Owner');
        $this->question($owner, 'company', 'Our secret register of suppliers');

        $html = $this->actingAs($this->person('Outsider'))->get('/solution-results?search=register')->assertOk()->getContent();

        $this->assertStringNotContainsString('secret register', $html);
        $this->assertStringContainsString('No match yet', $html);
    }

    public function test_a_visitor_who_is_not_signed_in_gets_the_public_shell_not_the_old_layout(): void
    {
        $owner = $this->person();
        $this->question($owner, 'public', 'How do I register a clothing brand?');

        $html = $this->get('/solution-results?search=register')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Search · InfoDot</title>', $html);
        $this->assertStringContainsString('class="ix-top"', $html);
        $this->assertStringContainsString('class="ix-band"', $html);
        $this->assertStringContainsString('Sign in', $html);
        $this->assertStringContainsString('Create account', $html);
        $this->assertStringContainsString('public questions and solutions only', $html);
        $this->assertStringContainsString('How do I register a clothing brand?', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('fa-facebook', $html, 'the old social strip is gone');
    }

    public function test_the_page_never_ends_blank_when_there_is_nothing_to_list(): void
    {
        $guest = $this->get('/solution-results')->assertOk()->getContent();
        $this->assertStringContainsString('Nothing has been shared publicly yet', $guest);
        $this->assertStringContainsString(route('login'), $guest);

        $signedIn = $this->actingAs($this->person())->get('/solution-results')->assertOk()->getContent();
        $this->assertStringContainsString('Nothing has been shared publicly yet', $signedIn);
        $this->assertStringContainsString(route('seek'), $signedIn);
    }

    public function test_a_company_only_question_is_never_shown_to_a_visitor(): void
    {
        $this->question($this->person(), 'company', 'Our private register of suppliers');

        $this->get('/solution-results?search=register')->assertOk()->assertDontSee('private register');
    }
}
