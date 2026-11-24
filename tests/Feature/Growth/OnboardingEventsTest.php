<?php

namespace Tests\Feature\Growth;

use App\Models\OnboardingEvent;
use App\Models\User;
use App\Services\Growth\OnboardingEvents;
use App\Services\Growth\OnboardingFunnel;
use App\Services\Onboarding\PlatformRecommender;
use App\Support\BusinessTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_step_is_recorded_once(): void
    {
        $user = User::factory()->create();
        $events = app(OnboardingEvents::class);

        $events->record($user, 'account_created');
        $events->record($user, 'account_created');
        $events->record($user, 'not_a_step');

        $this->assertSame(1, OnboardingEvent::where('user_id', $user->id)->count());
    }

    public function test_business_form_saves_aims_and_validates_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.business.store'), [
            'name' => 'Acme Traders', 'visibility' => 'private', 'description' => 'We trade.',
            'stage' => 'running', 'size_band' => '2-5', 'objectives' => ['manage_money', 'manage_people'],
        ])->assertRedirect(route('onboarding.recommendations'));

        $profile = $user->fresh()->ownedTeams()->where('personal_team', false)->first()->profile;
        $this->assertSame(['manage_money', 'manage_people'], $profile->objectives);
        $this->assertSame('running', $profile->stage);

        $steps = OnboardingEvent::where('user_id', $user->id)->pluck('step')->all();
        foreach (['business_created', 'profile_established', 'objectives_set'] as $step) {
            $this->assertContains($step, $steps);
        }
    }

    public function test_invalid_aims_are_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.business.store'), [
            'name' => 'Acme', 'visibility' => 'private', 'objectives' => ['rule_the_world'],
        ])->assertSessionHasErrors('objectives.0');
    }

    public function test_recommender_is_deterministic_explained_and_limited(): void
    {
        $recommender = app(PlatformRecommender::class);
        $a = $recommender->recommend(['manage_money', 'organise_work'], 'running', '2-5');
        $b = $recommender->recommend(['manage_money', 'organise_work'], 'running', '2-5');

        $this->assertSame($a, $b);
        $this->assertLessThanOrEqual((int) config('onboarding.max_recommendations'), count($a));
        foreach ($a as $item) {
            $this->assertNotEmpty($item['why']);
            $this->assertTrue(BusinessTools::shown($item['key']));
        }
    }

    public function test_recommender_falls_back_to_starter_set(): void
    {
        $out = app(PlatformRecommender::class)->recommend([], null, null);
        foreach ($out as $item) {
            $this->assertContains($item['key'], config('onboarding.starter'));
        }
    }

    public function test_recommendations_page_needs_a_business_and_records_once(): void
    {
        $user = User::factory()->create();
        $this->get(route('onboarding.recommendations'))->assertRedirect();

        $team = $user->ownedTeams()->create(['name' => 'Acme', 'personal_team' => false]);
        $team->profile()->create(['visibility' => 'private', 'objectives' => ['manage_money']]);

        $this->actingAs($user)->get(route('onboarding.recommendations'))->assertOk();
        $this->actingAs($user)->get(route('onboarding.recommendations'))->assertOk();

        $this->assertSame(1, OnboardingEvent::where('step', 'platforms_recommended')->count());
    }

    public function test_funnel_counts_people_not_events(): void
    {
        $events = app(OnboardingEvents::class);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $events->record($a, 'account_created');
        $events->record($b, 'account_created');
        $events->record($a, 'email_verified');

        $steps = collect(app(OnboardingFunnel::class)->steps())->keyBy('step');
        $this->assertSame(2, $steps['account_created']['people']);
        $this->assertSame(1, $steps['email_verified']['people']);
        $this->assertSame(50.0, $steps['email_verified']['of_parent']);
    }

    public function test_no_funnel_share_exceeds_one_hundred_percent(): void
    {
        $events = app(OnboardingEvents::class);
        $user = User::factory()->create();
        $events->record($user, 'account_created');
        $events->record($user, 'platform_opened'); // opened a platform without a business step on record

        foreach (app(OnboardingFunnel::class)->steps() as $row) {
            $this->assertTrue($row['of_parent'] === null || $row['of_parent'] <= 100.0);
        }
    }
}
