<?php

namespace Tests\Feature\Platforms;

use App\Livewire\DotSwitcher;
use App\Livewire\EcosystemWidget;
use App\Livewire\PlatformLink;
use App\Models\DotPlatform;
use App\Models\PlatformCatalogue;
use App\Models\Resource;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

/**
 * Every door to a platform asks the same question (R-50 section 5). A door that was missed would make the others
 * cosmetic, so each one is tried here with a company that does not hold the platform and with one that does.
 */
class PlatformDoorsTest extends PlatformTestCase
{
    private const SECRET = 'forms-service-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ecosystem.service_secrets' => ['forms' => self::SECRET, 'files' => 'files-service-secret-for-tests', 'memory' => 'memory-service-secret-for-tests', 'mines' => 'mines-service-secret-for-tests']]);
    }

    private function redeem(User $user, string $platform, ?string $secret = null)
    {
        $token = $user->createToken(EcosystemHandoff::tokenName($platform), ['ecosystem:read'], now()->addMinute())->plainTextToken;

        return $this->withHeaders(['Authorization' => 'Bearer '.($secret ?? config('ecosystem.service_secrets.'.$platform))])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => $platform]);
    }

    // ---- launchForm and launchUrl ----

    public function test_launch_form_mints_no_code_for_a_platform_the_company_does_not_hold(): void
    {
        $team = $this->company();

        $this->assertNull(EcosystemHandoff::launchForm($team->owner, 'forms'));
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        $this->activatePlatforms($team, 'forms');

        $this->assertSame('https://forms.infodot.co.za/auth/ecosystem', EcosystemHandoff::launchForm($team->owner, 'forms')['action']);
    }

    public function test_launch_url_still_returns_a_url_for_a_refused_platform_and_it_is_the_explanation(): void
    {
        $team = $this->company();

        $this->assertSame(route('platform.access', ['platform' => 'forms']), EcosystemHandoff::launchUrl($team->owner, 'forms'));
        $this->assertSame(route('platform.access', ['platform' => 'forms', 'redirect' => '/f/12']), EcosystemHandoff::launchUrl($team->owner, 'forms', '/f/12'));
        $this->assertSame(route('platform.access', ['platform' => 'forms']), EcosystemHandoff::launchUrl($team->owner, 'forms', 'https://evil.example'));

        $this->activatePlatforms($team, 'forms');

        $this->assertSame(route('platform.launch', ['platform' => 'forms', 'redirect' => '/f/12']), EcosystemHandoff::launchUrl($team->owner, 'forms', '/f/12'));
    }

    public function test_launch_url_is_still_null_for_a_platform_that_really_cannot_be_opened(): void
    {
        $team = $this->company();
        config(['ecosystem.platforms.forms.active' => false]);

        $this->assertNull(EcosystemHandoff::launchUrl($team->owner, 'forms'));
        $this->assertNull(EcosystemHandoff::launchUrl($team->owner, 'no-such-platform'));
    }

    public function test_finance_always_opens_whatever_the_company_holds_so_the_seats_button_never_breaks(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $person = $this->person();

        foreach ([$team->owner, $member, $person] as $who) {
            $this->assertSame(route('platform.launch', ['platform' => 'finance', 'redirect' => '/seats']), EcosystemHandoff::launchUrl($who, 'finance', '/seats'));
            $this->actingAs($who)->get('/go/finance?redirect=/seats')->assertOk()->assertSee('https://finance.infodot.co.za/auth/ecosystem', false)->assertSee('name="redirect" value="/seats"', false);
        }

        Gate::forUser($team->owner)->authorize('changeLicence', $team);
    }

    // ---- /go/{platform} ----

    public function test_go_explains_a_refusal_with_403_and_keeps_503_for_real_unavailability(): void
    {
        $team = $this->company();

        $response = $this->actingAs($team->owner)->get('/go/forms?redirect=/f/12');

        $response->assertStatus(403)->assertSee($team->name.' has not switched on Dot.Forms')->assertSee('free while its pricing is being finalised')
            ->assertSee('name="redirect" value="/f/12"', false)->assertSee('name="company" value="'.$team->uuid.'"', false)
            ->assertDontSee('cannot be opened right now')->assertDontSee('auth/ecosystem');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        config(['ecosystem.platforms.forms.active' => false]);
        $this->actingAs($team->owner)->get('/go/forms')->assertStatus(503)->assertSee('cannot be opened right now');
        $this->actingAs($team->owner)->get('/go/no-such-platform')->assertStatus(503);
        $this->actingAs($team->owner)->get('/go/memory')->assertNotFound();

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_the_activate_button_switches_the_platform_on_and_returns_to_the_launch(): void
    {
        $team = $this->company();

        $this->actingAs($team->owner)->post('/platforms/forms/activate', ['company' => $team->uuid, 'redirect' => '/f/12'])
            ->assertRedirect(route('platform.launch', ['platform' => 'forms', 'redirect' => '/f/12']));

        $this->actingAs($team->owner)->get('/go/forms?redirect=/f/12')->assertOk()->assertSee('https://forms.infodot.co.za/auth/ecosystem', false);
    }

    public function test_the_explanation_page_sends_an_entitled_person_on_and_hides_what_is_not_a_platform(): void
    {
        $team = $this->company();

        $this->actingAs($team->owner)->get('/platforms/forms/access')->assertStatus(403)->assertSee('Switch on Dot.Forms');
        $this->actingAs($team->owner)->get('/platforms/files/access')->assertRedirect(route('platform.launch', ['platform' => 'files']));
        $this->actingAs($team->owner)->get('/platforms/memory/access')->assertNotFound();
        $this->actingAs($team->owner)->get('/platforms/infodot/access')->assertNotFound();
        $this->actingAs($team->owner)->get('/platforms/no-such-platform/access')->assertNotFound();
    }

    public function test_the_explanation_page_needs_a_signed_in_person(): void
    {
        $this->get('/platforms/forms/access')->assertRedirect('/login');
    }

    public function test_each_reason_has_its_own_words_and_the_right_next_step(): void
    {
        $team = $this->company('Northwind Freight');
        $member = $this->memberOf($team);
        $owner = $team->owner;
        $this->activatePlatforms($team, 'forms');
        $entitlement = $this->access()->entitlement($team, 'forms');
        $page = fn (User $who) => $this->actingAs($who)->get('/go/forms')->assertStatus(403);

        $entitlement->forceFill(['status' => 'payment_required', 'source' => 'paid'])->save();
        $page($owner)->assertSee('Dot.Forms is waiting for a payment')->assertSee('Open Dot.Finance')->assertSee(route('platform.launch', ['platform' => 'finance']), false);
        $page($member)->assertSee('Dot.Forms is waiting for a payment')->assertDontSee('Open Dot.Finance')->assertSee('Ask '.$owner->name);

        $entitlement->forceFill(['status' => 'trial', 'trial_ends_at' => now()->subDay()])->save();
        $page($owner)->assertSee('The trial of Dot.Forms has ended')->assertSee('Open Dot.Finance');

        $entitlement->forceFill(['status' => 'expired'])->save();
        $page($member)->assertSee('access to Dot.Forms has ended')->assertDontSee('Switch on');

        $entitlement->forceFill(['status' => 'cancelled', 'source' => 'unpriced_free'])->save();
        $page($owner)->assertSee('Northwind Freight switched Dot.Forms off')->assertSee('Nothing was deleted')->assertSee('Switch on Dot.Forms');
        $page($member)->assertSee('switch it on again')->assertDontSee('Switch on Dot.Forms');

        $entitlement->forceFill(['status' => 'active', 'access_mode' => 'selected'])->save();
        $page($member)->assertSee('You have not been added to Dot.Forms')->assertSee('Ask '.$owner->name);

        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'not_launched']);
        $page($owner)->assertSee('Dot.Forms has not launched yet')->assertDontSee('Switch on');
    }

    public function test_the_explanation_never_says_team_and_lists_no_other_platforms(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);

        foreach ([$team->owner, $member, $this->person()] as $who) {
            $html = $this->actingAs($who)->get('/go/forms')->getContent();
            $main = substr($html, (int) strpos($html, '<main'), (int) strpos($html, '</main>') - (int) strpos($html, '<main'));

            $this->assertDoesNotMatchRegularExpression('/\bteams?\b/i', strip_tags($main));
            $this->assertStringNotContainsString('rest of the Dot Ecosystem', $main);
            $this->assertStringNotContainsString('onclick', $html);
        }
    }

    // ---- the links that lead to /go ----

    public function test_the_switcher_the_platforms_page_and_a_platform_link_all_land_on_the_explanation(): void
    {
        $team = $this->company();
        $explains = route('platform.access', ['platform' => 'forms']);

        Livewire::actingAs($team->owner)->test(DotSwitcher::class)->call('switchTo', 'forms')->assertRedirect($explains);
        Livewire::actingAs($team->owner)->test(EcosystemWidget::class)->call('launch', 'forms')->assertRedirect($explains);
        Livewire::actingAs($team->owner)->test(PlatformLink::class, ['platform' => 'forms', 'label' => 'Forms'])->call('open')->assertRedirect($explains);
        Livewire::actingAs($team->owner)->test(PlatformLink::class, ['platform' => 'files', 'label' => 'Files'])->call('open')->assertRedirect(route('platform.launch', ['platform' => 'files']));

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_opening_a_registered_object_on_a_platform_the_company_does_not_hold_lands_on_the_explanation(): void
    {
        $team = $this->company();
        $resource = Resource::forceCreate(['team_id' => $team->id, 'type' => 'file', 'platform' => 'forms', 'platform_object_uuid' => (string) Str::uuid(), 'path' => '/f/12']);

        $this->actingAs($team->owner)->get('/open/'.$resource->uuid)->assertRedirect(route('platform.access', ['platform' => 'forms', 'redirect' => '/f/12']));
        $this->actingAs($team->owner)->get(route('platform.access', ['platform' => 'forms', 'redirect' => '/f/12']))->assertStatus(403);

        $this->activatePlatforms($team, 'forms');

        $this->actingAs($team->owner)->get('/open/'.$resource->uuid)->assertRedirect(route('platform.launch', ['platform' => 'forms', 'redirect' => '/f/12']));
    }

    // ---- POST /api/ecosystem/redeem ----

    public function test_redeem_refuses_a_code_when_no_company_of_the_person_holds_the_platform_and_logs_why(): void
    {
        $team = $this->company();
        Log::spy();

        $this->redeem($team->owner, 'forms')->assertForbidden()->assertExactJson(['error' => 'invalid_request']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Ecosystem redeem refused.'
            && str_contains($context['reason'], 'no entitled company') && str_contains($context['reason'], 'not_activated'))->once();
        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'the code is spent either way');
    }

    public function test_redeem_marks_each_company_with_entitled_and_names_the_platform(): void
    {
        $holds = $this->company('Northwind Freight');
        $owner = $holds->owner;
        $this->activatePlatforms($holds, 'forms');
        $second = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Second Company']);

        $response = $this->redeem($owner->fresh(), 'forms')->assertOk();

        $response->assertJsonPath('platform', 'forms')->assertJsonPath('user.uuid', $owner->uuid)->assertJsonPath('current_team_uuid', $holds->uuid);
        $teams = collect($response->json('teams'))->keyBy('name');

        $this->assertSame(['entitled' => true, 'access' => null], array_intersect_key($teams['Northwind Freight'], ['entitled' => 1, 'access' => 1]));
        $this->assertSame(['entitled' => false, 'access' => 'not_activated'], array_intersect_key($teams['Second Company'], ['entitled' => 1, 'access' => 1]));
        $personal = $teams->firstWhere('personal', true);
        $this->assertSame([false, 'no_personal_access'], [$personal['entitled'], $personal['access']]);

        // Nothing that was there before has changed shape.
        $this->assertSame(['id', 'uuid', 'name', 'personal', 'role', 'infodot_role', 'entitled', 'access'], array_keys($teams['Northwind Freight']));
        $this->assertSame(['platform', 'user', 'teams', 'current_team_id', 'current_team_uuid'], array_keys($response->json()));
        $this->assertSame($second->uuid, $teams['Second Company']['uuid']);
    }

    public function test_redeem_for_a_core_platform_entitles_every_company_and_the_personal_workspace(): void
    {
        $team = $this->company();

        $response = $this->redeem($team->owner, 'files')->assertOk()->assertJsonPath('platform', 'files');

        $this->assertSame([true, true], collect($response->json('teams'))->pluck('entitled')->all());
        $this->assertSame([null, null], collect($response->json('teams'))->pluck('access')->all());

        $this->redeem($this->person(), 'files')->assertOk()->assertJsonPath('teams.0.personal', true)->assertJsonPath('teams.0.entitled', true);
    }

    public function test_redeem_respects_selected_people_suspension_and_maintenance(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $this->activatePlatforms($team, 'forms');

        $this->redeem($member, 'forms')->assertOk();

        $this->entitlements()->setAccessMode($team, 'forms', 'selected', $team->owner);
        $this->redeem($member, 'forms')->assertForbidden();
        $this->redeem($team->owner, 'forms')->assertOk();

        $this->entitlements()->suspend($team, 'forms', $this->administrator());
        $this->redeem($team->owner, 'forms')->assertForbidden();
        $this->entitlements()->restore($team, 'forms', $this->administrator());

        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'maintenance']);
        $this->redeem($team->owner, 'forms')->assertForbidden();
    }

    public function test_redeem_is_unchanged_for_bridge_services_and_platforms_with_their_own_sign_in(): void
    {
        $person = $this->person();

        $this->redeem($person, 'memory')->assertOk()->assertJsonPath('teams.0.entitled', true);
        $this->redeem($person, 'mines')->assertOk()->assertJsonPath('teams.0.entitled', true);
    }

    // ---- /oauth/authorize ----

    /** @return array<string, string> */
    private function authorizeQuery(string $slug): array
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Dot.'.ucfirst($slug), ["https://$slug.test/sso/callback"], confidential: true);
        DotPlatform::create(['name' => 'Dot.'.ucfirst($slug), 'slug' => $slug, 'domain' => "https://$slug.test", 'oauth_client_id' => $client->getKey(), 'redirect_uris' => ["https://$slug.test/sso/callback"], 'scopes' => ['openid', 'profile', 'email'], 'status' => 'active']);

        return [
            'client_id' => (string) $client->getKey(), 'redirect_uri' => "https://$slug.test/sso/callback", 'response_type' => 'code', 'scope' => 'openid profile email',
            'state' => str_repeat('s', 40), 'nonce' => 'nonce-123456', 'code_challenge_method' => 'S256',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('a1B2-c3._~', 6).'xYz9', true)), '+/', '-_'), '='),
        ];
    }

    public function test_oauth_authorize_refuses_a_platform_the_company_does_not_hold_with_the_explanation(): void
    {
        $team = $this->company();
        $query = $this->authorizeQuery('forms');

        $response = $this->actingAs($team->owner)->get('/oauth/authorize?'.http_build_query($query));

        $response->assertStatus(403)->assertSee($team->name.' has not switched on Dot.Forms')->assertSee('Switch on Dot.Forms');
        $this->assertNull($response->headers->get('Location'), 'nothing is sent to the platform');
        $this->assertSame(0, DB::table('oauth_auth_codes')->count());

        $this->activatePlatforms($team, 'forms');

        $location = (string) $this->actingAs($team->owner)->get('/oauth/authorize?'.http_build_query($query))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://forms.test/sso/callback?code=', $location);
    }

    public function test_oauth_authorize_lets_a_core_platform_through_and_refuses_a_personal_workspace_for_the_rest(): void
    {
        $person = $this->person();

        $this->actingAs($person)->get('/oauth/authorize?'.http_build_query($this->authorizeQuery('files')))->assertRedirect();
        $this->actingAs($person)->get('/oauth/authorize?'.http_build_query($this->authorizeQuery('hr')))->assertStatus(403)->assertSee('Dot.HR is for companies');
    }
}
