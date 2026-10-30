<?php

namespace Tests\Feature\Knowledge;

use App\Models\Questions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** R-16 phase 6: search stays bounded — no per-row queries, deduped work, related fallback when words miss. */
class SearchOptimisationTest extends TestCase
{
    use RefreshDatabase;

    private function seedQuestions(User $author, int $count): void
    {
        foreach (range(1, $count) as $i) {
            $question = new Questions;
            $question->forceFill([
                'user_id' => $author->id, 'team_id' => $author->current_team_id, 'visibility' => Questions::VISIBILITY_PUBLIC,
                'question' => 'Cash flow question number '.$i.' about suppliers', 'description' => 'Suppliers wait while cash flow stays tight in month '.$i.'.',
            ])->save();
        }
    }

    public function test_the_similar_endpoint_runs_a_bounded_number_of_queries_whatever_the_library_size(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $this->seedQuestions($author, 40);
        $me = User::factory()->withPersonalTeam()->create();
        $this->actingAs($me);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson(route('knowledge.similar'), [
            'kind' => 'question',
            'text' => 'Suppliers wait while our cash flow stays tight every single month, what can we do?',
        ])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queries, 'duplicate detection must not grow with the library (ran '.$queries.' queries)');
    }

    public function test_identical_similar_searches_are_answered_from_cache(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $this->seedQuestions($author, 3);
        $me = User::factory()->withPersonalTeam()->create();
        $this->actingAs($me);

        $payload = ['kind' => 'question', 'text' => 'Suppliers wait while cash flow stays tight, how do other businesses cope?'];
        $this->postJson(route('knowledge.similar'), $payload)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson(route('knowledge.similar'), $payload)->assertOk();
        $contentQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'questions') || str_contains($q['query'], 'solutions'))->count();
        DB::disableQueryLog();

        $this->assertSame(0, $contentQueries, 'the identical search re-reads nothing from the content tables');
    }

    public function test_the_results_page_offers_close_matches_when_exact_words_miss(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $existing = new Questions;
        $existing->forceFill([
            'user_id' => $author->id, 'team_id' => $author->current_team_id, 'visibility' => Questions::VISIBILITY_PUBLIC,
            'question' => 'How do I improve cash flow in my small shop?', 'description' => 'Money runs out before suppliers and payday arrive each month.',
        ])->save();

        $me = User::factory()->withPersonalTeam()->create();

        $this->actingAs($me)->get('/solution-results?search='.urlencode('no money left before payday suppliers'))
            ->assertOk()
            ->assertSee('Close matches for what you described')
            ->assertSee($existing->question);
    }

    public function test_the_header_search_debounce_comes_from_config(): void
    {
        config(['search.debounce_ms' => 450]);
        $me = User::factory()->withPersonalTeam()->create();

        $this->actingAs($me)->get('/home')->assertOk()->assertSee('debounce.450ms', false);
    }
}
