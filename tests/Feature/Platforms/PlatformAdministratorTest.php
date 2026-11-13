<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogueEvent;
use Illuminate\Support\Facades\Route;

/**
 * The platform administrator (R-50): a role of its own, granted only from the host, audited, and not implied by
 * being a moderator or by owning a company.
 */
class PlatformAdministratorTest extends PlatformTestCase
{
    public function test_nobody_is_a_platform_administrator_by_default(): void
    {
        $team = $this->company();
        $moderator = $this->person();
        $moderator->forceFill(['is_moderator' => true])->save();

        $this->assertFalse($team->owner->isPlatformAdmin());
        $this->assertFalse($moderator->fresh()->isPlatformAdmin());
    }

    public function test_the_role_cannot_be_set_by_filling_the_account(): void
    {
        $person = $this->person();

        $person->fill(['is_platform_admin' => true])->save();
        $person->update(['is_platform_admin' => true]);

        $this->assertFalse($person->fresh()->isPlatformAdmin());
    }

    public function test_the_command_grants_and_revokes_and_writes_both_to_the_audit_trail(): void
    {
        $person = $this->person();

        $this->artisan('platform:admin', ['action' => 'grant', 'email' => $person->email])->expectsOutputToContain('is now a platform administrator')->assertSuccessful();
        $this->assertTrue($person->fresh()->isPlatformAdmin());

        $this->artisan('platform:admin', ['action' => 'grant', 'email' => $person->email])->expectsOutputToContain('already')->assertSuccessful();
        $this->assertSame(1, PlatformCatalogueEvent::count(), 'granting twice is one event');

        $this->artisan('platform:admin', ['action' => 'revoke', 'email' => $person->email])->assertSuccessful();
        $this->assertFalse($person->fresh()->isPlatformAdmin());

        $events = PlatformCatalogueEvent::orderBy('id')->get();
        $this->assertSame(['administrator_granted', 'administrator_revoked'], $events->pluck('type')->all());
        $this->assertSame(['administrator', $person->uuid], [$events[0]->subject_type, $events[0]->subject_key]);
        $this->assertSame([['is_platform_admin' => false], ['is_platform_admin' => true]], [$events[0]->before, $events[0]->after]);
        $this->assertNull($events[0]->actor_id, 'done from the command line');
    }

    public function test_the_command_changes_nothing_for_an_unknown_address_or_an_unknown_action(): void
    {
        $person = $this->person();

        $this->artisan('platform:admin', ['action' => 'grant', 'email' => 'nobody@example.test'])->assertFailed();
        $this->artisan('platform:admin', ['action' => 'promote', 'email' => $person->email])->assertFailed();

        $this->assertFalse($person->fresh()->isPlatformAdmin());
        $this->assertSame(0, PlatformCatalogueEvent::count());
    }

    public function test_the_middleware_lets_only_a_platform_administrator_through(): void
    {
        Route::middleware(['web', 'auth', 'platform.admin'])->get('/_test/platform-admin', fn () => 'in');

        $moderator = $this->person();
        $moderator->forceFill(['is_moderator' => true])->save();

        $this->get('/_test/platform-admin')->assertRedirect('/login');
        $this->actingAs($this->company()->owner)->get('/_test/platform-admin')->assertForbidden();
        $this->actingAs($moderator->fresh())->get('/_test/platform-admin')->assertForbidden();
        $this->actingAs($this->administrator())->get('/_test/platform-admin')->assertOk()->assertSee('in');
    }
}
