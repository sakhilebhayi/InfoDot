<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformEntitlement;
use App\Models\PlatformFeature;
use App\Services\Platforms\CatalogueAdmin;
use Illuminate\Auth\Access\AuthorizationException;

/** R-61: switching one feature of a platform off from InfoDot, without touching the platform, the data or any purchase. */
class PlatformFeatureControlsTest extends AdminCentreTestCase
{
    private const SECRET = 'tasks-service-secret-for-tests';

    public function test_the_ai_breakdown_feature_is_listed_and_available_to_begin_with(): void
    {
        $feature = PlatformFeature::where('platform_key', 'tasks')->where('key', 'ai_breakdown')->firstOrFail();

        $this->assertSame(['available', 'free'], [$feature->state, $feature->kind]);
        $this->assertNull($feature->unavailableMessage());
    }

    public function test_an_administrator_lists_a_feature_and_it_is_audited(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/features', ['key' => 'recruitment', 'name' => 'Recruitment workflows', 'kind' => 'paid', 'reason' => self::WHY])->assertSessionHasNoErrors();

        $feature = PlatformFeature::where('platform_key', 'hr')->where('key', 'recruitment')->firstOrFail();
        $this->assertSame(['available', 'paid'], [$feature->state, $feature->kind]);

        $event = PlatformCatalogueEvent::where('subject_type', 'feature')->sole();
        $this->assertSame([$admin->id, 'hr/recruitment', 'feature_registered'], [$event->actor_id, $event->subject_key, $event->type]);
        $this->actingAs($admin)->get('/admin/platforms/catalogue/hr')->assertOk()->assertSee('Recruitment workflows')->assertSee('Feature listed');
    }

    public function test_a_duplicate_a_bad_key_and_a_missing_reason_are_refused(): void
    {
        $admin = $this->administrator();
        $ok = ['key' => 'reports', 'name' => 'Advanced reports', 'kind' => 'free', 'reason' => self::WHY];

        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/features', $ok)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/features', $ok)->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/features', ['key' => 'Bad Key!', 'name' => 'X', 'kind' => 'free', 'reason' => self::WHY])->assertSessionHasErrors('refused');
        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/features', ['key' => 'other', 'name' => 'Other', 'kind' => 'free', 'reason' => 'short'])->assertSessionHasErrors('reason');

        $this->assertSame(1, PlatformFeature::where('platform_key', 'hr')->count());
    }

    public function test_switching_a_feature_off_changes_only_that_feature_and_is_audited(): void
    {
        $admin = $this->administrator();
        $this->company();
        $entitlements = PlatformEntitlement::count();
        $feature = PlatformFeature::where('key', 'ai_breakdown')->firstOrFail();

        $this->actingAs($admin)->post('/admin/platforms/catalogue/tasks/features/'.$feature->id, ['state' => 'disabled', 'reason' => self::WHY])->assertSessionHasNoErrors();

        $this->assertSame('disabled', $feature->fresh()->state);
        $this->assertSame('operational', PlatformCatalogue::where('key', 'tasks')->value('operational_status'), 'the platform stays on');
        $this->assertSame($entitlements, PlatformEntitlement::count(), 'no purchase or entitlement is touched');

        $event = PlatformCatalogueEvent::where('subject_type', 'feature')->sole();
        $this->assertSame(['feature_updated', 'tasks/ai_breakdown', self::WHY], [$event->type, $event->subject_key, $event->reason]);
        $this->assertSame(['state' => 'available'], $event->before);
        $this->assertSame(['state' => 'disabled'], $event->after);

        // Saving the same state again changes and records nothing.
        $this->actingAs($admin)->post('/admin/platforms/catalogue/tasks/features/'.$feature->id, ['state' => 'disabled', 'reason' => self::WHY])->assertSessionHasNoErrors();
        $this->assertSame(1, PlatformCatalogueEvent::where('subject_type', 'feature')->count());
    }

    public function test_a_feature_belongs_to_its_own_platform_and_the_states_are_checked(): void
    {
        $admin = $this->administrator();
        $feature = PlatformFeature::where('key', 'ai_breakdown')->firstOrFail();

        $this->actingAs($admin)->post('/admin/platforms/catalogue/hr/features/'.$feature->id, ['state' => 'disabled', 'reason' => self::WHY])->assertNotFound();
        $this->actingAs($admin)->post('/admin/platforms/catalogue/tasks/features/'.$feature->id, ['state' => 'exploded', 'reason' => self::WHY])->assertSessionHasErrors('state');
        $this->assertSame('available', $feature->fresh()->state);
    }

    public function test_only_a_platform_administrator_can_change_a_feature(): void
    {
        $feature = PlatformFeature::where('key', 'ai_breakdown')->firstOrFail();
        $payload = ['state' => 'disabled', 'reason' => self::WHY];

        $this->actingAs($this->moderator())->post('/admin/platforms/catalogue/tasks/features/'.$feature->id, $payload)->assertForbidden();
        $this->actingAs($this->company()->owner)->post('/admin/platforms/catalogue/tasks/features/'.$feature->id, $payload)->assertForbidden();

        $this->expectException(AuthorizationException::class);

        try {
            app(CatalogueAdmin::class)->setFeatureState($feature, 'disabled', $this->moderator(), self::WHY);
        } finally {
            $this->assertSame('available', $feature->fresh()->state);
        }
    }

    public function test_the_platform_api_tells_only_the_caller_its_own_features(): void
    {
        config(['ecosystem.service_secrets' => ['tasks' => self::SECRET, 'forms' => 'forms-service-secret-for-tests']]);
        $team = $this->company();
        $feature = PlatformFeature::where('key', 'ai_breakdown')->firstOrFail();
        $row = fn (string $caller, string $secret, string $key) => collect($this->withHeaders(['Authorization' => 'Bearer '.$secret])->getJson('/api/ecosystem/companies/'.$team->uuid.'/platforms?platform='.$caller)->assertOk()->json('platforms'))->firstWhere('key', $key);

        $this->assertSame(['ai_breakdown' => ['state' => 'available', 'message' => null]], $row('tasks', self::SECRET, 'tasks')['features']);

        $feature->update(['state' => 'disabled']);
        $answer = $row('tasks', self::SECRET, 'tasks')['features']['ai_breakdown'];
        $this->assertSame('disabled', $answer['state']);
        $this->assertStringContainsString('temporarily unavailable', $answer['message']);

        $this->assertArrayNotHasKey('features', $row('tasks', self::SECRET, 'forms'), 'another platform is not told');
        $this->assertSame([], $row('forms', 'forms-service-secret-for-tests', 'forms')['features'], 'a platform with nothing listed has nothing to switch');
    }

    public function test_each_state_has_wording_for_people_and_available_has_none(): void
    {
        $feature = new PlatformFeature(['name' => 'AI task breakdown']);

        foreach (['available' => false, 'disabled' => true, 'development' => true, 'retired' => true] as $state => $hasMessage) {
            $feature->state = $state;
            $this->assertSame($hasMessage, $feature->unavailableMessage() !== null, $state);
        }
    }
}
