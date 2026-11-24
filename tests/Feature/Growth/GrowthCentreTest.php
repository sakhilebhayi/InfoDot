<?php

namespace Tests\Feature\Growth;

use App\Models\AcquisitionTouch;
use App\Models\GrowthTarget;
use App\Models\User;
use App\Services\Growth\GrowthMetrics;
use App\Services\Growth\OnboardingEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthCentreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
    }

    public function test_only_platform_administrators_see_it(): void
    {
        $this->get('/admin/growth')->assertRedirect();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get('/admin/growth')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/growth')->assertOk()->assertSee('Not yet measured');
    }

    public function test_every_tab_renders_with_real_counts_and_no_invented_figures(): void
    {
        $admin = $this->admin();
        $person = User::factory()->create();
        app(OnboardingEvents::class)->record($person, 'account_created');
        AcquisitionTouch::create(['user_id' => $person->id, 'channel' => 'referral', 'touched_at' => now(), 'created_at' => now()]);

        foreach (['overview', 'funnel', 'channels', 'geography', 'platforms', 'targets'] as $tab) {
            $this->actingAs($admin)->get('/admin/growth/'.$tab)->assertOk();
        }

        $this->actingAs($admin)->get('/admin/growth/channels')->assertSee('Referral')->assertSee('100%');
        $this->actingAs($admin)->get('/admin/growth/funnel')->assertSee('Account created');
    }

    public function test_a_fresh_system_shows_zero_and_never_a_made_up_number(): void
    {
        $overview = app(GrowthMetrics::class)->overview(null, null);
        $this->assertSame(0, $overview['businesses']);
        $this->assertSame([], app(GrowthMetrics::class)->channels(null, null)['rows']);
        $this->assertSame([], app(GrowthMetrics::class)->platforms());
        foreach (app(GrowthMetrics::class)->targets() as $t) {
            $this->assertNull($t['target']);
            $this->assertNull($t['progress']);
        }
    }

    public function test_targets_are_saved_apart_from_actuals(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/growth/targets', ['targets' => ['businesses' => ['value' => 1000, 'date' => '2027-12-31']]])->assertRedirect();

        $this->assertSame(1000, GrowthTarget::where('metric', 'businesses')->value('value'));
        $row = app(GrowthMetrics::class)->targets()['businesses'];
        $this->assertSame(1000, $row['target']);
        $this->assertSame(0, $row['actual']);

        $this->actingAs($admin)->post('/admin/growth/targets', ['targets' => ['businesses' => ['value' => '']]]);
        $this->assertSame(0, GrowthTarget::count());
    }

    public function test_unmeasured_figures_are_declared(): void
    {
        $this->assertArrayHasKey('Monthly recurring revenue', app(GrowthMetrics::class)->unmeasured());
    }
}
