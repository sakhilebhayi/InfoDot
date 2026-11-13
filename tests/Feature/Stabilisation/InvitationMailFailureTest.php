<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-49. The host sends mail inside the request. When the mail server refused an invitation, the page answered 500
 * with the invitation already saved: a seat was held, nothing had been sent, and inviting the same address again was
 * refused as "already invited". A failed send now leaves nothing behind and says so on the form.
 */
class InvitationMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invitation_that_cannot_be_emailed_is_not_kept_and_the_form_says_so(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        Log::spy();

        $owner = User::factory()->create();
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $company->id])->save();

        Livewire::actingAs($owner->fresh())->test(TeamMemberManager::class, ['team' => $company])
            ->set('addTeamMemberForm', ['email' => 'colleague@example.com', 'role' => 'admin'])
            ->call('addTeamMember')
            ->assertHasErrors(['email']);

        $this->assertCount(0, $company->fresh()->teamInvitations);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'team.invitation_mail_failed')->once();
    }
}
