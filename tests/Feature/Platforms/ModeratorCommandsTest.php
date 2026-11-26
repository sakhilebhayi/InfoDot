<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogueEvent;
use App\Services\Platforms\Moderators;
use LogicException;
use ReflectionProperty;

class ModeratorCommandsTest extends PlatformTestCase
{
    public function test_grant_and_revoke_work_and_are_audited(): void
    {
        $person = $this->person();
        $other = $this->person();
        $other->forceFill(['is_moderator' => true, 'two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();

        $this->artisan('moderator:grant', ['email' => $person->email])->expectsOutputToContain('is now a moderator')
            ->expectsOutputToContain('only once they turn on two-factor')->assertSuccessful();
        // R-72: the role is granted, but it works only with two-factor sign-in on.
        $this->assertTrue($person->fresh()->hasModeratorRole());
        $this->assertFalse($person->fresh()->isModerator());
        $person->forceFill(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->assertTrue($person->fresh()->isModerator());

        $this->artisan('moderator:grant', ['email' => $person->email])->expectsOutputToContain('already')->assertSuccessful();
        $this->assertSame(1, PlatformCatalogueEvent::count(), 'granting twice is one event');

        $this->artisan('moderator:revoke', ['email' => $person->email])->expectsOutputToContain('no longer a moderator')->assertSuccessful();
        $this->assertFalse($person->fresh()->isModerator());

        $events = PlatformCatalogueEvent::orderBy('id')->get();
        $this->assertSame(['moderator_granted', 'moderator_revoked'], $events->pluck('type')->all());
        $this->assertSame(['moderator', $person->uuid], [$events[0]->subject_type, $events[0]->subject_key]);
        $this->assertSame([['is_moderator' => false], ['is_moderator' => true]], [$events[0]->before, $events[0]->after]);
        $this->assertNull($events[0]->actor_id);
    }

    public function test_unknown_unverified_and_suspended_accounts_are_refused(): void
    {
        $this->artisan('moderator:grant', ['email' => 'nobody@example.test'])->assertFailed();

        $unverified = $this->person();
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->artisan('moderator:grant', ['email' => $unverified->email])->expectsOutputToContain('not verified')->assertFailed();
        $this->assertFalse($unverified->fresh()->isModerator());
        $this->assertSame(0, PlatformCatalogueEvent::count());
    }

    public function test_the_only_moderator_cannot_be_removed_without_force(): void
    {
        $only = $this->person();
        $only->forceFill(['is_moderator' => true, 'two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();

        $this->artisan('moderator:revoke', ['email' => $only->email])->expectsOutputToContain('only moderator')->assertFailed();
        $this->assertTrue($only->fresh()->isModerator());

        $this->artisan('moderator:revoke', ['email' => $only->email, '--force' => true])->assertSuccessful();
        $this->assertFalse($only->fresh()->isModerator());
    }

    public function test_the_trail_page_names_the_new_events_and_the_list_command_shows_both_roles(): void
    {
        $admin = $this->administrator();
        $person = $this->person();
        $this->artisan('moderator:grant', ['email' => $person->email])->assertSuccessful();

        $this->actingAs($admin)->get(route('admin.platforms.audit'))->assertOk()->assertSee('Moderator role granted');

        $this->artisan('roles:list')->expectsOutputToContain($person->email)->assertSuccessful();
    }

    public function test_the_service_refuses_to_run_inside_a_web_request(): void
    {
        $person = $this->person();
        $console = new ReflectionProperty($this->app, 'isRunningInConsole');
        $console->setValue($this->app, false);

        try {
            app(Moderators::class)->grant($person);
            $this->fail('The role was granted outside the command line.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('command line', $e->getMessage());
        } finally {
            $console->setValue($this->app, true);
        }

        $this->assertFalse($person->fresh()->isModerator());
        $this->assertSame(0, PlatformCatalogueEvent::count());
    }
}
