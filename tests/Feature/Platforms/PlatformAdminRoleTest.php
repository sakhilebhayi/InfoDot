<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogueEvent;
use App\Services\Accounts\AccountSuspension;
use App\Services\Platforms\PlatformAdministrators;
use Illuminate\Support\Facades\Gate;
use LogicException;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

/**
 * The platform administrator role (R-50 step 3): given and taken away only by a command on the host, refused for an
 * account that is unverified or suspended, written to the audit trail, and reachable from no screen.
 */
class PlatformAdminRoleTest extends AdminCentreTestCase
{
    public function test_the_grant_and_revoke_commands_change_the_role_and_write_the_audit_trail(): void
    {
        $person = $this->person();

        $this->artisan('platform-admin:grant', ['email' => $person->email])->expectsOutputToContain('is now a platform administrator')->assertSuccessful();
        $this->assertTrue($person->fresh()->isPlatformAdmin());
        $this->assertTrue(Gate::forUser($person->fresh())->allows('administer-platforms'));

        $this->artisan('platform-admin:grant', ['email' => $person->email])->expectsOutputToContain('already')->assertSuccessful();

        $this->artisan('platform-admin:revoke', ['email' => $person->email])->expectsOutputToContain('no longer')->assertSuccessful();
        $this->assertFalse($person->fresh()->isPlatformAdmin());
        $this->artisan('platform-admin:revoke', ['email' => $person->email])->expectsOutputToContain('Nothing was changed')->assertSuccessful();

        $events = PlatformCatalogueEvent::orderBy('id')->get();
        $this->assertSame(['administrator_granted', 'administrator_revoked'], $events->pluck('type')->all());
        $this->assertSame($person->uuid, $events[0]->subject_key);
        $this->assertNull($events[0]->actor_id);
        $this->assertNotEmpty($events[0]->reason);
    }

    public function test_an_unverified_account_is_refused(): void
    {
        $person = $this->person(['email_verified_at' => null]);

        foreach (['platform-admin:grant' => ['email' => $person->email], 'platform:admin' => ['action' => 'grant', 'email' => $person->email]] as $command => $arguments) {
            $this->artisan($command, $arguments)->expectsOutputToContain('not verified')->assertFailed();
        }

        $this->assertFalse($person->fresh()->isPlatformAdmin());
        $this->assertSame(0, PlatformCatalogueEvent::count());
    }

    public function test_a_suspended_account_is_refused_and_a_suspended_administrator_loses_the_gate(): void
    {
        $person = $this->person();
        app(AccountSuspension::class)->suspend($person, 'Abuse reported and confirmed.');

        $this->artisan('platform-admin:grant', ['email' => $person->email])->expectsOutputToContain('suspended')->assertFailed();
        $this->assertFalse($person->fresh()->isPlatformAdmin());

        $administrator = $this->administrator();
        $this->assertTrue(Gate::forUser($administrator)->allows('administer-platforms'));
        app(AccountSuspension::class)->suspend($administrator, 'Account taken over, under review.');
        $this->assertFalse(Gate::forUser($administrator->fresh())->allows('administer-platforms'));
    }

    public function test_an_unknown_address_changes_nothing(): void
    {
        $this->artisan('platform-admin:grant', ['email' => 'nobody@example.test'])->assertFailed();
        $this->artisan('platform-admin:revoke', ['email' => 'nobody@example.test'])->assertFailed();
        $this->assertSame(0, PlatformCatalogueEvent::count());
    }

    public function test_being_a_moderator_an_owner_or_a_member_is_not_the_role(): void
    {
        $team = $this->company();

        foreach ([$this->moderator(), $team->owner, $this->memberOf($team, 'admin'), $this->memberOf($team)] as $person) {
            $this->assertFalse(Gate::forUser($person)->allows('administer-platforms'));
        }
    }

    public function test_the_role_service_refuses_to_run_inside_a_web_request(): void
    {
        $person = $this->person();
        $console = new ReflectionProperty($this->app, 'isRunningInConsole');
        $console->setValue($this->app, false);

        try {
            app(PlatformAdministrators::class)->grant($person);
            $this->fail('The role was granted outside the command line.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('command line', $e->getMessage());
        } finally {
            $console->setValue($this->app, true);
        }

        $this->assertFalse($person->fresh()->isPlatformAdmin());
    }

    public function test_nothing_a_browser_can_reach_writes_the_role(): void
    {
        // The column is written in one place, and that place is called only by the three commands and the trait they share.
        $writers = [];
        $callers = [];

        foreach ((new Finder)->files()->in([app_path(), base_path('routes'), resource_path('views')])->name(['*.php']) as $file) {
            $path = str_replace(base_path().'/', '', $file->getPathname());
            $code = $file->getContents();

            if (preg_match('/[\'"]is_platform_admin[\'"]\s*=>/', $code) && ! str_contains($path, 'app/Models/User.php')) {
                $writers[] = $path;
            }

            if (str_contains($code, 'PlatformAdministrators') && $path !== 'app/Services/Platforms/PlatformAdministrators.php') {
                $callers[] = $path;
            }
        }

        sort($callers);
        $this->assertSame(['app/Services/Platforms/PlatformAdministrators.php'], $writers);
        $this->assertSame(['app/Console/Commands/Concerns/ChangesPlatformAdministrator.php', 'app/Console/Commands/PlatformAdmin.php', 'app/Console/Commands/PlatformAdminGrant.php', 'app/Console/Commands/PlatformAdminRevoke.php'], $callers);

        // And posting the field to the account form, as an administrator would be tempted to, changes nothing.
        $person = $this->person();
        $this->actingAs($person)->put('/user/profile-information', ['name' => $person->name, 'email' => $person->email, 'is_platform_admin' => 1]);
        $this->assertFalse($person->fresh()->isPlatformAdmin());

        // No admin centre route mentions the role.
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'admin.platforms')) {
                $this->assertStringNotContainsString('administrator', $route->uri());
            }
        }
    }
}
