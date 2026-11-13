<?php

namespace Tests\Feature;

use App\Livewire\Teams\TeamMemberManager;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\Licensing\SeatLedger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Laravel\Jetstream\Mail\TeamInvitation as TeamInvitationMail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-49, finding L-09. A company invitation lives 14 days. Expired, it cannot be accepted and holds no seat; the person
 * who invited can send it again (a new 14 days); one expired for more than 30 days is removed by a daily command.
 */
class TeamInvitationExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Team $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        $this->owner = User::factory()->create();
        $this->company = Team::factory()->create(['user_id' => $this->owner->id, 'personal_team' => false, 'name' => 'Ledger Works']);
        $this->owner->forceFill(['current_team_id' => $this->company->id])->save();
    }

    private function invite(string $email): TeamInvitation
    {
        Livewire::actingAs($this->owner)->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('addTeamMemberForm', ['email' => $email, 'role' => 'member'])->call('addTeamMember')->assertHasNoErrors();

        return TeamInvitation::where('email', $email)->sole();
    }

    private function acceptUrl(TeamInvitation $invitation): string
    {
        return URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]);
    }

    public function test_a_new_invitation_runs_for_fourteen_days(): void
    {
        $this->freezeTime();
        $invitation = $this->invite('new@example.test');

        $this->assertSame(now()->addDays(14)->timestamp, $invitation->expires_at->timestamp);
        $this->assertFalse($invitation->isExpired());
    }

    public function test_an_invitation_can_be_accepted_inside_its_life(): void
    {
        $invitation = $this->invite('joiner@example.test');
        $joiner = User::factory()->create(['email' => 'joiner@example.test']);

        $this->travel(13)->days();
        $this->actingAs($joiner)->get($this->acceptUrl($invitation))->assertRedirect();

        $this->assertTrue($this->company->fresh()->hasUser($joiner));
        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_an_expired_invitation_is_refused_with_a_clear_message_and_kept(): void
    {
        $invitation = $this->invite('late@example.test');
        $late = User::factory()->create(['email' => 'late@example.test']);

        $this->travel(15)->days();
        $response = $this->actingAs($late)->get($this->acceptUrl($invitation));

        $response->assertRedirect()->assertSessionHas('flash.bannerStyle', 'danger');
        $this->assertStringContainsString('This invitation to join Ledger Works ran out on', (string) session('flash.banner'));
        $this->assertStringContainsString('send it again', (string) session('flash.banner'));
        $this->assertFalse($this->company->fresh()->hasUser($late));
        $this->assertSame(1, TeamInvitation::count(), 'the invitation stays so it can be sent again');
    }

    public function test_an_expired_invitation_stops_holding_a_seat(): void
    {
        config(['licensing.included_seats' => 2]);   // the owner and one more
        $this->invite('first@example.test');
        $seats = app(SeatLedger::class);
        $this->assertSame([1, 0], [$seats->summary($this->company)['reserved'], $seats->summary($this->company)['available']]);

        // Full: a second invitation is refused.
        Livewire::actingAs($this->owner)->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('addTeamMemberForm', ['email' => 'second@example.test', 'role' => 'member'])->call('addTeamMember')->assertHasErrors('email');

        $this->travel(15)->days();
        $this->assertSame([0, 1], [$seats->summary($this->company->fresh())['reserved'], $seats->summary($this->company->fresh())['available']]);
        $this->invite('second@example.test');
    }

    public function test_sending_again_starts_a_new_fourteen_days_and_sends_the_email(): void
    {
        $invitation = $this->invite('again@example.test');
        $this->travel(20)->days();
        $this->assertTrue($invitation->fresh()->isExpired());

        Livewire::actingAs($this->owner)->test(TeamMemberManager::class, ['team' => $this->company->fresh()])
            ->assertSee('Expired')->call('resendTeamInvitation', $invitation->id)->assertHasNoErrors()->assertDispatched('invitation-resent');

        $fresh = $invitation->fresh();
        $this->assertFalse($fresh->isExpired());
        $this->assertTrue($fresh->expires_at->isSameDay(now()->addDays(14)));
        Mail::assertSent(TeamInvitationMail::class, 2);

        // And it can be accepted again.
        $again = User::factory()->create(['email' => 'again@example.test']);
        $this->actingAs($again)->get($this->acceptUrl($fresh))->assertRedirect();
        $this->assertTrue($this->company->fresh()->hasUser($again));
    }

    public function test_sending_an_expired_invitation_again_needs_a_free_seat(): void
    {
        config(['licensing.included_seats' => 2]);
        $old = $this->invite('old@example.test');
        $this->travel(15)->days();
        $this->invite('newer@example.test');   // takes the seat the expired one gave up

        Livewire::actingAs($this->owner)->test(TeamMemberManager::class, ['team' => $this->company->fresh()])
            ->call('resendTeamInvitation', $old->id)->assertHasErrors('email');

        $this->assertTrue($old->fresh()->isExpired());
    }

    public function test_inviting_an_address_whose_invitation_expired_makes_a_new_one(): void
    {
        $old = $this->invite('back@example.test');
        $this->travel(15)->days();

        $new = $this->invite('back@example.test');

        $this->assertNotSame($old->id, $new->id);
        $this->assertFalse($new->isExpired());
    }

    public function test_only_people_who_may_invite_can_send_again_and_only_their_own_companys(): void
    {
        $invitation = $this->invite('guarded@example.test');
        $member = User::factory()->create();
        $this->company->users()->attach($member, ['role' => 'member']);

        Livewire::actingAs($member)->test(TeamMemberManager::class, ['team' => $this->company->fresh()])
            ->call('resendTeamInvitation', $invitation->id)->assertForbidden();

        $other = User::factory()->create();
        $otherCompany = Team::factory()->create(['user_id' => $other->id, 'personal_team' => false]);
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($other)->test(TeamMemberManager::class, ['team' => $otherCompany])->call('resendTeamInvitation', $invitation->id);
    }

    public function test_the_daily_command_removes_invitations_expired_for_more_than_thirty_days(): void
    {
        $gone = $this->invite('gone@example.test');
        $this->travel(14 + 29)->days();
        $kept = $this->invite('kept@example.test');

        $this->artisan('invitations:prune')->expectsOutputToContain('Removed 0')->assertSuccessful();   // expired 29 days ago
        $this->travel(2)->days();
        $this->artisan('invitations:prune')->expectsOutputToContain('Removed 1')->assertSuccessful();

        $this->assertNull($gone->fresh());
        $this->assertNotNull($kept->fresh());
    }

    public function test_the_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'invitations:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('15 3 * * *', $events->first()->expression);
    }

    public function test_the_migration_gives_existing_invitations_a_full_life_and_reverses(): void
    {
        $migration = require database_path('migrations/2026_11_13_140000_add_expires_at_to_team_invitations.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('team_invitations', 'expires_at'));

        DB::table('team_invitations')->insert(['team_id' => $this->company->id, 'email' => 'before@example.test', 'role' => 'member', 'created_at' => now()->subYear(), 'updated_at' => now()->subYear()]);

        $migration->up();
        $migration->up();

        $invitation = TeamInvitation::where('email', 'before@example.test')->sole();
        $this->assertFalse($invitation->isExpired());
        $this->assertTrue($invitation->expires_at->isSameDay(now()->addDays(14)));
    }
}
