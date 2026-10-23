<?php

namespace Tests\Feature\Points;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigestEmailPreferenceTest extends TestCase
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

    public function test_nobody_is_signed_up_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame('off', $user->fresh()->points_digest);
        $this->assertNull($user->fresh()->pointsDigestPeriod());
    }

    public function test_the_page_shows_three_choices_with_the_current_one_selected_and_one_sentence_of_explanation(): void
    {
        $user = $this->member();

        $html = $this->actingAs($user)->get('/points')->assertOk()->getContent();
        $this->assertStringContainsString('id="digest-emails"', $html);
        $this->assertStringContainsString('action="'.route('points.digest-emails').'"', $html);
        $this->assertStringContainsString('never unless you choose it here', $html);
        $this->assertMatchesRegularExpression('/<fieldset[^>]*>\s*<legend>/', $html);
        $this->assertMatchesRegularExpression('/<input type="radio"[^>]*id="pts-digest-off"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="radio"[^>]*id="pts-digest-week"[^>]*checked/', $html);
        foreach (['off', 'week', 'month'] as $choice) {
            $this->assertMatchesRegularExpression('/<label[^>]*for="pts-digest-'.$choice.'"/', $html);
        }
        $this->assertStringContainsString('this is <strong>off</strong>', $html);

        User::whereKey($user->id)->update(['points_digest' => 'month']);
        $html = $this->actingAs($user->fresh())->get('/points')->getContent();
        $this->assertMatchesRegularExpression('/<input type="radio"[^>]*id="pts-digest-month"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="radio"[^>]*id="pts-digest-off"[^>]*checked/', $html);
        $this->assertStringContainsString('this is <strong>monthly</strong>', $html);
    }

    public function test_the_choice_is_there_even_when_the_points_service_is_not_connected(): void
    {
        config(['services.dopemine.secret' => null]);

        $this->actingAs($this->member())->get('/points')->assertOk()
            ->assertSee('Points are unavailable right now')
            ->assertSee('Summary emails');
    }

    public function test_saving_each_choice_stores_it_and_says_so(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/points/digest-emails', ['points_digest' => 'week'])->assertRedirect(route('points').'#digest-emails');
        $this->assertSame('week', $user->fresh()->points_digest);
        $this->actingAs($user)->get('/points')->assertSee('each Monday, only in weeks when you earned some');

        $this->actingAs($user)->post('/points/digest-emails', ['points_digest' => 'month']);
        $this->assertSame('month', $user->fresh()->points_digest);

        $this->actingAs($user)->post('/points/digest-emails', ['points_digest' => 'off']);
        $this->assertSame('off', $user->fresh()->points_digest);
        $this->actingAs($user)->get('/points')->assertSee('We will not email you summaries of your points');
    }

    public function test_it_only_changes_the_signed_in_persons_own_setting(): void
    {
        $me = $this->member();
        $other = User::factory()->create();

        $this->actingAs($me)->post('/points/digest-emails', ['points_digest' => 'week', 'user_id' => $other->id, 'id' => $other->id, 'uuid' => $other->uuid]);

        $this->assertSame('week', $me->fresh()->points_digest);
        $this->assertSame('off', $other->fresh()->points_digest);
    }

    public function test_anything_but_off_week_or_month_is_refused_and_changes_nothing(): void
    {
        $user = $this->member();
        User::whereKey($user->id)->update(['points_digest' => 'week']);

        foreach (['', 'daily', 'WEEK', '1', null, ['week']] as $value) {
            $this->actingAs($user->fresh())->from('/points')->post('/points/digest-emails', ['points_digest' => $value])
                ->assertRedirect('/points')->assertSessionHasErrors('points_digest');
        }
        $this->assertSame('week', $user->fresh()->points_digest);
    }

    public function test_guests_are_sent_to_sign_in_and_nothing_changes(): void
    {
        $user = User::factory()->create();

        $this->post('/points/digest-emails', ['points_digest' => 'week'])->assertRedirect(route('login'));

        $this->assertSame('off', $user->fresh()->points_digest);
    }

    public function test_the_post_requires_a_csrf_token(): void
    {
        $this->app['env'] = 'production'; // CSRF is skipped while unit tests run; production mode switches it on.
        $user = $this->member();

        $this->actingAs($user)->post('/points/digest-emails', ['points_digest' => 'week'])->assertStatus(419);

        $this->assertSame('off', $user->fresh()->points_digest);
    }

    public function test_the_expiry_email_switch_is_untouched(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post('/points/digest-emails', ['points_digest' => 'week']);

        $this->assertTrue((bool) $user->fresh()->points_expiry_emails);
    }
}
