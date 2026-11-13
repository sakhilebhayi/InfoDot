<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateTeamNameForm;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateTeamNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_names_can_be_updated()
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        Livewire::test(UpdateTeamNameForm::class, ['team' => $user->currentTeam])
            ->set(['state' => ['name' => 'Test Team']])
            ->call('updateTeamName');

        $this->assertCount(1, $user->fresh()->ownedTeams);
        $this->assertEquals('Test Team', $user->currentTeam->fresh()->name);
    }

    public function test_the_industry_choice_is_switched_off_for_a_member_by_the_server_not_by_a_script(): void
    {
        // The select carried :disabled="! Gate::check(…)", which is a Blade component binding on a plain element:
        // the browser's Alpine tried to run the PHP, threw in the console, and the choice was never switched off.
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $team->users()->attach($member, ['role' => 'member']);

        $forOwner = Livewire::actingAs($owner)->test(UpdateTeamNameForm::class, ['team' => $team])->html();
        $forMember = Livewire::actingAs($member)->test(UpdateTeamNameForm::class, ['team' => $team])->html();

        $this->assertSame(1, preg_match('/<select id="industry"[^>]*>/', $forOwner, $ownerTag));
        $this->assertSame(1, preg_match('/<select id="industry"[^>]*>/', $forMember, $memberTag));
        $this->assertStringNotContainsString('disabled', $ownerTag[0]);
        $this->assertMatchesRegularExpression('/\sdisabled(\s|>|=)/', $memberTag[0]);
        $this->assertStringNotContainsString('Gate::', $forOwner.$forMember, 'PHP reached the page as an attribute');
    }
}
