<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-48 S-05. Production, 2026-11-13: six accounts, one business, no business profile, nothing in the directory.
 * Creating a business wrote only the team; it stayed hidden until its owner found the profile editor, wrote a
 * description in one form and chose "Anyone" in another, and nothing along the way said so. The owner's decision:
 * who can see the business is asked when it is created, with nothing pre-selected, and never assumed.
 */
class BusinessListingPathTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    /** @param array<string, mixed> $state */
    private function create(User $owner, array $state)
    {
        return Livewire::actingAs($owner)->test(CreateTeamForm::class)->set('state', $state)->call('createTeam');
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function business(User $owner): Team
    {
        return $owner->fresh()->ownedTeams()->where('personal_team', false)->latest('id')->firstOrFail();
    }

    public function test_a_business_created_for_anyone_with_a_description_is_listed_at_once(): void
    {
        $owner = $this->owner();

        $this->create($owner, ['name' => 'Karoo Boreholes', 'description' => 'We drill and test boreholes for farms.', 'visibility' => TeamProfile::PUBLIC])
            ->assertHasNoErrors();

        $team = $this->business($owner);
        $this->assertSame(TeamProfile::PUBLIC, $team->profile->visibility);
        $this->assertSame('We drill and test boreholes for farms.', $team->profile->description);
        $this->assertTrue($team->listing()['listed']);

        $this->signOut();
        $this->get('/businesses?all=1')->assertOk()->assertSee('Karoo Boreholes');
    }

    public function test_creating_a_business_goes_to_its_listing_and_says_where_it_stands(): void
    {
        $owner = $this->owner();

        $response = $this->create($owner, ['name' => 'Karoo Boreholes', 'description' => 'We drill boreholes.', 'visibility' => TeamProfile::PUBLIC]);

        $team = $this->business($owner);
        $response->assertRedirect(route('business.edit', $team).'#directory');
        $this->assertStringContainsString('is listed for anyone', (string) session('status'));
    }

    public function test_who_can_see_the_business_must_be_chosen_and_nothing_is_created_without_it(): void
    {
        $owner = $this->owner();

        $this->create($owner, ['name' => 'Karoo Boreholes', 'description' => 'We drill boreholes.'])->assertHasErrors('visibility');
        $this->create($owner, ['name' => 'Karoo Boreholes', 'visibility' => 'everyone'])->assertHasErrors('visibility');

        $this->assertSame(0, $owner->fresh()->ownedTeams()->where('personal_team', false)->count());
    }

    public function test_the_create_form_asks_the_question_with_nothing_pre_selected(): void
    {
        $html = $this->actingAs($this->owner())->get(route('teams.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Who can see the business', $html);
        $this->assertStringContainsString('What the business does', $html);
        $this->assertSame(3, substr_count($html, 'wire:model.defer="state.visibility"'));
        $this->assertStringNotContainsString('checked', strstr($html, 'Who can see the business') ? substr(strstr($html, 'Who can see the business'), 0, 1500) : '');
    }

    public function test_a_business_for_anyone_without_a_description_is_not_listed_and_is_told_why(): void
    {
        $owner = $this->owner();

        $this->create($owner, ['name' => 'Karoo Boreholes', 'visibility' => TeamProfile::PUBLIC])->assertHasNoErrors();
        $this->assertStringContainsString('Not listed yet', (string) session('status'));
        $this->assertStringNotContainsString('is listed for anyone', (string) session('status'));

        $team = $this->business($owner);
        $this->assertSame(['description'], $team->listing()['missing']);

        $html = $this->actingAs($owner->fresh())->get(route('business.edit', $team))->assertOk()->getContent();
        $this->assertStringContainsString('Not listed yet', $html);
        $this->assertStringContainsString('Say what the business does', $html);

        $this->signOut();
        $this->get('/businesses?all=1')->assertOk()->assertDontSee('Karoo Boreholes');
    }

    public function test_a_business_kept_to_its_own_team_stays_hidden_and_the_page_says_so(): void
    {
        $owner = $this->owner();

        $this->create($owner, ['name' => 'Karoo Boreholes', 'description' => 'We drill boreholes.', 'visibility' => TeamProfile::PRIVATE])->assertHasNoErrors();

        $team = $this->business($owner);
        $this->assertSame(['visibility'], $team->listing()['missing']);
        $html = $this->actingAs($owner->fresh())->get(route('business.edit', $team))->assertOk()->getContent();
        $this->assertStringContainsString('Not listed', $html);
        $this->assertStringContainsString('Choose Anyone or InfoDot users', $html);

        $this->actingAs(User::factory()->withPersonalTeam()->create())->get('/businesses?all=1')->assertDontSee('Karoo Boreholes');
        $this->signOut();
        $this->get('/businesses?all=1')->assertDontSee('Karoo Boreholes');
        $this->get(route('business.show', $team))->assertNotFound();
    }

    public function test_the_listing_form_saves_who_can_see_it_and_what_it_does_together(): void
    {
        $owner = $this->owner();
        $this->create($owner, ['name' => 'Karoo Boreholes', 'visibility' => TeamProfile::PRIVATE]);
        $team = $this->business($owner);

        $this->actingAs($owner->fresh())->post(route('business.directory', $team), ['visibility' => TeamProfile::PUBLIC, 'description' => 'We drill boreholes.'])
            ->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'is listed for anyone'));

        $this->assertTrue($team->fresh()->listing()['listed']);
        $html = $this->actingAs($owner->fresh())->get(route('business.edit', $team))->getContent();
        $form = fn (string $action) => preg_match('/<form[^>]*action="'.preg_quote($action, '/').'".*?<\/form>/s', $html, $m) ? $m[0] : '';
        $this->assertStringContainsString('id="description"', $form(route('business.directory', $team)), 'the description lives in the listing form');
        $this->assertStringNotContainsString('name="description"', $form(route('business.update', $team)), 'and no longer in the profile form');
        $this->assertSame(1, substr_count($html, 'id="description"'));
    }

    public function test_saving_for_anyone_with_no_description_does_not_claim_the_business_is_listed(): void
    {
        $owner = $this->owner();
        $this->create($owner, ['name' => 'Karoo Boreholes', 'visibility' => TeamProfile::PRIVATE]);
        $team = $this->business($owner);

        $this->actingAs($owner->fresh())->post(route('business.directory', $team), ['visibility' => TeamProfile::PUBLIC, 'description' => ''])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Not listed yet') && ! str_contains($s, 'is listed for anyone'));
    }

    public function test_saving_the_rest_of_the_profile_does_not_wipe_the_description(): void
    {
        $owner = $this->owner();
        $this->create($owner, ['name' => 'Karoo Boreholes', 'description' => 'We drill boreholes.', 'visibility' => TeamProfile::PUBLIC]);
        $team = $this->business($owner);

        $this->actingAs($owner->fresh())->post(route('business.update', $team), ['trading_name' => 'Karoo Drilling', 'location' => 'Beaufort West'])->assertRedirect();

        $profile = $team->fresh()->profile;
        $this->assertSame('We drill boreholes.', $profile->description);
        $this->assertSame('Karoo Drilling', $profile->trading_name);
        $this->assertTrue($team->fresh()->listing()['listed']);
    }

    public function test_a_business_that_never_chose_stays_unlisted(): void
    {
        $owner = $this->owner();
        $old = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Made Before The Question', 'personal_team' => false]);

        $this->assertNull($old->fresh()->profile);
        $this->assertFalse($old->fresh()->listing()['listed']);
        $this->get('/businesses?all=1')->assertDontSee('Made Before The Question');
    }

    public function test_the_directory_points_a_signed_in_person_to_adding_or_editing_their_business(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get('/businesses')->assertOk()->assertSee('Add your business')->assertSee(route('teams.create'), false);

        $this->create($owner, ['name' => 'Karoo Boreholes', 'visibility' => TeamProfile::PRIVATE]);
        $team = $this->business($owner);
        $this->actingAs($owner->fresh())->get('/businesses')->assertOk()->assertSee(route('business.edit', $team).'#directory', false);

        $this->signOut();
        $this->get('/businesses')->assertOk()->assertDontSee('Add your business');
    }
}
