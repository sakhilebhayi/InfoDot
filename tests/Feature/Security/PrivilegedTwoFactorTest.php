<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * R-72 (S-02): moderator and platform-administrator powers work only while the person signs in with two-factor. Without it the role
 * is still theirs, a page visit sends them to turn two-factor on with the reason, and nothing can be done on a password alone.
 */
class PrivilegedTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function person(array $attributes): User
    {
        return User::factory()->create(['email_verified_at' => now()] + $attributes);
    }

    private function secured(): array
    {
        return ['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()];
    }

    public function test_a_moderator_without_two_factor_holds_the_role_but_has_no_powers(): void
    {
        $moderator = $this->person(['is_moderator' => true]);

        $this->assertTrue($moderator->hasModeratorRole());
        $this->assertFalse($moderator->hasSecuredSignIn());
        $this->assertFalse($moderator->isModerator());

        // A secret that was never confirmed is not two-factor.
        $moderator->forceFill(['two_factor_secret' => encrypt('x')])->save();
        $this->assertFalse($moderator->fresh()->isModerator());
    }

    public function test_moderation_pages_send_them_to_turn_two_factor_on_and_actions_are_refused(): void
    {
        $moderator = $this->person(['is_moderator' => true]);
        $other = $this->person([]);

        $this->actingAs($moderator)->get(route('moderation.accounts'))
            ->assertRedirect(route('profile.edit').'#two-factor')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Turn on two-factor sign-in to use moderation'));

        $this->post(route('moderation.accounts.suspend', $other), ['reason' => 'Testing the refusal.'])->assertForbidden();
        $this->assertFalse($other->fresh()->isSuspended());

        // The settings page says why they were sent there.
        $this->withSession(['status' => 'Turn on two-factor sign-in to use moderation.'])->get(route('profile.edit'))
            ->assertOk()->assertSee('Turn on two-factor sign-in to use moderation.');
    }

    public function test_with_two_factor_on_the_moderation_pages_open(): void
    {
        $moderator = $this->person(['is_moderator' => true] + $this->secured());

        $this->actingAs($moderator)->get(route('moderation.accounts'))->assertOk();
    }

    public function test_a_platform_administrator_without_two_factor_cannot_administer(): void
    {
        $admin = $this->person(['is_platform_admin' => true]);

        $this->assertTrue($admin->hasPlatformAdminRole());
        $this->assertFalse(Gate::forUser($admin)->allows('administer-platforms'));

        $this->actingAs($admin)->get(route('admin.platforms'))
            ->assertRedirect(route('profile.edit').'#two-factor')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'platform administration'));

        $admin->forceFill($this->secured())->save();
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('administer-platforms'));
        $this->actingAs($admin->fresh())->get(route('admin.platforms'))->assertOk();
    }

    public function test_people_without_the_role_are_still_simply_refused(): void
    {
        $this->actingAs($this->person($this->secured()))->get(route('moderation.accounts'))->assertForbidden();
        $this->actingAs($this->person($this->secured()))->get(route('admin.platforms'))->assertForbidden();
    }
}
