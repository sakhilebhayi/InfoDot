<?php

namespace Tests\Feature\Onboarding;

use App\Models\CompanyProfileImport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-62 phase 2: the company setup page and the "create another company" menu item both go through the capture flow. */
class CompanySetupFlowTest extends TestCase
{
    use RefreshDatabase;

    private function person(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true, 'name' => 'Personal']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'Dlamini Freight', 'industry' => 'logistics', 'description' => 'We move freight.', 'visibility' => 'private'];
    }

    public function test_setting_up_the_first_company_records_a_confirmed_manual_import_with_sources(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post(route('onboarding.business.store'), $this->payload(['stage' => 'running', 'objectives' => ['manage_money']]))->assertRedirect(route('onboarding.recommendations'));

        $team = Team::where('name', 'Dlamini Freight')->firstOrFail();
        $import = CompanyProfileImport::sole();
        $this->assertSame(['manual', 'confirmed', $team->id, $user->id], [$import->source, $import->status, $import->team_id, $import->user_id]);
        $profile = $team->profile()->first();
        $this->assertSame('running', $profile->stage);
        $this->assertSame(['manage_money'], $profile->objectives);
        $this->assertSame('manual', $profile->field_sources['description']['source']);
    }

    public function test_the_menu_item_opens_a_create_another_company_form_for_someone_who_already_has_one(): void
    {
        $user = $this->person();
        $this->actingAs($user)->post(route('onboarding.business.store'), $this->payload())->assertRedirect();

        // Without asking for another, the page sends a person who has a company home, as before.
        $this->actingAs($user->fresh())->get(route('onboarding.business'))->assertRedirect();

        $this->actingAs($user->fresh())->get(route('onboarding.business', ['another' => 1]))->assertOk()
            ->assertSee('Create another company')->assertSee('name="another"', false)->assertDontSee('Skip for now');

        $this->actingAs($user->fresh())->post(route('onboarding.business.store'), $this->payload(['name' => 'Second Venture Ltd', 'another' => 1]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(2, $user->fresh()->ownedTeams()->where('personal_team', false)->count());
        $this->assertSame('Second Venture Ltd', $user->fresh()->currentTeam->name, 'the new company is the active one');
        $this->assertSame(2, CompanyProfileImport::where('status', 'confirmed')->count());
    }

    public function test_a_second_tap_on_create_another_asks_whether_it_is_a_new_company_and_creates_nothing_until_confirmed(): void
    {
        $user = $this->person();
        $this->actingAs($user)->post(route('onboarding.business.store'), $this->payload())->assertRedirect();

        $response = $this->actingAs($user->fresh())->post(route('onboarding.business.store'), $this->payload(['another' => 1]));

        $response->assertOk()->assertSee('Is this a new company?')->assertSee('Dlamini Freight')->assertSee('name="confirm_duplicate"', false);
        $this->assertSame(1, $user->fresh()->ownedTeams()->where('personal_team', false)->count());

        // Saying it is different creates it, from the same import.
        $draft = CompanyProfileImport::where('status', 'draft')->sole();
        $this->actingAs($user->fresh())->post(route('onboarding.business.store'), $this->payload(['another' => 1, 'confirm_duplicate' => 1, 'import' => $draft->id]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame(2, $user->fresh()->ownedTeams()->where('personal_team', false)->count());
        $this->assertSame('confirmed', $draft->fresh()->status);
        $this->assertSame(0, CompanyProfileImport::where('status', 'draft')->count());
    }

    public function test_another_persons_import_cannot_be_resumed(): void
    {
        $user = $this->person();
        $other = $this->person();
        $theirs = CompanyProfileImport::create(['user_id' => $other->id, 'source' => 'manual', 'status' => 'draft']);

        $this->actingAs($user)->post(route('onboarding.business.store'), $this->payload(['import' => $theirs->id]))->assertRedirect();

        $this->assertSame('draft', $theirs->fresh()->status, 'their import was not used');
        $this->assertNull($theirs->fresh()->team_id);
    }

    public function test_the_growth_centre_counts_how_companies_were_set_up(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $user = $this->person();
        $this->actingAs($user)->post(route('onboarding.business.store'), $this->payload())->assertRedirect();
        CompanyProfileImport::create(['user_id' => $user->id, 'source' => 'website', 'status' => 'draft']);

        $this->actingAs($admin)->get('/admin/growth/funnel')->assertOk()
            ->assertSee('How companies were set up')->assertSee('Typed by hand')->assertSee('Read from a website')->assertSee('Read from a document');
    }
}
