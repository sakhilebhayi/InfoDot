<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Reserved and non-deliverable email domains (example, test, invalid, localhost, example.com/.net/.org) are refused
 * wherever a NEW address is accepted, and existing accounts are never touched. phpunit.xml turns the rule off for the
 * rest of the suite; these tests turn it on.
 */
class ReservedEmailDomainsTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Please use a real email address that can receive mail: addresses on example, test, invalid and localhost domains are not accepted.';

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.block_reserved_email_domains' => true]);
    }

    private function register(string $email)
    {
        return $this->post('/register', ['name' => 'Test User', 'email' => $email, 'password' => 'a-Long-Passw0rd!x', 'password_confirmation' => 'a-Long-Passw0rd!x', 'terms' => 'on']);
    }

    public static function refused(): array
    {
        return [['x@example.test'], ['x@foo.invalid'], ['x@sub.example.com'], ['x@localhost'], ['x@EXAMPLE.ORG'], ['x@example.test.']];
    }

    #[DataProvider('refused')]
    public function test_registration_refuses_reserved_domains_with_a_clear_field_error(string $email): void
    {
        $this->from('/register')->register($email)->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_the_register_page_shows_the_message_in_the_field_error(): void
    {
        $this->followingRedirects()->from('/register')->register('x@example.test')->assertSee(self::MESSAGE, false);
    }

    #[DataProvider('accepted')]
    public function test_registration_accepts_real_and_lookalike_addresses(string $email): void
    {
        $this->register($email)->assertSessionDoesntHaveErrors();

        $this->assertAuthenticated();
        $this->assertSame(1, User::where('email', $email)->count());
    }

    public static function accepted(): array
    {
        return [['person@gmail.com'], ['x@example.com.au'], ['x@notexample.com']];
    }

    public function test_the_flag_off_lets_registration_through(): void
    {
        config(['auth.block_reserved_email_domains' => false]);

        $this->register('x@example.test')->assertSessionDoesntHaveErrors();
        $this->assertSame(1, User::where('email', 'x@example.test')->count());
    }

    public function test_an_existing_user_on_a_reserved_domain_can_still_sign_in_and_keep_their_address(): void
    {
        $user = User::factory()->create(['email' => 'runtime-check@example.test', 'password' => bcrypt('a-Long-Passw0rd!x')]);

        $this->post('/login', ['email' => 'runtime-check@example.test', 'password' => 'a-Long-Passw0rd!x'])->assertSessionDoesntHaveErrors();
        $this->assertAuthenticatedAs($user);

        // Saving the profile with the same address (changing only the name) is not blocked.
        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'New Name', 'email' => 'runtime-check@example.test'])
            ->call('updateProfileInformation')
            ->assertHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('runtime-check@example.test', $user->fresh()->email);
    }

    public function test_changing_the_profile_email_to_a_reserved_domain_is_refused(): void
    {
        $this->actingAs($user = User::factory()->create(['email' => 'real@gmail.com']));

        try {
            app(UpdatesUserProfileInformation::class)->update($user, ['name' => $user->name, 'email' => 'new@sub.example.com']);
            $this->fail('The reserved address was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(self::MESSAGE, $e->errors()['email'][0]);
            $this->assertSame('updateProfileInformation', $e->errorBag);
        }

        $this->assertSame('real@gmail.com', $user->fresh()->email);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => $user->name, 'email' => 'new@example.com.au'])
            ->call('updateProfileInformation')
            ->assertHasNoErrors();
    }

    public function test_invitations_refuse_reserved_domains_and_accept_real_ones(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $company = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $company->id])->save();
        TeamLicence::create(['team_id' => $company->id, 'purchased_seats' => 3, 'payment_status' => 'active']);
        $this->actingAs($user);

        Livewire::test(TeamMemberManager::class, ['team' => $company])
            ->set('addTeamMemberForm', ['email' => 'guest@invalid', 'role' => 'admin'])
            ->call('addTeamMember')
            ->assertHasErrors(['email']);
        Livewire::test(TeamMemberManager::class, ['team' => $company])
            ->set('addTeamMemberForm', ['email' => 'guest@Example.Test', 'role' => 'admin'])
            ->call('addTeamMember')
            ->assertHasErrors(['email']);
        $this->assertCount(0, $company->fresh()->teamInvitations);

        Livewire::test(TeamMemberManager::class, ['team' => $company])
            ->set('addTeamMemberForm', ['email' => 'guest@notexample.com', 'role' => 'admin'])
            ->call('addTeamMember')
            ->assertHasNoErrors();
        $this->assertCount(1, $company->fresh()->teamInvitations);
    }

    public function test_the_contact_form_refuses_a_reserved_address(): void
    {
        $this->post('/contact-send', ['name' => 'A', 'email' => 'a@example.org', 'message' => 'A message that is long enough.'])
            ->assertSessionHasErrors(['email' => self::MESSAGE]);
    }

    public function test_the_business_contact_email_is_checked_only_when_it_changes(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $url = '/business/'.$team->uuid.'/profile';

        $this->actingAs($owner)->post($url, ['contact_email' => 'hello@acme.example'])->assertSessionHasErrors(['contact_email' => self::MESSAGE]);
        $this->actingAs($owner)->post($url, ['contact_email' => 'hello@acme.co.za'])->assertSessionDoesntHaveErrors();
        $this->assertSame('hello@acme.co.za', $team->profile()->first()->contact_email);
    }

    public function test_the_report_command_lists_masked_addresses_and_changes_nothing(): void
    {
        User::factory()->create(['email' => 'runtime-check@example.test']);
        User::factory()->create(['email' => 'b@sub.example.com']);
        User::factory()->create(['email' => 'real@example.com.au']);
        User::factory()->create(['email' => 'real@gmail.com']);
        $before = User::orderBy('id')->get(['id', 'email', 'updated_at'])->toArray();

        $this->artisan('users:reserved-emails --count')->expectsOutput('2')->assertSuccessful();
        $this->artisan('users:reserved-emails')
            ->expectsOutputToContain('r******@example.test')
            ->expectsOutputToContain('2 user(s). Nothing was changed.')
            ->doesntExpectOutputToContain('runtime-check')
            ->assertSuccessful();

        $this->assertSame($before, User::orderBy('id')->get(['id', 'email', 'updated_at'])->toArray());
    }

    public function test_the_report_command_says_so_when_there_are_none(): void
    {
        User::factory()->create(['email' => 'real@gmail.com']);

        $this->artisan('users:reserved-emails')->expectsOutputToContain('No existing user')->assertSuccessful();
        $this->artisan('users:reserved-emails --count')->expectsOutput('0')->assertSuccessful();
    }
}
