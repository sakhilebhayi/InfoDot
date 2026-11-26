<?php

namespace Tests\Feature\Licensing;

use App\Actions\Jetstream\InviteTeamMember;
use App\Livewire\Teams\TeamMemberManager;
use App\Models\LicenceAssignment;
use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamLicence;
use App\Models\User;
use App\Services\Licensing\FinanceEntitlements;
use App\Services\Licensing\LicenceBatches;
use App\Services\Licensing\LicencePricing;
use App\Services\Licensing\SeatLedger;
use App\Support\Knowledge\Signature;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * R-53 phase 3: the upgrade journey, the purchase call to Dot.Finance (a fake one here), the owner's actions behind their warning pages, the
 * return pages that never activate anything, and isolation between companies. Journeys A, B and C of the brief are the first three groups.
 */
class TeamLicencesScreensTest extends LicenceTestCase
{
    private const CHECKOUT = 'https://checkout.paystack.test/abc123';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'licensing.finance_connected' => true, 'licensing.finance.url' => 'https://finance.example.test', 'licensing.finance.secret' => 'finance-secret-for-tests',
            'licensing.finance.cancel_enabled' => false,
        ]);
    }

    private function fakeFinance(int $status = 200, ?string $url = self::CHECKOUT): void
    {
        Http::fake(['finance.example.test/api/licences/*' => Http::response($status === 200 ? ['checkout_url' => $url, 'status' => 'awaiting_payment'] : ['error' => 'x'], $status), 'finance.example.test/*' => Http::response([], 404)]);
    }

    private function buy(Team $team, int $quantity = 2, string $interval = 'month', ?User $as = null)
    {
        return $this->actingAs($as ?? $team->owner)->post(route('licences.checkout', $team->uuid), ['quantity' => $quantity, 'interval' => $interval]);
    }

    // ---------------------------------------------------------------- Journey A: register, one free licence, no payment

    public function test_journey_a_a_new_company_has_one_free_licence_and_uses_it_without_paying(): void
    {
        Http::fake();
        $team = $this->company();
        $s = app(SeatLedger::class)->summary($team);

        $this->assertSame(1, $s['included']);
        $this->assertSame(1, $s['capacity']);
        $this->assertSame(0, $s['available']);
        $this->assertSame(0, LicenceBatch::count());
        $this->actingAs($team->owner)->get(route('teams.show', $team))->assertOk()->assertSee('Licences in use')->assertSee('data-upgrade-panel', false);
        $this->actingAs($team->owner)->get('/people')->assertOk();   // the free account works with no purchase
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/licences/'));
    }

    // ---------------------------------------------------------------- Journey B: invite -> upgrade -> pay -> confirmed -> invite -> join

    public function test_journey_b_second_person_needs_a_verified_payment_before_anyone_can_be_invited(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $owner = $team->owner;

        // 1. No licence free: the invite is refused and the Add form is replaced by the upgrade panel.
        try {
            app(InviteTeamMember::class)->invite($owner, $team, 'second@example.test', 'member');
            $this->fail('An invitation without a licence must be refused.');
        } catch (ValidationException) {
            $this->assertSame(0, TeamInvitation::count());
        }
        $this->actingAs($owner)->get(route('teams.show', $team))->assertSee('Your free licence covers you. Each extra person needs a licence.')->assertDontSee('Enter the email address');

        // 2. Pay: the owner is sent to Finance's checkout; nothing is active.
        $this->buy($team, 1)->assertRedirect(self::CHECKOUT);
        $batch = LicenceBatch::firstOrFail();
        $this->assertSame('pending', $batch->status);
        $this->assertSame(0, app(SeatLedger::class)->summary($team->fresh())['available']);
        try {
            app(InviteTeamMember::class)->invite($owner, $team, 'second@example.test', 'member');
            $this->fail('A pending purchase must not allow an invitation.');
        } catch (ValidationException) {
        }

        // 3. Only Finance's signed `paid` makes the licence real; then the owner can invite and the invitee joins.
        $this->finance($this->licenceEvent($batch, 'paid'))->assertOk()->assertExactJson(['status' => 'applied']);
        $this->assertSame(1, app(SeatLedger::class)->summary($team->fresh())['available']);
        app(InviteTeamMember::class)->invite($owner, $team, 'second@example.test', 'member');
        $invitation = TeamInvitation::firstOrFail();
        $invitee = User::factory()->create(['email' => 'second@example.test', 'email_verified_at' => now()]);
        $this->actingAs($invitee)->get(URL::signedRoute('team-invitations.accept', ['invitation' => $invitation]))->assertRedirect();
        $this->assertTrue($team->fresh()->hasUserWithEmail('second@example.test'));
        $this->assertSame(0, app(SeatLedger::class)->summary($team->fresh())['available']);
    }

    // ---------------------------------------------------------------- Journey C: bulk, one purchase not one subscription per person

    public function test_journey_c_bulk_purchases_are_batches_priced_with_the_volume_bands(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $this->buy($team, 5)->assertRedirect(self::CHECKOUT);
        $first = LicenceBatch::firstOrFail();
        $this->assertSame(5 * 9900, $first->total_minor);
        $this->finance($this->licenceEvent($first, 'paid'))->assertOk();

        // 3 more: licences 6 to 8 are in the 90% band, priced from what is already held.
        $this->buy($team, 3)->assertRedirect(self::CHECKOUT);
        $second = LicenceBatch::where('quantity', 3)->firstOrFail();
        $this->assertSame((int) round(3 * 9900 * 0.9), $second->total_minor);
        $this->finance($this->licenceEvent($second, 'paid'))->assertOk();

        $s = app(SeatLedger::class)->summary($team->fresh());
        $this->assertSame(2, LicenceBatch::count());
        $this->assertSame(8, $s['purchased']);
        $this->assertSame(9, $s['capacity']);
        $this->assertSame(8, $s['available']);
    }

    public function test_the_quantity_and_interval_come_from_the_browser_but_never_a_price(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $this->actingAs($team->owner)->post(route('licences.checkout', $team->uuid), ['quantity' => 2, 'interval' => 'year', 'total_minor' => 1, 'amount' => 1, 'price' => 1])->assertRedirect(self::CHECKOUT);

        $this->assertSame(198000, LicenceBatch::firstOrFail()->total_minor);
        Http::assertSent(fn (HttpRequest $r) => (json_decode($r->body(), true)['total_minor'] ?? null) === 198000 && json_decode($r->body(), true)['quantity'] === 2 && json_decode($r->body(), true)['interval'] === 'year');
    }

    // ---------------------------------------------------------------- the call to Finance

    public function test_the_checkout_call_is_signed_and_carries_the_batch_the_server_priced(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $this->buy($team, 2)->assertRedirect(self::CHECKOUT);
        $batch = LicenceBatch::firstOrFail();

        Http::assertSent(function (HttpRequest $r) use ($batch, $team) {
            $body = $r->body();
            $ts = (int) $r->header('X-InfoDot-Timestamp')[0];

            return $r->url() === 'https://finance.example.test/api/licences/checkout'
                && $r->header('X-InfoDot-Signature')[0] === Signature::sign('finance-secret-for-tests', $ts, $body)
                && json_decode($body, true) === [
                    'batch_uuid' => $batch->uuid, 'team_uuid' => $team->uuid, 'quantity' => 2, 'interval' => 'month', 'total_minor' => 19800, 'currency' => 'ZAR',
                    'customer' => ['name' => $team->owner->name, 'email' => $team->owner->email],
                ];
        });
    }

    public function test_when_finance_fails_nothing_is_charged_and_a_retry_reuses_the_same_pending_batch(): void
    {
        $team = $this->company();
        Http::fake(['finance.example.test/api/licences/*' => Http::sequence()->push([], 502)->push([], 500)->pushFailedConnection()->push(['checkout_url' => self::CHECKOUT]), 'finance.example.test/*' => Http::response([], 404)]);
        $this->buy($team, 2)->assertRedirect(route('licences.buy', ['team' => $team->uuid, 'quantity' => 2, 'interval' => 'month']))->assertSessionHas('licence_error');
        $this->assertStringContainsString('Nothing has been charged', session('licence_error'));
        $this->assertSame(1, LicenceBatch::count());
        $this->buy($team, 2)->assertSessionHas('licence_error');
        $this->buy($team, 2)->assertSessionHas('licence_error');
        $this->assertSame(1, LicenceBatch::count(), 'Failures must not pile up purchases.');

        $this->buy($team, 2)->assertRedirect(self::CHECKOUT);
        $this->assertSame(1, LicenceBatch::count());
        $this->assertSame(1, app(SeatLedger::class)->summary($team->fresh())['capacity']);
    }

    public function test_an_unsafe_checkout_address_is_refused(): void
    {
        $team = $this->company();
        Http::fake(['finance.example.test/api/licences/*' => Http::sequence()->push(['checkout_url' => 'ftp://evil.example/pay'])->push(['checkout_url' => 'https://user:pw@evil.example/'])->push(['checkout_url' => 'javascript:alert(1)']), 'finance.example.test/*' => Http::response([], 404)]);
        foreach (range(1, 3) as $i) {
            $this->buy($team, 1)->assertSessionHas('licence_error');
        }
    }

    public function test_pending_purchases_are_cleaned_up_by_the_sweep_and_the_owner_can_close_one(): void
    {
        $this->fakeFinance(502);
        $team = $this->company();
        $this->buy($team, 1);
        $batch = LicenceBatch::firstOrFail();
        $this->actingAs($team->owner)->get(route('licences.confirm', ['team' => $team->uuid, 'action' => 'close', 'batch' => $batch->uuid]))->assertOk()->assertSee('holds no licence');
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'close']), ['batch' => $batch->uuid])->assertSessionHas('licence_ok');
        $this->assertSame('ended', $batch->fresh()->status);
    }

    // ---------------------------------------------------------------- the pages

    public function test_the_buy_page_explains_why_what_cost_and_how_with_no_pressure(): void
    {
        $team = $this->company('Northwind Freight');
        $this->actingAs($team->owner)->get(route('licences.buy', $team->uuid))->assertOk()
            ->assertSee('Why a licence is needed')->assertSee('What a licence includes')->assertSee('What it costs')->assertSee('How to buy more')
            ->assertSee('99.00 ZAR per person per month')->assertSee('990.00 ZAR per person per year')->assertSee('you save 198.00 ZAR a year')
            ->assertSee('Continue to payment')->assertDontSee('hurry', false)->assertDontSee('countdown', false)->assertDontSee('limited time', false);
    }

    public function test_the_monthly_and_yearly_totals_follow_the_quantity_from_configuration(): void
    {
        $team = $this->company();
        $this->actingAs($team->owner)->get(route('licences.buy', ['team' => $team->uuid, 'quantity' => 10, 'interval' => 'year']))->assertOk()
            ->assertSee(SeatLedger::formatMoney(app(LicencePricing::class)->quote($team, 10, 'year')['total_minor'], 'ZAR'))
            ->assertSee(SeatLedger::formatMoney(app(LicencePricing::class)->quote($team, 10, 'month')['total_minor'], 'ZAR'));
    }

    public function test_with_no_price_configured_the_page_is_honest_and_offers_nothing(): void
    {
        config(['licensing.price.amount_minor_units' => null, 'licensing.price.annual_amount_minor_units' => null, 'licensing.price.currency' => null]);
        Http::fake();
        $team = $this->company();
        $this->actingAs($team->owner)->get(route('licences.buy', $team->uuid))->assertOk()->assertSee('No price has been set yet')->assertSee('cannot be bought')->assertDontSee('Continue to payment')->assertDontSee('ZAR');
        $this->buy($team, 1)->assertSessionHas('licence_error');
        $this->assertSame(0, LicenceBatch::count());
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/api/licences/'));
    }

    public function test_when_finance_is_not_connected_nothing_can_be_started(): void
    {
        config(['licensing.finance_connected' => false]);
        Http::fake();
        $team = $this->company();
        $this->actingAs($team->owner)->get(route('licences.buy', $team->uuid))->assertOk()->assertSee('Payments are not connected yet')->assertDontSee('Continue to payment');
        $this->buy($team, 1)->assertSessionHas('licence_error');
        $this->assertSame(0, LicenceBatch::count());
    }

    public function test_an_administrator_sees_the_page_but_is_told_to_ask_the_owner_and_cannot_buy(): void
    {
        Http::fake();
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $this->actingAs($admin)->get(route('licences.buy', $team->uuid))->assertOk()->assertSee('Only the owner')->assertDontSee('Continue to payment');
        $this->buy($team, 1, 'month', $admin)->assertForbidden();
        $this->actingAs($admin)->get(route('teams.show', $team))->assertSee('Ask '.$team->owner->name)->assertDontSee('data-upgrade-buy', false);
        $this->assertSame(0, LicenceBatch::count());
    }

    public function test_an_administrator_can_invite_only_where_a_licence_is_free(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $this->expectException(ValidationException::class);
        app(InviteTeamMember::class)->invite($admin, $team, 'x@example.test', 'member');
    }

    public function test_an_ordinary_member_and_a_stranger_cannot_open_the_licence_pages(): void
    {
        Http::fake();
        $team = $this->company();
        $member = $this->memberOf($team, 'member');
        $stranger = $this->person();
        $this->actingAs($member)->get(route('licences.buy', $team->uuid))->assertForbidden();
        $this->actingAs($stranger)->get(route('licences.buy', $team->uuid))->assertNotFound();
        $this->actingAs($stranger)->post(route('licences.checkout', $team->uuid), ['quantity' => 1, 'interval' => 'month'])->assertNotFound();
        $this->actingAs($member)->get(route('teams.show', $team))->assertDontSee('Buy licences');
    }

    public function test_the_return_pages_never_activate_and_wait_for_the_signed_confirmation(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $this->buy($team, 2);
        $batch = LicenceBatch::firstOrFail();

        foreach ([route('licences.return', ['batch' => $batch->uuid]), route('licences.return', ['result' => 'success', 'batch' => $batch->uuid]),
            route('licences.return', ['result' => 'failed', 'batch' => $batch->uuid]), route('licences.return', ['reference' => 'lic_'.str_replace('-', '', $batch->uuid).'_2'])] as $url) {
            $this->actingAs($team->owner)->get($url)->assertOk()->assertSee('data-licence-return="waiting"', false);
        }

        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertSame(0, app(SeatLedger::class)->summary($team->fresh())['purchased']);
        $this->actingAs($team->owner)->get(route('licences.return', ['batch' => $batch->uuid]))->assertSee('<meta http-equiv="refresh"', false)->assertSee('never from this page');
        $this->actingAs($team->owner)->get(route('licences.return', ['result' => 'failed', 'batch' => $batch->uuid]))->assertSee('The payment was not completed')->assertSee('you do not need to pay again');

        $this->finance($this->licenceEvent($batch, 'paid'))->assertOk();
        $this->actingAs($team->owner)->get(route('licences.return', ['batch' => $batch->uuid]))->assertSee('data-licence-return="active"', false)->assertSee('2 licences are ready')->assertDontSee('<meta http-equiv="refresh"', false);
    }

    public function test_the_waiting_page_stops_refreshing_after_the_wait_window(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $this->buy($team, 1);
        $batch = LicenceBatch::firstOrFail();
        $this->actingAs($team->owner)->get(route('licences.return', ['batch' => $batch->uuid]))->assertSee('http-equiv="refresh"', false);
        $this->travel(11)->minutes();
        $this->actingAs($team->owner)->get(route('licences.return', ['batch' => $batch->uuid]))->assertDontSee('http-equiv="refresh"', false)->assertSee('taking longer than usual');
    }

    public function test_a_return_link_for_someone_elses_purchase_or_nothing_is_a_404(): void
    {
        $this->fakeFinance();
        $team = $this->company();
        $this->buy($team, 1);
        $batch = LicenceBatch::firstOrFail();
        $other = $this->company('Other Co');
        $this->actingAs($other->owner)->get(route('licences.return', ['batch' => $batch->uuid]))->assertNotFound();
        $this->actingAs($other->owner)->get(route('licences.return', ['reference' => 'lic_'.str_replace('-', '', $batch->uuid)]))->assertNotFound();
        $this->actingAs($team->owner)->get(route('licences.return'))->assertNotFound();
        $this->actingAs($team->owner)->get(route('licences.return', ['batch' => 'not-a-uuid']))->assertNotFound();
    }

    // ---------------------------------------------------------------- owner actions behind their warnings

    public function test_taking_a_licence_back_names_who_loses_access_and_keeps_their_data(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        $ann = $this->join($team);
        $this->actingAs($team->owner)->get(route('licences.confirm', ['team' => $team->uuid, 'action' => 'release', 'user' => $ann->getKey()]))->assertOk()
            ->assertSee($ann->name.' loses access')->assertSee('straight away')->assertSee('Nothing is deleted');
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'release']), ['user' => $ann->getKey()])->assertRedirect()->assertSessionHas('licence_ok');
        $this->assertSame('released', LicenceAssignment::where('user_id', $ann->getKey())->value('state'));
        $this->assertTrue($team->fresh()->hasUser($ann));
        $this->actingAs($team->owner)->get(route('teams.show', $team))->assertSee('Give a licence');
    }

    public function test_reassigning_moves_a_licence_in_one_step_and_a_missing_licence_is_refused(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        $a = $this->join($team);
        $b = $this->join($team);
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'release']), ['user' => $b->getKey()]);
        $this->actingAs($team->owner)->get(route('licences.confirm', ['team' => $team->uuid, 'action' => 'reassign', 'user' => $a->getKey(), 'to' => $b->getKey()]))->assertOk()
            ->assertSee($a->name.' loses access')->assertSee($b->name.' gains that access');
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'reassign']), ['user' => $a->getKey(), 'to' => $b->getKey()])->assertSessionHas('licence_ok');
        $this->assertSame('released', LicenceAssignment::where('user_id', $a->getKey())->value('state'));
        $this->assertNotSame('released', LicenceAssignment::where('user_id', $b->getKey())->value('state'));
        // Giving one back fits while there is room; beyond capacity it is refused and nothing changes.
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'assign']), ['user' => $a->getKey()])->assertSessionHas('licence_ok');
        $c = $this->join($team);
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'release']), ['user' => $c->getKey()]);
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'assign']), ['user' => $c->getKey()])->assertSessionHas('licence_error');
        $this->assertSame('released', LicenceAssignment::where('user_id', $c->getKey())->value('state'));
    }

    public function test_reducing_or_cancelling_names_the_people_who_would_lose_access_and_when(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 3);
        $a = $this->join($team);
        $b = $this->join($team);
        $c = $this->join($team);
        $until = $batch->period_end->copy()->addDays(14)->format('j F Y');

        $page = $this->actingAs($team->owner)->get(route('licences.confirm', ['team' => $team->uuid, 'action' => 'reduce', 'batch' => $batch->uuid, 'quantity' => 1]))->assertOk();
        $page->assertSee($c->name)->assertSee($b->name)->assertDontSee($a->name)->assertSee($until)->assertSee($batch->period_end->format('j F Y'))
            ->assertSee('The owner and administrators never lose a licence')->assertSee('cannot be done online yet');
        $this->actingAs($team->owner)->get(route('licences.confirm', ['team' => $team->uuid, 'action' => 'cancel', 'batch' => $batch->uuid]))->assertOk()->assertSee($a->name);
    }

    public function test_reduce_and_cancel_are_refused_while_finance_cannot_be_told_and_change_nothing(): void
    {
        Http::fake();
        $team = $this->company();
        $batch = $this->paid($team, 3);
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'reduce']), ['batch' => $batch->uuid, 'quantity' => 1])->assertSessionHas('licence_error');
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'cancel']), ['batch' => $batch->uuid])->assertSessionHas('licence_error');
        $fresh = $batch->fresh();
        $this->assertNull($fresh->quantity_next);
        $this->assertNull($fresh->cancelled_at);
    }

    public function test_the_company_page_offers_renewal_changes_only_when_they_can_be_made_online(): void
    {
        // R-72 (F-01): no button that leads to a page where nothing can be done.
        config(['licensing.reminders.support_email' => 'support@infodot.co.za']);
        $team = $this->company();
        $this->paid($team, 3);

        $this->actingAs($team->owner)->get('/teams/'.$team->id)->assertOk()
            ->assertDontSee('Reduce at renewal')->assertDontSee('Stop renewing')
            ->assertSee('To renew with fewer licences or stop renewing, ask us')->assertSee('mailto:support@infodot.co.za', false);

        config(['licensing.finance.cancel_enabled' => true]);
        $this->actingAs($team->owner)->get('/teams/'.$team->id)->assertOk()
            ->assertSee('Reduce at renewal')->assertSee('Stop renewing')->assertDontSee('ask us at');
    }

    public function test_when_enabled_finance_is_told_first_and_a_failure_changes_nothing(): void
    {
        config(['licensing.finance.cancel_enabled' => true]);
        $team = $this->company();
        $batch = $this->paid($team, 3);

        Http::fake(['finance.example.test/api/licences/*' => Http::sequence()->push([], 500)->push(['ok' => true])->push(['ok' => true]), 'finance.example.test/*' => Http::response([], 404)]);
        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'cancel']), ['batch' => $batch->uuid])->assertSessionHas('licence_error');
        $this->assertNull($batch->fresh()->cancelled_at);

        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'reduce']), ['batch' => $batch->uuid, 'quantity' => 2])->assertSessionHas('licence_ok');
        $this->assertSame(2, $batch->fresh()->quantity_next);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/api/licences/cancel') && (json_decode($r->body(), true)['action'] ?? '') === 'reduce' && (json_decode($r->body(), true)['quantity_next'] ?? 0) === 2 && $r->hasHeader('X-InfoDot-Signature'));

        $this->actingAs($team->owner)->post(route('licences.act', ['team' => $team->uuid, 'action' => 'cancel']), ['batch' => $batch->uuid])->assertSessionHas('licence_ok');
        $this->assertNotNull($batch->fresh()->cancelled_at);
        $this->assertSame('active', $batch->fresh()->status, 'Cancelling stops renewal; the licences run to the end of the period.');
    }

    public function test_only_the_owner_can_act_and_an_administrator_gets_403(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $admin = $this->memberOf($team, 'admin');
        $m = $this->join($team);
        foreach (['release' => ['user' => $m->getKey()], 'cancel' => ['batch' => $batch->uuid], 'close' => ['batch' => $batch->uuid]] as $action => $data) {
            $this->actingAs($admin)->get(route('licences.confirm', ['team' => $team->uuid, 'action' => $action] + $data))->assertForbidden();
            $this->actingAs($admin)->post(route('licences.act', ['team' => $team->uuid, 'action' => $action]), $data)->assertForbidden();
        }
        $this->assertNotSame('released', LicenceAssignment::where('user_id', $m->getKey())->value('state') ?? 'active');
    }

    // ---------------------------------------------------------------- cross-tenant

    public function test_one_company_cannot_act_on_another_companys_people_or_purchases(): void
    {
        Http::fake();
        $a = $this->company('Company A');
        $b = $this->company('Company B');
        $this->paid($b, 2);
        $bMember = $this->join($b);
        $bBatch = LicenceBatch::where('team_id', $b->id)->firstOrFail();
        $this->join($a);

        // A's owner naming B's person or B's batch inside A's own URL: 404, nothing changes.
        $this->actingAs($a->owner)->get(route('licences.confirm', ['team' => $a->uuid, 'action' => 'release', 'user' => $bMember->getKey()]))->assertNotFound();
        $this->actingAs($a->owner)->post(route('licences.act', ['team' => $a->uuid, 'action' => 'release']), ['user' => $bMember->getKey()])->assertNotFound();
        $this->actingAs($a->owner)->post(route('licences.act', ['team' => $a->uuid, 'action' => 'cancel']), ['batch' => $bBatch->uuid])->assertNotFound();
        $this->actingAs($a->owner)->get(route('licences.confirm', ['team' => $a->uuid, 'action' => 'reduce', 'batch' => $bBatch->uuid, 'quantity' => 1]))->assertNotFound();
        // A's owner using B's URL: 404 (not a member of B).
        $this->actingAs($a->owner)->post(route('licences.act', ['team' => $b->uuid, 'action' => 'release']), ['user' => $bMember->getKey()])->assertNotFound();
        $this->actingAs($a->owner)->get(route('licences.buy', $b->uuid))->assertNotFound();
        $this->assertNotSame('released', LicenceAssignment::where('user_id', $bMember->getKey())->value('state'));
        $this->assertNull($bBatch->fresh()->cancelled_at);
    }

    public function test_the_settings_section_shows_only_this_companys_licences(): void
    {
        $a = $this->company('Alpha Co');
        $b = $this->company('Beta Co');
        $this->paid($b, 4);
        $this->join($b);
        $this->actingAs($a->owner)->get(route('teams.show', $a))->assertOk()->assertDontSee('4 licences')->assertDontSee('data-batch=', false);
        $this->actingAs($a->owner)->get(route('teams.show', $b))->assertForbidden();
    }

    public function test_the_section_shows_included_purchased_assigned_available_cost_renewal_and_history(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 3, 'year');
        $this->join($team);
        app(InviteTeamMember::class)->invite($team->owner, $team, 'later@example.test', 'member');
        $html = $this->actingAs($team->owner)->get(route('teams.show', $team))->assertOk()
            ->assertSee('Free licence')->assertSee('Paid licences bought')->assertSee('Licences in use')->assertSee('2 of 4', false)->assertSee('Held by unanswered invitations')->assertSee('Licences available')
            ->assertSee('Cost of your paid licences')->assertSee('297.00 ZAR a month', false)->assertSee('2 970.00 ZAR', false)->assertSee('Next renewal '.$batch->period_end->format('j M Y'))
            ->assertSee('Purchase history')->assertSee('Purchase paid and active')->assertSee('1 invitation is waiting')->assertSee('data-invoices-note', false)->getContent();
        $this->assertStringNotContainsString('three', strtolower(strip_tags($html)));
    }

    public function test_the_copy_has_no_leftover_three(): void
    {
        $files = array_merge(glob(resource_path('views/licences/*.php')), [resource_path('views/teams/_seats.blade.php'), resource_path('views/teams/team-member-manager.blade.php'), resource_path('views/teams/show.blade.php')]);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\bthree\b/i', (string) file_get_contents($file), basename($file));
        }
        $this->assertDoesNotMatchRegularExpression('/\bthree\b/i', (string) file_get_contents(app_path('Http/Controllers/TeamLicencesController.php')));

        $team = $this->company();
        $this->paid($team, 2);
        foreach ([route('teams.show', $team), route('licences.buy', $team->uuid)] as $url) {
            $this->assertStringNotContainsString('three', strtolower(strip_tags($this->actingAs($team->owner)->get($url)->getContent())));
        }
    }

    public function test_removing_a_member_names_who_loses_access(): void
    {
        $team = $this->company();
        $this->paid($team, 1);
        $m = $this->join($team);
        Livewire::actingAs($team->owner)->test(TeamMemberManager::class, ['team' => $team])
            ->call('confirmTeamMemberRemoval', $m->getKey())->assertSee($m->name.' loses access')->assertSee('nothing is deleted');
    }

    // ---------------------------------------------------------------- Finance read must not double count

    public function test_the_older_finance_read_never_adds_to_the_capacity_batches_already_give(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        Http::fake(['finance.example.test/api/entitlements/*' => Http::response(['status' => 'active', 'seat_limit' => 3, 'plan' => ['slug' => 'x']])]);
        app(FinanceEntitlements::class)->sync($team);

        $s = app(SeatLedger::class)->summary($team->fresh());
        $this->assertSame(0, TeamLicence::where('team_id', $team->id)->value('purchased_seats'));
        $this->assertSame(3, $s['capacity']);
    }

    public function test_a_pending_purchase_alone_adds_no_capacity_and_no_assignments(): void
    {
        $team = $this->company();
        app(LicenceBatches::class)->createPending($team, $team->owner, 3, 'month');
        $this->assertSame(1, app(SeatLedger::class)->summary($team)['capacity']);
        $this->assertSame(0, LicenceAssignment::count());
        $this->assertSame(0, app(SeatLedger::class)->summary($team)['available']);
    }
}
