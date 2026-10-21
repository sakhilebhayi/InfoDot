<?php

namespace Tests\Feature\Points;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpiryEmailPreferenceTest extends TestCase
{
    use FakesDopemine;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->upstream['GET /api/points/{uuid}'] = Http::response($this->summaryBody(50));
        $this->fakeDopemine();
    }

    private function member(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_new_people_are_opted_in_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->fresh()->wantsPointsExpiryEmails());
        $this->assertTrue($user->wantsPointsExpiryEmails()); // even before the row is reloaded
    }

    public function test_the_page_shows_the_switch_with_its_current_state(): void
    {
        $user = $this->member();

        $html = $this->actingAs($user)->get('/points')->assertOk()->getContent();
        $this->assertStringContainsString('id="expiry-emails"', $html);
        $this->assertStringContainsString('Email me before my points expire', $html);
        $this->assertStringContainsString('action="'.route('points.expiry-emails').'"', $html);
        $this->assertMatchesRegularExpression('/<input type="checkbox"[^>]*id="pts-expiry-emails"[^>]*checked/', $html);
        $this->assertStringContainsString('are <strong>on</strong>', $html);

        User::whereKey($user->id)->update(['points_expiry_emails' => false]);
        $html = $this->actingAs($user->fresh())->get('/points')->getContent();
        $this->assertDoesNotMatchRegularExpression('/<input type="checkbox"[^>]*id="pts-expiry-emails"[^>]*checked/', $html);
        $this->assertStringContainsString('are <strong>off</strong>', $html);
        $this->assertMatchesRegularExpression('/<label[^>]*for="pts-expiry-emails"/', $html);
    }

    public function test_the_switch_is_there_even_when_the_points_service_is_not_connected(): void
    {
        config(['services.dopemine.secret' => null]);

        $this->actingAs($this->member())->get('/points')->assertOk()
            ->assertSee('Points are unavailable right now')
            ->assertSee('Email me before my points expire');
    }

    public function test_saving_turns_it_off_and_back_on_and_says_so(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/points/expiry-emails', ['points_expiry_emails' => '0'])->assertRedirect(route('points'));
        $this->assertFalse((bool) $user->fresh()->points_expiry_emails);
        $this->actingAs($user)->get('/points')->assertSee('We will not email you about points that are about to lapse');

        $this->actingAs($user)->post('/points/expiry-emails', ['points_expiry_emails' => '1'])->assertRedirect(route('points'));
        $this->assertTrue((bool) $user->fresh()->points_expiry_emails);
        $this->actingAs($user)->get('/points')->assertSee('We will email you before some of your points lapse');
    }

    public function test_it_only_changes_the_signed_in_persons_own_setting(): void
    {
        $me = $this->member();
        $other = User::factory()->create();

        $this->actingAs($me)->post('/points/expiry-emails', ['points_expiry_emails' => '0', 'user_id' => $other->id, 'id' => $other->id]);

        $this->assertFalse((bool) $me->fresh()->points_expiry_emails);
        $this->assertTrue((bool) $other->fresh()->points_expiry_emails);
    }

    public function test_anything_but_0_or_1_is_refused_and_changes_nothing(): void
    {
        $user = $this->member();

        foreach (['', 'yes', '2', null] as $value) {
            $this->actingAs($user)->from('/points')->post('/points/expiry-emails', ['points_expiry_emails' => $value])
                ->assertRedirect('/points')->assertSessionHasErrors('points_expiry_emails');
        }
        $this->assertTrue((bool) $user->fresh()->points_expiry_emails);
    }

    public function test_guests_are_sent_to_sign_in_and_nothing_changes(): void
    {
        $user = User::factory()->create();

        $this->post('/points/expiry-emails', ['points_expiry_emails' => '0'])->assertRedirect(route('login'));

        $this->assertTrue((bool) $user->fresh()->points_expiry_emails);
    }

    public function test_the_post_requires_a_csrf_token(): void
    {
        $this->app['env'] = 'production'; // CSRF is skipped while unit tests run; production mode switches it on.
        $user = $this->member();

        $this->actingAs($user)->post('/points/expiry-emails', ['points_expiry_emails' => '0'])->assertStatus(419);

        $this->assertTrue((bool) $user->fresh()->points_expiry_emails);
    }
}
