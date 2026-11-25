<?php

namespace Tests\Feature\Growth;

use App\Models\CompanyProfileImport;
use App\Models\OnboardingEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\Growth\OnboardingEvents;
use App\Services\Growth\OnboardingInsights;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Tests\TestCase;

/** R-62 phase 6: the extra onboarding figures are counted from records, and say "not yet" when there is nothing behind them. */
class OnboardingInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function insights(): OnboardingInsights
    {
        return app(OnboardingInsights::class);
    }

    public function test_signing_in_is_a_step_and_the_day_is_kept_once(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));
        event(new Login('web', $user, false));

        $this->assertSame(1, OnboardingEvent::where('user_id', $user->id)->where('step', 'login_completed')->count());
        $this->assertSame(1, DB::table('user_login_days')->where('user_id', $user->id)->count());
    }

    public function test_joining_a_company_is_recorded_for_the_person_and_the_company(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $person = User::factory()->create();

        event(new TeamMemberAdded($team, $person));

        $this->assertDatabaseHas('onboarding_events', ['user_id' => $person->id, 'step' => 'company_joined', 'team_id' => $team->id]);
    }

    public function test_where_people_stopped_is_the_last_step_each_reached(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $b->id, 'personal_team' => false]);
        $events = app(OnboardingEvents::class);
        $events->record($a, 'account_created');
        $events->record($b, 'account_created');
        $events->record($b, 'business_created', $team);

        $rows = collect($this->insights()->stoppedAt())->pluck('people', 'step');

        $this->assertSame(1, $rows['account_created']);
        $this->assertSame(1, $rows['business_created']);
        $this->assertSame(0, $rows['email_verified']);
    }

    public function test_time_to_first_company_is_the_median_and_says_not_yet_with_no_one(): void
    {
        $this->assertSame(['people' => 0, 'median_minutes' => null], $this->insights()->timeToFirstCompany());

        foreach ([10, 30, 200] as $minutes) {
            $u = User::factory()->create(['created_at' => now()->subDays(2)]);
            Team::factory()->create(['user_id' => $u->id, 'personal_team' => false, 'created_at' => now()->subDays(2)->addMinutes($minutes)]);
        }
        // a personal workspace is not a company
        $p = User::factory()->create(['created_at' => now()->subDays(2)]);
        Team::factory()->create(['user_id' => $p->id, 'personal_team' => true, 'created_at' => now()->subDays(2)->addMinute()]);

        $this->assertSame(['people' => 3, 'median_minutes' => 30], $this->insights()->timeToFirstCompany());
    }

    public function test_website_fields_count_found_kept_and_changed(): void
    {
        $user = User::factory()->create();
        CompanyProfileImport::create(['user_id' => $user->id, 'source' => 'website', 'status' => 'confirmed',
            'extracted' => ['name' => ['value' => 'Dlamini Freight', 'confidence' => 0.9, 'source' => 'website'], 'website' => ['value' => 'https://d.example', 'confidence' => 1, 'source' => 'website']],
            'confirmed' => ['name' => 'Dlamini Freight (Pty) Ltd', 'website' => 'https://d.example']]);
        CompanyProfileImport::create(['user_id' => $user->id, 'source' => 'website', 'status' => 'abandoned',
            'extracted' => ['name' => ['value' => 'Other', 'confidence' => 0.5, 'source' => 'website']]]);
        CompanyProfileImport::create(['user_id' => $user->id, 'source' => 'manual', 'status' => 'confirmed', 'confirmed' => ['name' => 'X']]);

        $rows = collect($this->insights()->websiteFields())->keyBy('field');

        $this->assertSame(['field' => 'name', 'found' => 2, 'kept' => 0, 'changed' => 1], $rows['name']);
        $this->assertSame(['field' => 'website', 'found' => 1, 'kept' => 1, 'changed' => 0], $rows['website']);
    }

    public function test_retention_counts_only_people_who_signed_in_again_after_the_window(): void
    {
        $this->assertNull($this->insights()->retention()['since']);

        $back = User::factory()->create(['created_at' => now()->subDays(10)]);
        $gone = User::factory()->create(['created_at' => now()->subDays(10)]);
        DB::table('user_login_days')->insert([
            ['user_id' => $back->id, 'day' => now()->subDays(10)->toDateString()],
            ['user_id' => $back->id, 'day' => now()->subDays(2)->toDateString()],
            ['user_id' => $gone->id, 'day' => now()->subDays(10)->toDateString()],
        ]);

        $windows = collect($this->insights()->retention()['windows'])->keyBy('days');

        $this->assertSame([2, 1, 50.0], [$windows[1]['cohort'], $windows[1]['returned'], $windows[1]['share']]);
        $this->assertSame([2, 1], [$windows[7]['cohort'], $windows[7]['returned']]);
        $this->assertSame(0, $windows[30]['cohort']);
        $this->assertNull($windows[30]['share']);
    }

    public function test_the_funnel_tab_shows_the_new_panels_and_nothing_invented(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)->get('/admin/growth/funnel')->assertOk()
            ->assertSee('Where people stopped')->assertSee('Time from account to first company')->assertSee('What reading a website found')->assertSee('People coming back')
            ->assertSee('Not yet. No one in this range has created a company.');
    }

    public function test_home_puts_the_company_first_and_asks_a_person_without_one_to_set_one_up(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $personal = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => true]);
        $owner->forceFill(['current_team_id' => $personal->id])->save();

        $this->actingAs($owner)->get('/home')->assertOk()->assertSee('data-company-summary', false)->assertSee('Set up your company');

        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Dlamini Freight']);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($owner->fresh())->get('/home')->assertOk()->assertSee('Dlamini Freight')->assertSee('No platforms switched on yet')->assertDontSee('Set up your company');
    }
}
