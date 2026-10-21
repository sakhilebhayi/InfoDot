<?php

namespace Tests\Feature\Points;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PointsScreenTest extends TestCase
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

    /** The one-time token rendered into the Redeem form for a reward. */
    private function ticketFor(string $html, string $rewardName): string
    {
        $section = strstr($html, $rewardName);
        $this->assertNotFalse($section, "$rewardName is not on the page.");
        $this->assertSame(1, preg_match('/name="ticket" value="([A-Za-z0-9]{32})"/', $section, $m), "No Redeem form for $rewardName.");

        return $m[1];
    }

    // ---------------------------------------------------------------- access

    public function test_guests_are_sent_to_sign_in_and_nothing_is_asked_of_the_points_service(): void
    {
        $this->get('/points')->assertRedirect(route('login'));
        $this->post('/points/redeem', ['ticket' => str_repeat('a', 32)])->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_the_redeem_post_requires_a_csrf_token(): void
    {
        $this->app['env'] = 'production'; // CSRF is skipped while unit tests run; production mode switches it on.
        $user = $this->member();

        $this->actingAs($user)->post('/points/redeem', ['ticket' => str_repeat('a', 32)])->assertStatus(419);
        Http::assertNothingSent();
    }

    public function test_the_form_carries_a_csrf_token_and_the_page_is_not_cacheable(): void
    {
        $response = $this->actingAs($this->member())->get('/points')->assertOk();

        $this->assertMatchesRegularExpression('/<form method="POST" action="[^"]+\/points\/redeem"[^>]*>\s*<input type="hidden" name="_token"/', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    // ---------------------------------------------------------------- what is shown

    public function test_the_page_shows_balance_lapsing_points_catalogue_by_kind_history_and_terms(): void
    {
        $this->upstream['GET /api/points/redemptions'] = Http::response([
            ['redemption_id' => 'a', 'reward' => ['name' => 'R50 billing credit', 'kind' => 'billing_credit'], 'points_spent' => 20, 'status' => 'failed',
                'requested_at' => '2026-10-21T10:00:00+00:00', 'resolved_at' => '2026-10-22T08:30:00+00:00', 'points_returned' => true, 'message' => 'Your points were returned.'],
        ]);

        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('50', $html);
        $this->assertStringContainsString('points you can redeem', $html);
        $this->assertStringContainsString('12 points</strong> lapse within 30 days, the first of them on 4 Nov 2026', $html);
        $this->assertStringContainsString('Next to lapse: <strong>12 points</strong> on 4 Nov 2026', $html);
        foreach (['Billing credit', 'Capacity', 'Marketplace'] as $kind) {
            $this->assertStringContainsString($kind, $html);
        }
        $this->assertLessThan(strpos($html, 'Extra storage'), strpos($html, 'R50 billing credit'), 'Grouped by kind in a fixed order.');
        $this->assertStringContainsString('Off your next invoice.', $html);
        $this->assertStringContainsString('Unavailable right now', $html); // the sticker pack
        $this->assertStringContainsString('Your points were returned.', $html);
        $this->assertStringContainsString('Failed', $html);
        $this->assertStringContainsString('22 Oct 2026', $html);
        $this->assertStringContainsString('no cash value', $html);
        $this->assertStringContainsString('12 months after it was granted', $html);
        $this->assertStringContainsString('fulfilled by an operator', $html);
        $this->assertStringContainsString('the points are returned to you', $html);
    }

    public function test_redeem_shows_what_is_spent_and_what_is_received_and_waits_for_a_confirmation(): void
    {
        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('Confirm this redemption', $html);
        $this->assertStringContainsString('You spend', $html);
        $this->assertStringContainsString('Balance after', $html);
        $this->assertStringContainsString('30 points', $html); // 50 - 20
        $this->assertStringContainsString('You get', $html);
        $this->assertStringContainsString('Confirm: spend 20 points', $html);
        $this->assertCount(0, $this->sent('POST', '/api/points/redemptions'), 'Viewing a form spends nothing.');
        $this->assertStringNotContainsString('Sticker pack</h4>'.'<form', $html);
    }

    public function test_a_reward_the_person_cannot_afford_or_that_is_unavailable_has_no_redeem_form(): void
    {
        $this->upstream['GET /api/points/{uuid}'] = Http::response($this->summaryBody(25));

        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('You need 15 more', $html);
        $this->assertSame(1, substr_count($html, 'name="ticket"'), 'Only the R50 credit (20 points) is affordable and available.');
    }

    public function test_an_empty_catalogue_and_empty_history_say_so_plainly(): void
    {
        $this->upstream['GET /api/points/rewards'] = Http::response([]);

        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('There is nothing to redeem yet', $html);
        $this->assertStringContainsString('You have not redeemed anything yet', $html);
    }

    // ---------------------------------------------------------------- outage

    public function test_when_the_secret_is_not_set_the_page_says_points_are_unavailable_and_asks_nothing(): void
    {
        config(['services.dopemine.secret' => null]);

        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('Points are unavailable right now', $html);
        $this->assertStringNotContainsString('name="ticket"', $html);
        $this->assertStringContainsString('no cash value', $html);
        Http::assertNothingSent();
    }

    public function test_when_the_service_is_down_the_page_says_so_instead_of_failing_or_showing_zero(): void
    {
        $this->upstream = [
            'GET /api/points/rewards' => fn () => throw new ConnectionException('down'),
            'GET /api/points/redemptions' => fn () => throw new ConnectionException('down'),
            'GET /api/points/{uuid}' => fn () => throw new ConnectionException('down'),
        ];

        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('Points are unavailable right now', $html);
        $this->assertStringContainsString('Your points are not lost', $html);
        $this->assertStringNotContainsString('points you can redeem', $html);
        $this->assertStringNotContainsString('name="ticket"', $html);
        $this->assertSame(1, $this->reached, 'Fails fast: one timeout, not three.');
    }

    public function test_a_server_error_or_wrong_signature_is_also_the_unavailable_state(): void
    {
        foreach ([Http::response('boom', 500), Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401)] as $answer) {
            $this->upstream = ['GET /api/points/rewards' => $answer, 'GET /api/points/redemptions' => $answer, 'GET /api/points/{uuid}' => $answer];

            $this->actingAs($this->member())->get('/points')->assertOk()->assertSee('Points are unavailable right now');
        }
    }

    public function test_one_failed_part_leaves_the_others_showing(): void
    {
        $this->upstream['GET /api/points/redemptions'] = Http::response('boom', 500);

        $html = $this->actingAs($this->member())->get('/points')->assertOk()->getContent();

        $this->assertStringContainsString('points you can redeem', $html);
        $this->assertStringContainsString('R50 billing credit', $html);
        $this->assertStringContainsString('Your redemptions are unavailable right now', $html);
        $this->assertStringNotContainsString('You have not redeemed anything yet', $html);
    }

    // ---------------------------------------------------------------- redeeming

    public function test_redeeming_asks_the_service_once_as_the_signed_in_person_and_the_page_then_shows_the_new_state(): void
    {
        $user = $this->member();
        $token = $this->ticketFor($this->actingAs($user)->get('/points')->getContent(), 'R50 billing credit');

        $this->upstream['GET /api/points/{uuid}'] = Http::response($this->summaryBody(30));
        $this->upstream['GET /api/points/redemptions'] = Http::response([
            ['redemption_id' => '9b35d390-7031-4946-807e-784f03d09f42', 'reward' => ['name' => 'R50 billing credit', 'kind' => 'billing_credit'], 'points_spent' => 20, 'status' => 'pending',
                'requested_at' => '2026-10-21T10:00:00+00:00', 'resolved_at' => null, 'points_returned' => false, 'message' => null],
        ]);

        $this->post('/points/redeem', ['ticket' => $token])->assertRedirect(route('points'));

        $sent = $this->sent('POST', '/api/points/redemptions');
        $this->assertCount(1, $sent);
        $this->assertSignedOverBody($sent[0]);
        $this->assertSame(['user_uuid' => $user->uuid, 'reward_id' => 3, 'idempotency_key' => $token], json_decode($sent[0]->body(), true));

        $html = $this->get('/points')->assertOk()->getContent();
        $this->assertStringContainsString('Requested: R50 billing credit. 20 points spent; your balance is now 30.', $html);
        $this->assertStringContainsString('<span class="ix-stencil-num">30</span>', $html, 'The balance shown is the new one, not the cached 50.');
        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('21 Oct 2026', $html);
    }

    public function test_nothing_the_browser_sends_can_change_whose_points_move_or_what_is_bought(): void
    {
        $user = $this->member();
        $other = User::factory()->create();
        $token = $this->ticketFor($this->actingAs($user)->get('/points')->getContent(), 'R50 billing credit');

        $this->post('/points/redeem', [
            'ticket' => $token,
            'user_uuid' => $other->uuid,
            'reward_id' => 4,
            'idempotency_key' => 'attacker-chosen-key-1234',
            'cost_points' => 1,
        ])->assertRedirect(route('points'));

        $body = json_decode($this->sent('POST', '/api/points/redemptions')[0]->body(), true);
        $this->assertSame(['user_uuid' => $user->uuid, 'reward_id' => 3, 'idempotency_key' => $token], $body);
        foreach ($this->requests as $request) {
            $this->assertStringNotContainsString($other->uuid, $request->url().$request->body());
        }
    }

    public function test_a_ticket_that_is_missing_forged_or_someone_elses_sends_nothing(): void
    {
        $alice = $this->member();
        $bob = $this->member();
        $aliceToken = $this->ticketFor($this->actingAs($alice)->get('/points')->getContent(), 'R50 billing credit');

        foreach ([[], ['ticket' => 'short'], ['ticket' => str_repeat('Z', 32)], ['ticket' => ['array']]] as $input) {
            $this->actingAs($alice)->post('/points/redeem', $input)->assertRedirect(route('points'));
        }
        $this->flushSession();
        // Bob presents Alice's token: it is not his, and his session never issued it.
        $this->actingAs($bob)->post('/points/redeem', ['ticket' => $aliceToken])->assertRedirect(route('points'));

        $this->assertCount(0, $this->sent('POST', '/api/points/redemptions'));
        $this->actingAs($bob)->get('/points')->assertSee('Choose the reward again', false);
    }

    public function test_a_form_works_once_a_second_post_of_the_same_form_spends_nothing_more(): void
    {
        $user = $this->member();
        $token = $this->ticketFor($this->actingAs($user)->get('/points')->getContent(), 'R50 billing credit');

        $this->post('/points/redeem', ['ticket' => $token]);
        $first = session('points_notice');
        $this->post('/points/redeem', ['ticket' => $token])->assertRedirect(route('points'));

        $this->assertCount(1, $this->sent('POST', '/api/points/redemptions'));
        $this->assertSame($first, session('points_notice'), 'The repeat is told what happened the first time.');
        $this->assertSame('ok', $first['level']);
    }

    public function test_each_displayed_form_gets_its_own_key_and_a_new_page_gets_new_ones(): void
    {
        $user = $this->member();
        $one = $this->actingAs($user)->get('/points')->getContent();
        $two = $this->get('/points')->getContent();

        $this->assertNotSame($this->ticketFor($one, 'R50 billing credit'), $this->ticketFor($one, 'Extra storage'));
        $this->assertNotSame($this->ticketFor($one, 'R50 billing credit'), $this->ticketFor($two, 'R50 billing credit'));
    }

    public function test_an_unconfirmed_request_is_retried_with_the_same_key_so_it_cannot_spend_twice(): void
    {
        $user = $this->member();
        $token = $this->ticketFor($this->actingAs($user)->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['POST /api/points/redemptions'] = fn () => throw new ConnectionException('timed out');

        $this->post('/points/redeem', ['ticket' => $token])->assertRedirect(route('points'));
        $this->assertStringContainsString('could not confirm', session('points_notice')['message']);

        // The person goes back to the page: the same reward carries the same key, and says why.
        $page = $this->get('/points')->getContent();
        $this->assertSame($token, $this->ticketFor($page, 'R50 billing credit'));
        $this->assertStringContainsString('could not be confirmed', $page);

        // The service has by now recorded the first attempt: the retry is a replay (200), not a second spend.
        $this->upstream['POST /api/points/redemptions'] = Http::response($this->receipt(), 200);
        $this->post('/points/redeem', ['ticket' => $token]);

        $keys = collect($this->sent('POST', '/api/points/redemptions'))->map(fn ($r) => json_decode($r->body(), true)['idempotency_key'])->unique();
        $this->assertSame([$token], $keys->all());
        $this->assertStringContainsString('already received', session('points_notice')['message']);
    }

    public function test_a_price_that_changed_since_the_page_was_drawn_is_shown_before_it_is_paid(): void
    {
        $user = $this->member();
        $token = $this->ticketFor($this->actingAs($user)->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['GET /api/points/rewards'] = Http::response([
            ['id' => 3, 'name' => 'R50 billing credit', 'kind' => 'billing_credit', 'description' => null, 'cost_points' => 35, 'available' => true],
        ]);

        $this->post('/points/redeem', ['ticket' => $token]);

        $this->assertCount(0, $this->sent('POST', '/api/points/redemptions'));
        $this->assertStringContainsString('now costs 35 points (you saw 20)', session('points_notice')['message']);
    }

    public function test_a_reward_that_went_away_since_the_page_was_drawn_is_not_requested(): void
    {
        $token = $this->ticketFor($this->actingAs($this->member())->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['GET /api/points/rewards'] = Http::response([]);

        $this->post('/points/redeem', ['ticket' => $token]);

        $this->assertCount(0, $this->sent('POST', '/api/points/redemptions'));
        $this->assertStringContainsString('not available any more', session('points_notice')['message']);
    }

    /** @return array<string, array{0: int, 1: array<string, mixed>, 2: string}> */
    public static function refusals(): array
    {
        return [
            'reward gone' => [404, ['code' => 'reward_unavailable'], 'not available any more'],
            'not enough points' => [409, ['code' => 'insufficient_points', 'points_needed' => 20, 'balance' => 5], 'It costs 20 and you have 5'],
            'out of stock' => [409, ['code' => 'out_of_stock'], 'just run out'],
            'monthly limit' => [409, ['code' => 'monthly_limit_reached'], 'limit for this month'],
            'key reused' => [409, ['code' => 'idempotency_key_reused'], 'Reload the page'],
            'validation' => [422, ['code' => 'validation_failed', 'errors' => ['x' => ['y']]], 'could not process'],
            'rate limited' => [429, ['code' => 'rate_limited', 'retry_after' => 3000], 'about 50 minute'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_every_refusal_is_shown_in_plain_words_and_spends_nothing(int $status, array $body, string $phrase): void
    {
        $token = $this->ticketFor($this->actingAs($this->member())->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['POST /api/points/redemptions'] = Http::response($body + ['message' => 'raw'], $status);

        $this->post('/points/redeem', ['ticket' => $token])->assertRedirect(route('points'));

        $html = $this->get('/points')->assertOk()->getContent();
        $this->assertStringContainsString($phrase, $html);
        $this->assertStringContainsString('Nothing was spent', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringNotContainsString('>raw<', $html);
    }

    public function test_a_not_enough_points_refusal_drops_the_cached_balance_so_the_page_shows_the_real_one(): void
    {
        $user = $this->member();
        $token = $this->ticketFor($this->actingAs($user)->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['GET /api/points/{uuid}'] = Http::response($this->summaryBody(5));
        $this->upstream['POST /api/points/redemptions'] = Http::response(['code' => 'insufficient_points', 'points_needed' => 20, 'balance' => 5], 409);

        $this->post('/points/redeem', ['ticket' => $token]);

        $this->get('/points')->assertSee('<span class="ix-stencil-num">5</span>', false);
    }

    public function test_a_rate_limited_form_can_be_tried_again_later_but_other_refusals_use_the_form_up(): void
    {
        $token = $this->ticketFor($this->actingAs($this->member())->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['POST /api/points/redemptions'] = Http::response(['code' => 'rate_limited', 'retry_after' => 60], 429);
        $this->post('/points/redeem', ['ticket' => $token]);
        $this->upstream['POST /api/points/redemptions'] = Http::response($this->receipt(), 201);
        $this->post('/points/redeem', ['ticket' => $token]);

        $this->assertCount(2, $this->sent('POST', '/api/points/redemptions'));
        $this->assertSame('ok', session('points_notice')['level']);
    }

    public function test_a_service_outage_while_redeeming_is_a_calm_message_not_an_error_page(): void
    {
        $token = $this->ticketFor($this->actingAs($this->member())->get('/points')->getContent(), 'R50 billing credit');
        $this->upstream['POST /api/points/redemptions'] = Http::response('boom', 503);

        $this->post('/points/redeem', ['ticket' => $token])->assertRedirect(route('points'));

        $this->assertStringContainsString('nothing is spent twice', session('points_notice')['message']);
    }

    // ---------------------------------------------------------------- limits and leaks

    public function test_the_redeem_route_is_rate_limited_per_person(): void
    {
        $user = $this->member();
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post('/points/redeem', ['ticket' => 'nope'])->assertRedirect(route('points'));
        }

        $this->actingAs($user)->post('/points/redeem', ['ticket' => 'nope'])->assertStatus(429);
        $this->actingAs($this->member())->post('/points/redeem', ['ticket' => 'nope'])->assertRedirect(route('points'));
    }

    public function test_the_secret_is_never_in_the_page_or_the_logs(): void
    {
        Log::spy();
        $user = $this->member();
        $html = $this->actingAs($user)->get('/points')->getContent();
        $token = $this->ticketFor($html, 'R50 billing credit');
        $this->upstream['POST /api/points/redemptions'] = Http::response('boom', 500);
        $redirected = $this->post('/points/redeem', ['ticket' => $token]);
        $html .= $this->get('/points')->getContent().$redirected->getContent();

        $this->assertStringNotContainsString(self::SECRET, $html);
        $this->assertStringNotContainsString('sha256=', $html);
        $this->assertStringNotContainsString('X-InfoDot', $html);
        $this->assertStringNotContainsString('dopemine.test', $html);
        Log::shouldHaveReceived('warning')->withArgs(function (...$args) {
            $line = json_encode($args);
            $this->assertStringNotContainsString(self::SECRET, $line);
            $this->assertStringNotContainsString('boom', $line);

            return true;
        });
    }

    public function test_the_profile_and_the_account_menu_link_to_the_points_screen(): void
    {
        $user = $this->member();

        $profile = $this->actingAs($user)->get(route('profile.show', ['id' => $user->id]))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('points').'"', $profile);

        $other = $this->member();
        $this->actingAs($user)->get('/points')->assertOk()->assertSee('href="'.route('points').'"', false);
        $this->assertNotSame($user->uuid, $other->uuid);
    }
}
