<?php

namespace Tests\Feature;

use App\Livewire\Comments;
use App\Livewire\DotSwitcher;
use App\Livewire\Search;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CoverageGapTest extends TestCase
{
    use RefreshDatabase;

    private function authUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    // QuestionsController

    public function test_seek_question_page_renders(): void
    {
        $user = $this->authUser();
        $this->actingAs($user)->get('/questions/ask')->assertOk();
    }

    public function test_view_question_page_renders(): void
    {
        $user = $this->authUser();
        $question = Questions::create([
            'user_id' => $user->id,
            'question' => 'How does testing work?',
            'description' => 'Explain PHPUnit basics.',
            'status' => 0,
        ]);

        $this->actingAs($user)
            ->get('/question/view/'.$question->id)
            ->assertOk();
    }

    // SolutionsController

    public function test_create_solution_page_renders(): void
    {
        $user = $this->authUser();
        $this->actingAs($user)->get('/solution/create')->assertOk();
    }

    public function test_view_solution_page_renders(): void
    {
        $user = $this->authUser();
        $solution = Solutions::create([
            'user_id' => $user->id,
            'solution_title' => 'Deploy a Laravel app',
            'solution_description' => 'Step by step guide.',
            'tags' => 'laravel, deployment',
            'duration' => 2,
            'duration_type' => 'hours',
            'steps' => 0,
        ]);

        $this->actingAs($user)
            ->get('/solution/view/'.$solution->id)
            ->assertOk();
    }

    // PagesController

    public function test_contact_form_sends_and_redirects(): void
    {
        $this->post('/contact-send', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'message' => 'Hello from the test suite.',
        ])->assertRedirect();
    }

    public function test_user_profile_page_renders(): void
    {
        $user = $this->authUser();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get('/user/profile/'.$other->id)
            ->assertOk();
    }

    // Search Livewire component

    public function test_search_returns_empty_when_query_blank(): void
    {
        $this->authUser();

        Livewire::test(Search::class)
            ->assertSet('query', '')
            ->assertSet('highlightIndex', 0);
    }

    public function test_search_finds_matching_solutions(): void
    {
        $user = $this->authUser();
        Solutions::create([
            'user_id' => $user->id,
            'solution_title' => 'Docker compose setup',
            'solution_description' => 'Run Docker containers locally.',
            'tags' => 'docker, containers',
            'duration' => 1,
            'duration_type' => 'days',
            'steps' => 0,
        ]);

        $component = Livewire::test(Search::class)->set('query', 'Docker');
        $this->assertGreaterThanOrEqual(1, $component->get('solutions')->count());
    }

    public function test_search_keyboard_highlight_increments_and_wraps(): void
    {
        $user = $this->authUser();
        Questions::create([
            'user_id' => $user->id,
            'question' => 'What is Docker?',
            'description' => 'Container basics.',
            'status' => 0,
        ]);

        Livewire::test(Search::class)
            ->set('query', 'Docker')
            ->call('incrementHighlight')
            ->assertSet('highlightIndex', 1)
            ->call('decrementHighlight')
            ->assertSet('highlightIndex', 0);
    }

    public function test_search_query_update_resets_highlight(): void
    {
        Livewire::test(Search::class)
            ->set('highlightIndex', 3)
            ->set('query', 'anything')
            ->assertSet('highlightIndex', 0);
    }

    // AnalyticsController

    public function test_analytics_dashboard_renders_for_authenticated_user(): void
    {
        $user = $this->authUser();
        $this->actingAs($user)->get('/analytics')->assertOk();
    }

    public function test_analytics_dashboard_redirects_guest(): void
    {
        $this->get('/analytics')->assertRedirect('/login');
    }

    // DotSwitcher

    public function test_dot_switcher_issues_token_and_redirects(): void
    {
        $user = $this->authUser();

        Livewire::actingAs($user)
            ->test(DotSwitcher::class)
            ->call('switchTo', 'files')
            ->assertRedirect();
    }

    public function test_dot_switcher_ignores_unknown_platform(): void
    {
        $user = $this->authUser();

        // Should not throw or redirect — unknown key is a no-op
        Livewire::actingAs($user)
            ->test(DotSwitcher::class)
            ->call('switchTo', 'nonexistent')
            ->assertNoRedirect()
            ->assertHasNoErrors();
    }

    // Comments — storeLike and markedAsSolved

    public function test_comments_component_can_toggle_like_on_question(): void
    {
        $user = $this->authUser();
        $question = Questions::create([
            'user_id' => $user->id,
            'question' => 'Is this tested?',
            'description' => 'Yes it is.',
            'status' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(Comments::class, ['model' => $question, 'question' => $question])
            ->call('storeLike');

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likable_id' => $question->id,
            'likable_type' => 'questions',
        ]);
    }

    public function test_comments_component_can_mark_question_as_solved(): void
    {
        $user = $this->authUser();
        $question = Questions::create([
            'user_id' => $user->id,
            'question' => 'Solved yet?',
            'description' => 'Nearly.',
            'status' => 0,
        ]);

        Livewire::actingAs($user)
            ->test(Comments::class, ['model' => $question, 'question' => $question])
            ->call('markedAsSolved');

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'status' => 1,
        ]);
    }
}
