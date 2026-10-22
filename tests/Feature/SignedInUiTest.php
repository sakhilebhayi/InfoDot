<?php

namespace Tests\Feature;

use App\Livewire\Comments;
use App\Livewire\Search;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The signed-in area is one shell (rail + top bar) with the "ix" design system.
 * These pin the parts that were broken or missing before it: every section is
 * reachable from the rail, the old palette is gone, the Dot Apps menu says which
 * platforms can actually be opened, search is live, solutions can be created,
 * and only the asker can mark a question solved.
 */
class SignedInUiTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function question(User $by, array $attrs = []): Questions
    {
        return Questions::withoutSyncingToSearch(fn () => Questions::create(array_merge([
            'user_id' => $by->id,
            'question' => 'How do we rotate the shared mail password?',
            'description' => 'Forms and Press send through one mailbox.',
            'status' => 0,
        ], $attrs)));
    }

    public function test_the_top_tabs_link_to_every_section_and_mark_the_current_one(): void
    {
        $html = $this->actingAs($this->member())->get('/solutions')->assertOk()->getContent();

        foreach (['/questions', '/solutions', '/platforms', '/analytics'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $html, $path);
        }

        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/solutions'), '#').'"[^>]*aria-current="page"#', $html);
    }

    public function test_signed_in_pages_carry_none_of_the_old_palette(): void
    {
        $user = $this->member();
        $this->question($user);

        foreach (['/questions', '/questions/ask', '/question/view/1', '/solutions', '/solution/create', '/platforms'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            foreach (['Manrope', 'Baloo', 'Nunito', 'Hanken', '#2962ff', '#b6c4ff', 'backdrop-filter', 'material-symbols-outlined', 'fa fa-'] as $old) {
                $this->assertStringNotContainsString($old, $html, "$url still contains $old");
            }
            $this->assertStringContainsString('Archivo', $html, $url);
        }
    }

    public function test_the_questions_page_offers_unsolved_and_solved_filters(): void
    {
        $user = $this->member();
        $this->question($user);
        $this->question($user, ['question' => 'Already answered', 'status' => 1]);

        $this->actingAs($user)->get('/questions')
            ->assertOk()
            ->assertSee('Waiting')
            ->assertSee('Solved')
            ->assertSee('aria-pressed', false);
    }

    public function test_an_empty_questions_page_explains_what_to_do(): void
    {
        $this->actingAs($this->member())->get('/questions')
            ->assertOk()
            ->assertSee('has no questions yet')
            ->assertSee('Ask the first question');
    }

    public function test_the_dot_apps_menu_only_offers_live_platforms_as_buttons(): void
    {
        config(['ecosystem.platforms' => [
            'agents' => ['name' => 'Dot.Agents', 'url' => 'https://agents.infodot.co.za', 'icon' => 'smart_toy', 'active' => true],
            'hr' => ['name' => 'Dot.HR', 'url' => 'https://hr.infodot.co.za', 'icon' => 'badge', 'active' => false],
        ]]);

        $html = $this->actingAs($this->member())->get('/questions')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/wire:click="switchTo\(\'agents\'\)"/', $html);
        $this->assertDoesNotMatchRegularExpression('/switchTo\(\'hr\'\)/', $html);
        $this->assertStringContainsString('Dot.HR is not live yet', $html);
    }

    public function test_search_finds_questions_and_solutions_by_any_field(): void
    {
        $user = $this->member();
        $this->question($user, ['question' => 'Why is the backup folder empty?', 'description' => 'Cron runs, nothing is written.']);
        Solutions::withoutSyncingToSearch(fn () => Solutions::create([
            'user_id' => $user->id, 'solution_title' => 'Nightly backups', 'solution_description' => 'Schedule on its own minute.',
            'tags' => 'backup, cron', 'duration' => 2, 'duration_type' => 'hours', 'steps' => 1,
        ]));

        $this->actingAs($user);

        // "cron" is in a question's description and a solution's tags, not in either title.
        Livewire::test(Search::class)
            ->set('query', 'cron')
            ->assertSee('Why is the backup folder empty?')
            ->assertSee('Nightly backups');

        Livewire::test(Search::class)
            ->set('query', 'zzzz-nothing')
            ->assertSee('Nothing matches');
    }

    public function test_a_solution_needs_at_least_one_step_and_saves_the_step_count(): void
    {
        $user = $this->member();

        $base = [
            'topics' => [Topic::query()->value('id')],
            'solution_title' => 'Register a business',
            'solution_description' => 'Everything needed to register.',
            'tags-input' => 'business, how to',
            'duration' => 1,
            'duration_type' => 'weeks',
        ];

        $this->actingAs($user)->post('/solution/add', $base)->assertSessionHasErrors('solution_heading');

        $this->actingAs($user)->post('/solution/add', $base + [
            'solution_heading' => ['Gather documents', 'Submit online'],
            'solution_body' => ['IDs and proof of address.', 'Use the CIPC site.'],
        ])->assertRedirect(route('solutions'));

        $this->assertDatabaseHas('solutions', ['solution_title' => 'Register a business', 'steps' => 2]);
        $this->assertDatabaseCount('solutions_step', 2);
    }

    public function test_only_the_asker_can_mark_a_question_solved(): void
    {
        $asker = $this->member();
        $other = $this->member();
        $question = $this->question($asker);

        $this->actingAs($other);
        Livewire::test(Comments::class, ['model' => $question, 'question' => $question])
            ->call('markedAsSolved')
            ->assertForbidden();
        $this->assertSame(0, (int) $question->fresh()->status);

        $this->actingAs($asker);
        Livewire::test(Comments::class, ['model' => $question, 'question' => $question])->call('markedAsSolved');
        $this->assertSame(1, (int) $question->fresh()->status);
    }

    public function test_figures_render_the_plain_number_in_the_figure_face(): void
    {
        $this->assertStringContainsString('>3<', view('components.ix.figure', ['value' => 3])->render());
        $this->assertStringContainsString('>0<', view('components.ix.figure', ['value' => 0])->render());
        $this->assertStringContainsString('>120<', view('components.ix.figure', ['value' => 120])->render());
        $this->assertStringContainsString('class="ix-fig"', view('components.ix.figure', ['value' => 7])->render());
    }
}
