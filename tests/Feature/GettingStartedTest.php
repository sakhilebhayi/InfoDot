<?php

namespace Tests\Feature;

use App\Models\PlatformEntitlement;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Support\GettingStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The "Getting started" block on Home (R-49): a new person is shown the few things to do first, each row read from what
 * is really in the database, and the block goes away when everything is done or when the person puts it away.
 */
class GettingStartedTest extends TestCase
{
    use RefreshDatabase;

    /** A person as registration leaves them: verified, with a personal workspace and nothing else. */
    private function newcomer(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now(), 'name' => 'Nomvula Newcomer']);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true, 'name' => "Nomvula's Team"]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function business(User $owner, string $name = 'Northwind Finance'): Team
    {
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => $name]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    /** @return array<string, array{key: string, title: string, note: string, done: bool, url: string, action: string}> */
    private function steps(User $user): array
    {
        return collect(GettingStarted::steps($user->fresh()))->keyBy('key')->all();
    }

    /** The markup of one row of the block, by its step. */
    private function row(string $html, string $key): string
    {
        $this->assertSame(1, preg_match('#<li[^>]*data-step="'.$key.'".*?</li>#s', $html, $m), 'no row for '.$key);

        return $m[0];
    }

    public function test_a_new_person_sees_the_five_company_steps_with_the_company_first(): void
    {
        $user = $this->newcomer();

        $html = $this->actingAs($user)->get('/home')->assertOk()
            ->assertSee('Getting started')->assertSee('0 of 5 done')->getContent();

        $this->assertSame(1, substr_count($html, 'class="ix-start"'));
        $this->assertSame(['company', 'essentials', 'invite', 'platforms', 'explore'], array_keys($this->steps($user)));

        $company = $this->row($html, 'company');
        $this->assertStringContainsString('Create your company profile', $company);
        $this->assertStringContainsString('href="'.route('onboarding.business').'"', $company);

        // Without a company the later steps say so and send the person to the one thing that unlocks them.
        foreach (['essentials', 'invite', 'platforms', 'explore'] as $key) {
            $row = $this->row($html, $key);
            $this->assertStringContainsString('Needs your company first.', $row);
            $this->assertStringContainsString('href="'.route('onboarding.business').'"', $row);
        }

        // The personal tasks are not part of getting started any more.
        $this->assertStringNotContainsString('Add a profile picture', $html);
        $this->assertStringNotContainsString('Ask a first question', $html);

        // Every link in the block opens a page that exists for this person.
        foreach (GettingStarted::steps($user) as $step) {
            $this->get(strtok($step['url'], '#'))->assertOk();
        }
    }

    public function test_the_company_step_is_done_once_a_company_exists_and_the_rest_point_at_it(): void
    {
        $user = $this->newcomer();
        $team = $this->business($user);
        $steps = $this->steps($user);

        $this->assertTrue($steps['company']['done']);
        $this->assertSame('Northwind Finance is created', $steps['company']['title']);
        $this->assertSame(route('business.edit', $team), $steps['company']['url']);
        $this->assertSame(route('teams.show', $team).'#members', $steps['invite']['url']);
        $this->assertSame(route('onboarding.recommendations'), $steps['platforms']['url']);
        $this->assertSame(route('ecosystem.widget'), $steps['explore']['url']);
        $this->assertFalse($steps['essentials']['done']);
    }

    public function test_the_essentials_step_names_what_is_missing_and_is_done_when_it_is_all_in(): void
    {
        Storage::fake('local');
        $user = $this->newcomer();
        $team = $this->business($user);

        $note = $this->steps($user)['essentials']['note'];
        foreach (['a description', 'an industry', 'a way to contact it', 'a logo'] as $missing) {
            $this->assertStringContainsString($missing, $note);
        }

        $team->forceFill(['industry' => 'construction'])->save();
        $team->profileOrNew()->forceFill(['description' => 'We build and repair.', 'contact_email' => 'hello@northwind.test'])->save();
        $this->assertSame('Still to add: a logo. Nothing else is required.', $this->steps($user)['essentials']['note']);
        $this->assertFalse($this->steps($user)['essentials']['done']);

        $this->actingAs($user)->post('/business/'.$team->uuid.'/media', ['role' => 'logo', 'file' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertRedirect();
        $done = $this->steps($user)['essentials'];
        $this->assertTrue($done['done']);
        $this->assertStringContainsString('The essentials are in', $done['note']);
    }

    public function test_inviting_is_done_by_a_real_invitation_or_member(): void
    {
        $user = $this->newcomer();
        $this->assertFalse($this->steps($user)['invite']['done']);

        $team = $this->business($user);
        $this->assertFalse($this->steps($user)['invite']['done']);

        // An invitation that has gone out counts.
        $team->teamInvitations()->create(['email' => 'colleague@northwind.test', 'role' => 'member']);
        $this->assertTrue($this->steps($user)['invite']['done']);

        // So does a colleague who is already in the business.
        $second = $this->newcomer();
        $other = $this->business($second, 'Harbour Freight');
        $this->assertFalse($this->steps($second)['invite']['done']);
        $other->users()->attach($this->newcomer(), ['role' => 'member']);
        $this->assertTrue($this->steps($second)['invite']['done']);

        // A member with no say over the business is not asked to invite into it: for them it is still "create your company".
        $member = $this->newcomer();
        $team->users()->attach($member, ['role' => 'member']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $this->assertSame(route('onboarding.business'), $this->steps($member)['company']['url']);
        $this->assertFalse($this->steps($member)['company']['done']);
    }

    public function test_platforms_are_done_by_a_platform_the_company_holds_or_one_really_opened_and_exploring_by_two(): void
    {
        $user = $this->newcomer();
        $team = $this->business($user);
        $this->assertFalse($this->steps($user)['platforms']['done']);
        $this->assertFalse($this->steps($user)['explore']['done']);

        PlatformEntitlement::create(['team_id' => $team->id, 'platform_key' => 'tasks', 'status' => 'active', 'source' => 'free', 'access_mode' => 'everyone']);
        $this->assertTrue($this->steps($user)['platforms']['done']);
        $this->assertFalse($this->steps($user)['explore']['done'], 'one platform is a start, not exploring');

        PlatformEntitlement::create(['team_id' => $team->id, 'platform_key' => 'forms', 'status' => 'trial', 'source' => 'trial', 'access_mode' => 'everyone']);
        $this->assertTrue($this->steps($user)['explore']['done']);

        // The core platforms every company holds do not count.
        $core = $this->newcomer();
        $coreTeam = $this->business($core, 'Core Only');
        PlatformEntitlement::create(['team_id' => $coreTeam->id, 'platform_key' => 'files', 'status' => 'active', 'source' => 'core', 'access_mode' => 'everyone']);
        $this->assertFalse($this->steps($core)['platforms']['done']);

        // Opening a platform from InfoDot also counts.
        $opener = $this->newcomer();
        $this->business($opener, 'Opener Ltd');
        $opener->forceFill(['platform_opened_at' => now()])->save();
        $this->assertTrue($this->steps($opener)['platforms']['done']);
    }

    public function test_opening_a_platform_is_noted_once_when_the_person_is_really_sent_there(): void
    {
        config(['ecosystem.platforms.files.url' => 'https://files.infodot.co.za', 'ecosystem.platforms.files.active' => true]);
        $user = $this->newcomer();

        // Looking at the register is not opening a platform.
        $this->actingAs($user)->get(route('ecosystem.widget'))->assertOk();
        $this->assertNull($user->fresh()->platform_opened_at);

        // A platform that cannot be opened is not counted either.
        $this->actingAs($user)->get('/go/no-such-platform');
        $this->assertNull($user->fresh()->platform_opened_at);

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->actingAs($user)->get('/go/files')->assertOk();
        $first = $user->fresh()->platform_opened_at;
        $this->assertNotNull($first);

        // The first time is kept; later opens do not move it.
        Carbon::setTestNow('2026-11-14 10:00:00');
        $this->actingAs($user->fresh())->get('/go/files')->assertOk();
        $this->assertTrue($first->equalTo($user->fresh()->platform_opened_at));
        Carbon::setTestNow();
    }

    public function test_done_steps_show_done_and_the_count_follows(): void
    {
        $user = $this->newcomer();
        $this->business($user);

        $html = $this->actingAs($user->fresh())->get('/home')->assertOk()->assertSee('1 of 5 done')->getContent();

        $row = $this->row($html, 'company');
        $this->assertStringContainsString('class="is-done"', $row);
        $this->assertStringContainsString('<span class="ix-start__done">Done</span>', $row);
        foreach (['essentials', 'invite', 'platforms', 'explore'] as $key) {
            $this->assertStringNotContainsString('ix-start__done', $this->row($html, $key));
        }
    }

    public function test_the_block_is_absent_for_someone_who_has_done_everything(): void
    {
        Storage::fake('local');
        $user = $this->newcomer();
        $team = $this->business($user);
        $team->forceFill(['industry' => 'construction'])->save();
        $team->profileOrNew()->forceFill(['description' => 'Bookkeeping for small firms.', 'visibility' => TeamProfile::PUBLIC, 'contact_email' => 'hello@northwind.test'])->save();
        $this->actingAs($user)->post('/business/'.$team->uuid.'/media', ['role' => 'logo', 'file' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertRedirect();
        $team->teamInvitations()->create(['email' => 'colleague@northwind.test', 'role' => 'member']);
        foreach (['tasks', 'forms'] as $key) {
            PlatformEntitlement::create(['team_id' => $team->id, 'platform_key' => $key, 'status' => 'active', 'source' => 'free', 'access_mode' => 'everyone']);
        }

        $this->assertNotContains(false, array_map(fn ($s) => $s['done'], $this->steps($user)));
        $this->assertFalse(GettingStarted::showsFor($user->fresh()));
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('Getting started')->assertDontSee('ix-start', false);

        // Nothing was stored to make it go away, so it comes back if a step is undone.
        $this->assertNull($user->fresh()->getting_started_dismissed_at);
        $team->teamInvitations()->delete();
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertSee('Getting started')->assertSee('4 of 5 done');
    }

    public function test_putting_it_away_is_remembered_for_that_person_only(): void
    {
        Carbon::setTestNow('2026-11-13 09:00:00');
        $user = $this->newcomer();
        $other = $this->newcomer();

        $this->actingAs($user)->post(route('getting-started.dismiss'))->assertRedirect(route('dashboard'));
        $this->assertSame('2026-11-13 09:00:00', $user->fresh()->getting_started_dismissed_at->toDateTimeString());

        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('ix-start', false);

        // Still away on a later day, and a second press does not move the date.
        Carbon::setTestNow('2026-12-01 09:00:00');
        $this->actingAs($user->fresh())->post(route('getting-started.dismiss'))->assertRedirect(route('dashboard'));
        $this->assertSame('2026-11-13 09:00:00', $user->fresh()->getting_started_dismissed_at->toDateTimeString());
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('ix-start', false);
        Carbon::setTestNow();

        // Somebody else still has theirs, and the steps themselves were never stored.
        $this->assertNull($other->fresh()->getting_started_dismissed_at);
        $this->actingAs($other->fresh())->get('/home')->assertOk()->assertSee('Getting started');
        $this->assertFalse($this->steps($user)['company']['done']);
    }

    public function test_the_person_is_told_it_was_put_away_in_a_status_message(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->followingRedirects()->post(route('getting-started.dismiss'))->assertOk()
            ->assertSee('<p class="ix-alert" role="status">Getting started is put away.', false)
            ->assertDontSee('class="ix-start"', false);
    }

    public function test_nothing_is_shown_to_or_changed_by_a_guest(): void
    {
        $this->get('/home')->assertRedirect(route('login'));
        $this->post(route('getting-started.dismiss'))->assertRedirect(route('login'));
        // The member's checklist panel is not on the guest's page. (R-55: the marketing homepage has its own section called
        // "Getting started with InfoDot is simple.", so the check is for the panel itself, not for those words.)
        $this->get('/')->assertOk()->assertDontSee('id="start-h"', false)->assertDontSee('ix-start', false)->assertDontSee('Getting started is put away');

        // An unverified person is sent to verify first.
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post(route('getting-started.dismiss'))->assertRedirect(route('verification.notice'));
        $this->assertNull($unverified->fresh()->getting_started_dismissed_at);
    }

    public function test_verifying_an_email_lands_on_company_setup_with_a_confirmation_when_there_is_no_company_yet(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Nomvula Newcomer']);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($link)->assertRedirect(route('onboarding.business', ['verified' => 1]));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->actingAs($user->fresh())->get(route('onboarding.business', ['verified' => 1]))->assertOk()
            ->assertSee('Your email address is confirmed.')
            ->assertSee('Set up your company')->assertSee('Skip for now');

        // The confirmation is for that arrival only.
        $this->actingAs($user->fresh())->get(route('onboarding.business'))->assertOk()->assertDontSee('Your email address is confirmed.');
    }

    public function test_verifying_an_email_lands_on_home_with_the_block_for_someone_who_already_has_a_company(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Nomvula Newcomer']);
        $personal = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $business = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $business->id])->save();

        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($link)->assertRedirect(route('dashboard', ['verified' => 1]));
        $this->actingAs($user->fresh())->get(route('dashboard', ['verified' => 1]))->assertOk()
            ->assertSee('<p class="ix-alert" role="status">Your email address is confirmed.', false)
            ->assertSee('Getting started');
        $this->assertNotNull($personal);
    }

    public function test_the_two_columns_are_nullable_timestamps_and_the_migration_can_be_run_twice_and_back(): void
    {
        foreach (['getting_started_dismissed_at', 'platform_opened_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
            $this->assertNull(User::factory()->create()->fresh()->{$column});
        }

        $migration = require database_path('migrations/2026_11_13_120000_add_getting_started_to_users_table.php');
        $migration->up(); // guarded: a second run changes nothing and does not fail
        $this->assertTrue(Schema::hasColumn('users', 'getting_started_dismissed_at'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'getting_started_dismissed_at'));
        $this->assertFalse(Schema::hasColumn('users', 'platform_opened_at'));
        $migration->down(); // and so is taking it away twice

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'platform_opened_at'));
    }
}
