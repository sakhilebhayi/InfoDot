<?php

namespace Tests\Feature;

use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\UpdateTeamName;
use App\Livewire\Search;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Support\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What the company does decides which examples InfoDot shows, and search completes from the
 * company's own questions and solutions.
 */
class IndustrySearchTest extends TestCase
{
    use RefreshDatabase;

    private function owner(?string $industry = null, string $name = 'Bluestone Mining'): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false, 'name' => $name]);
        $team->forceFill(['industry' => $industry])->save();
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function question(User $by, string $title, string $description = 'Details here.'): Questions
    {
        return Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $by->id, 'team_id' => $by->current_team_id, 'question' => $title, 'description' => $description, 'status' => 0,
        ]));
    }

    public function test_every_industry_is_complete_and_falls_back_to_general(): void
    {
        foreach (array_keys(config('industries')) as $key) {
            $team = new Team(['name' => 'x']);
            $team->industry = $key;
            $set = Industry::for($team);

            $this->assertSame($key, $set['key']);
            $this->assertGreaterThanOrEqual(4, count($set['searches']), $key);
            $this->assertGreaterThanOrEqual(6, count($set['questions']), $key);
            $this->assertGreaterThanOrEqual(4, count($set['solutions']), $key);

            foreach (['ask_title', 'ask_details', 'solution_title', 'solution_description', 'solution_tags', 'label'] as $field) {
                $this->assertNotSame('', $set[$field], "$key.$field");
            }
        }

        $this->assertSame('general', Industry::for(null)['key']);

        $unknown = new Team(['name' => 'x']);
        $unknown->industry = 'underwater-basket-weaving';
        $this->assertSame('general', Industry::for($unknown)['key']);
    }

    public function test_creating_and_renaming_a_company_stores_its_industry_and_rejects_unknown_ones(): void
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);

        $team = (new CreateTeam)->create($user, ['name' => 'Karoo Farms', 'industry' => 'agriculture']);
        $this->assertSame('agriculture', $team->fresh()->industry);

        (new UpdateTeamName)->update($user, $team, ['name' => 'Karoo Farms', 'industry' => 'retail']);
        $this->assertSame('retail', $team->fresh()->industry);

        (new UpdateTeamName)->update($user, $team, ['name' => 'Karoo Farms', 'industry' => '']);
        $this->assertNull($team->fresh()->industry, 'blank means general business');

        $this->expectException(ValidationException::class);
        (new CreateTeam)->create($user, ['name' => 'Bad Co', 'industry' => 'not-an-industry']);
    }

    public function test_the_empty_search_suggests_what_to_ask_for_this_kind_of_business(): void
    {
        $user = $this->owner('mining');

        Livewire::actingAs($user)->test(Search::class)
            ->assertSee('Mining and quarrying')
            ->assertSee('Try asking')
            ->assertSee('pre-shift inspection checklist')
            ->assertSee(route('seek', ['question' => 'How do we report a near miss and who must be told?']), false);

        Livewire::actingAs($this->owner(null, 'Plain Co'))->test(Search::class)
            ->assertSee('General business')
            ->assertSee('How do we onboard a new supplier?');
    }

    public function test_typing_completes_from_the_companys_own_content_and_offers_to_ask_it(): void
    {
        $user = $this->owner('retail');
        $this->question($user, 'Why does the till cash-up not balance?', 'Float is out by R50.');
        Solutions::withoutSyncingToSearch(fn () => Solutions::create([
            'user_id' => $user->id, 'team_id' => $user->current_team_id, 'solution_title' => 'Doing the till cash-up',
            'solution_description' => 'Count, record, bank.', 'tags' => 'till, cash', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 3,
        ]));

        $search = Livewire::actingAs($user)->test(Search::class)->set('query', 'cash-up');

        $search->assertSee('Doing the till <mark>cash-up</mark>', false)
            ->assertSee('Why does the till <mark>cash-up</mark> not balance?', false)
            ->assertSee('Ask “cash-up” as a new question')
            ->assertSee('See every result for “cash-up”');
    }

    public function test_search_never_shows_another_companys_content_or_executes_markup(): void
    {
        $mine = $this->owner('retail');
        $theirs = $this->owner('retail', 'Rival Ltd');
        $this->question($theirs, 'Rival secret pricing plan');
        $this->question($mine, '<script>alert(1)</script> backup tips');

        $search = Livewire::actingAs($mine)->test(Search::class)->set('query', 'backup');

        $search->assertDontSee('Rival secret pricing plan')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; <mark>backup</mark> tips', false);

        // A bare wildcard is text, not "match everything".
        Livewire::actingAs($mine)->test(Search::class)->set('query', '%%')->assertSee('Nothing matches');
    }

    public function test_arrows_wrap_and_enter_opens_the_highlighted_result(): void
    {
        $user = $this->owner();
        $q = $this->question($user, 'How do we order stock?');

        $search = Livewire::actingAs($user)->test(Search::class)->set('query', 'order stock');
        $this->assertSame(0, $search->get('highlightIndex'));

        $search->call('openHighlighted')->assertRedirect(route('questions.view', ['qid' => $q->id]));

        $count = count($search->get('items'));
        $search->call('decrementHighlight');
        $this->assertSame($count - 1, $search->get('highlightIndex'), 'up from the first wraps to the last');
        $search->call('incrementHighlight');
        $this->assertSame(0, $search->get('highlightIndex'));
    }

    public function test_the_ask_page_prefills_from_a_suggestion_and_uses_the_industry_placeholders(): void
    {
        $html = $this->actingAs($this->owner('mining'))->get('/questions/ask?question='.rawurlencode('Who signs off a permit?'))->assertOk()->getContent();

        $this->assertStringContainsString('value="Who signs off a permit?"', $html);
        $this->assertStringContainsString('placeholder="What is the pre-shift inspection checklist for the section?"', $html);
        $this->assertStringContainsString('Ideas for Mining and quarrying', $html);
    }

    public function test_the_questions_and_solutions_pages_show_a_tally_and_ideas_for_the_business(): void
    {
        $user = $this->owner('construction');
        $this->question($user, 'How do we raise a variation?');
        $solved = $this->question($user, 'Who approves payment?');
        $solved->forceFill(['status' => 1])->save();

        $this->actingAs($user)->get('/questions')->assertOk()
            ->assertSee('Waiting')->assertSee('Solved')
            ->assertSee('ix-tally', false)
            ->assertSee('Ideas for Construction and property');

        $this->actingAs($user)->get('/solutions')->assertOk()
            ->assertSee('has no solutions yet')
            ->assertSee('Inducting a new subcontractor on site');
    }

    public function test_company_settings_offer_the_industry_choice(): void
    {
        $user = $this->owner('retail');

        $this->actingAs($user)->get('/teams/'.$user->current_team_id)->assertOk()
            ->assertSee('What does the company do?')
            ->assertSee('Mining and quarrying');
    }
}
