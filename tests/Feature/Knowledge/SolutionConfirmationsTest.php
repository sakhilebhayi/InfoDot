<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\SolutionConfirmations;
use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-16 phase 1: one confirmation per person, toggleable, never the author's own, optional feedback, honest insights. */
class SolutionConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    private function solution(?User $author = null, string $visibility = Solutions::VISIBILITY_PUBLIC): Solutions
    {
        $author ??= User::factory()->withPersonalTeam()->create();
        $solution = new Solutions;
        $solution->forceFill([
            'user_id' => $author->id, 'team_id' => $author->current_team_id, 'visibility' => $visibility,
            'solution_title' => 'Reconcile the bank weekly', 'solution_description' => 'How.', 'tags' => 'money', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 0,
        ])->save();

        return $solution;
    }

    public function test_a_member_confirms_once_toggles_off_and_the_author_is_told(): void
    {
        $solution = $this->solution();
        $member = User::factory()->withPersonalTeam()->create();

        $component = Livewire::actingAs($member)->test(SolutionConfirmations::class, ['solutionId' => $solution->id]);

        $component->call('toggle')->assertHasNoErrors();
        $this->assertSame(1, SolutionConfirmation::count());

        // The same person confirming again is a toggle off, never a second row.
        $component->call('toggle');
        $this->assertSame(0, SolutionConfirmation::count());

        $component->call('toggle');
        $this->assertSame(1, SolutionConfirmation::where('user_id', $member->id)->count());

        $this->assertSame(1, UserNotice::where('user_id', $solution->user_id)->where('kind', UserNotice::SOLUTION_CONFIRMED)->count());
    }

    public function test_the_author_cannot_confirm_their_own_solution(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $solution = $this->solution($author);

        Livewire::actingAs($author)->test(SolutionConfirmations::class, ['solutionId' => $solution->id])
            ->call('toggle')->assertHasErrors('confirmation');

        $this->assertSame(0, SolutionConfirmation::count());
    }

    public function test_feedback_is_optional_and_saved_against_my_confirmation(): void
    {
        $solution = $this->solution();
        $member = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($member)->test(SolutionConfirmations::class, ['solutionId' => $solution->id])
            ->call('toggle')
            ->set('feedback.business_industry', 'plumbing services')
            ->set('feedback.results', 'Late payments halved in two months.')
            ->set('feedback.recommend', 'yes')
            ->call('saveFeedback')->assertHasNoErrors();

        $mine = SolutionConfirmation::where('user_id', $member->id)->firstOrFail();
        $this->assertSame('plumbing services', $mine->business_industry);
        $this->assertTrue($mine->recommend);
    }

    public function test_a_company_only_solution_cannot_be_confirmed_by_outsiders(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $solution = $this->solution($author, Solutions::VISIBILITY_COMPANY);
        $outsider = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($outsider)->test(SolutionConfirmations::class, ['solutionId' => $solution->id])
            ->assertStatus(404);
    }

    public function test_insights_appear_only_with_enough_confirmations(): void
    {
        $solution = $this->solution();

        $few = Livewire::actingAs(User::factory()->withPersonalTeam()->create())
            ->test(SolutionConfirmations::class, ['solutionId' => $solution->id]);
        $few->assertViewHas('insights', null);

        foreach (range(1, 5) as $i) {
            $user = User::factory()->withPersonalTeam()->create();
            SolutionConfirmation::create(['solution_id' => $solution->id, 'user_id' => $user->id] + ['business_industry' => 'retail '.$i]);
        }

        Livewire::actingAs(User::factory()->withPersonalTeam()->create())
            ->test(SolutionConfirmations::class, ['solutionId' => $solution->id])
            ->assertViewHas('insights', fn ($insights) => $insights !== null && $insights['businesses'] === 5);
    }
}
