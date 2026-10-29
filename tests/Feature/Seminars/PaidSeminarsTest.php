<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarPayoutAccount;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Services\Seminars\SeminarService;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Paid seminars: the host sets the price, InfoDot keeps 10%, money moves only through Dot.Billing, a ticket exists only after its verified callback. */
class PaidSeminarsTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_SECRET = 'callback-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['seminars.paid.agreement_approved' => true, 'seminars.paid.enabled' => true, 'seminars.paid.callback_secret' => self::CALLBACK_SECRET, 'licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret', 'licensing.billing.platform' => 'infodot']);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function host(): User
    {
        $h = $this->person('Host');
        SeminarPayoutAccount::create(['user_id' => $h->id, 'billing_account_id' => 77, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);
        \App\Models\SeminarHostAgreement::create(['user_id' => $h->id, 'version' => 'v1', 'accepted_at' => now()]);

        return $h;
    }

    private function paidSeminar(User $host, array $over = []): Seminar
    {
        return app(SeminarService::class)->save($host, null, array_merge([
            'title' => 'Pricing your first product', 'summary' => 'A practical hour on pricing.', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room', 'price' => '200.00',
        ], $over), true);
    }

    private function ticketFake(string $ref = 'ignored'): void
    {
        Http::fake([
            'billing.test/api/seminar-tickets' => Http::response(['reference' => 'ref_1', 'authorization_url' => 'https://checkout.paystack.com/abc', 'status' => 'pending'], 201),
            'billing.test/api/seminar-tickets/*' => Http::response(['status' => 'ok']),
        ]);
    }

    private function billingCallback(string $event, string $ref, int $amount = 20000, string $currency = 'ZAR', ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => $event, 'ticket_ref' => $ref, 'reference' => 'ref_1', 'amount_cents' => $amount, 'currency' => $currency]);
        $h = Signature::headers($secret ?? self::CALLBACK_SECRET, $body);

        return $this->call('POST', '/api/billing/ticket-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_TIMESTAMP' => $h['X-InfoDot-Timestamp'], 'HTTP_X_INFODOT_SIGNATURE' => $h['X-InfoDot-Signature']], $body);
    }

    private function buy(Seminar $s, User $u): SeminarRegistration
    {
        $this->ticketFake();
        $this->actingAs($u)->post('/seminars/'.$s->id.'/register')->assertRedirect('https://checkout.paystack.com/abc');

        return SeminarRegistration::where('seminar_id', $s->id)->where('user_id', $u->id)->firstOrFail();
    }

    public function test_it_is_off_unless_switched_on(): void
    {
        config(['seminars.paid.enabled' => false]);
        $host = $this->host();

        $this->actingAs($host)->get('/seminars/payouts')->assertNotFound();
        $this->expectException(ValidationException::class);
        $this->paidSeminar($host);
    }

    public function test_a_host_needs_a_payout_account_and_a_price_within_bounds(): void
    {
        $nobody = $this->person();
        try {
            $this->paidSeminar($nobody);
            $this->fail('no payout account');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('where your earnings are paid', $e->errors()['price'][0]);
        }

        $host = $this->host();
        foreach (['1.00', '99999999'] as $bad) {
            try {
                $this->paidSeminar($host, ['price' => $bad]);
                $this->fail("price $bad accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('price', $e->errors());
            }
        }

        $this->assertSame(20000, $this->paidSeminar($host)->price_cents);
        $this->assertTrue(Seminar::first()->isPaid());
    }

    public function test_the_account_number_is_sent_to_billing_and_kept_nowhere_here(): void
    {
        Http::fake([
            'billing.test/api/payout-banks' => Http::response(['banks' => [['name' => 'Test Bank', 'code' => '058']]]),
            'billing.test/api/payout-accounts' => Http::response(['id' => 77, 'status' => 'active', 'last4' => '9876', 'bank_code' => '058'], 201),
        ]);
        Log::spy();
        $host = $this->person('Host');

        $this->actingAs($host)->get('/seminars/payouts')->assertOk()->assertSee('Test Bank');
        $this->actingAs($host)->post('/seminars/payouts', ['business_name' => 'Host Pty', 'settlement_bank' => '058', 'account_number' => '1234569876'])->assertRedirect('/seminars/payouts');

        $account = SeminarPayoutAccount::first();
        $this->assertSame(['9876', 77], [$account->account_last4, $account->billing_account_id]);
        $this->assertStringNotContainsString('1234569876', json_encode(\DB::table('seminar_payout_accounts')->get()));
        $this->assertStringNotContainsString('1234569876', json_encode(session()->all()));
        Log::shouldNotHaveReceived('warning');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/payout-accounts') && Signature::valid('platform-secret', $r->header('X-InfoDot-Timestamp')[0], $r->header('X-InfoDot-Signature')[0], $r->body(), 300) && ($r->header('X-InfoDot-Platform')[0] ?? '') === 'infodot');
    }

    public function test_a_bad_account_number_is_refused_and_not_flashed_back(): void
    {
        Http::fake();
        $host = $this->person();

        $this->actingAs($host)->post('/seminars/payouts', ['business_name' => 'Host', 'settlement_bank' => '058', 'account_number' => '12ab'])->assertSessionHasErrors('account_number');
        $this->assertNull(session()->getOldInput('account_number'));
        Http::assertNothingSent();
    }

    public function test_buying_holds_a_place_and_sends_the_attendee_to_paystack_but_is_not_yet_a_ticket(): void
    {
        $s = $this->paidSeminar($this->host());
        $buyer = $this->person('Buyer');

        $reg = $this->buy($s, $buyer);

        $this->assertSame(['pending_payment', 'pending', 20000], [$reg->status, $reg->payment_status, $reg->amount_cents]);
        Http::assertSent(function (Request $r) use ($buyer) {
            $b = json_decode($r->body(), true);

            return str_ends_with($r->url(), '/api/seminar-tickets') && $b['amount_cents'] === 20000 && $b['payout_account_id'] === 77 && $b['buyer_email'] === $buyer->email && $b['currency'] === 'ZAR';
        });
        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->actingAs($buyer)->post('/seminars/'.$s->id.'/join')->assertRedirect(route('seminars.show', $s));
    }

    public function test_only_a_verified_signed_callback_makes_a_ticket_and_a_repeat_changes_nothing(): void
    {
        $s = $this->paidSeminar($this->host());
        $reg = $this->buy($s, $this->person('Buyer'));

        $this->billingCallback('ticket.paid', $reg->ticket_ref, secret: 'wrong')->assertUnauthorized();
        $this->assertSame('pending_payment', $reg->fresh()->status);

        $this->billingCallback('ticket.paid', $reg->ticket_ref)->assertOk()->assertJson(['status' => 'applied']);
        $this->assertSame(['registered', 'paid'], [$reg->fresh()->status, $reg->fresh()->payment_status]);
        $this->billingCallback('ticket.paid', $reg->ticket_ref)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->actingAs($reg->user)->post('/seminars/'.$s->id.'/join')->assertRedirect('https://meet.example.test/room');
    }

    public function test_a_callback_for_a_different_amount_or_an_unknown_ticket_is_not_applied(): void
    {
        $s = $this->paidSeminar($this->host());
        $reg = $this->buy($s, $this->person());

        $this->billingCallback('ticket.paid', $reg->ticket_ref, amount: 100)->assertOk()->assertJson(['status' => 'mismatch']);
        $this->assertSame('pending_payment', $reg->fresh()->status);
        $this->billingCallback('ticket.paid', 'sem9-u9-zzzz')->assertOk()->assertJson(['status' => 'unknown']);
        $this->billingCallback('ticket.exploded', $reg->ticket_ref)->assertOk()->assertJson(['status' => 'ignored']);
        $this->post('/api/billing/ticket-events', [])->assertUnauthorized();
    }

    public function test_a_held_place_counts_against_capacity_and_expires_with_billing_told(): void
    {
        $s = $this->paidSeminar($this->host(), ['capacity' => 1]);
        $first = $this->buy($s, $this->person('First'));

        $this->ticketFake();
        $this->actingAs($this->person('Second'))->post('/seminars/'.$s->id.'/register')->assertSessionHasErrors('seminar');

        $this->travelTo(now()->addMinutes(31));
        $this->assertSame(1, app(SeminarService::class)->expireHolds());
        $this->assertSame('cancelled', $first->fresh()->status);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$first->ticket_ref.'/cancel'));

        $this->actingAs($this->person('Third'))->post('/seminars/'.$s->id.'/register')->assertRedirect('https://checkout.paystack.com/abc');
    }

    public function test_the_price_cannot_change_once_someone_is_buying(): void
    {
        $host = $this->host();
        $s = $this->paidSeminar($host);
        $this->buy($s, $this->person());

        $this->expectException(ValidationException::class);
        app(SeminarService::class)->save($host, $s, ['title' => $s->title, 'summary' => $s->summary, 'starts_at' => $s->starts_at->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => $s->join_url, 'price' => '300.00'], true);
    }

    public function test_cancelling_refunds_everyone_who_paid_and_releases_the_rest(): void
    {
        $host = $this->host();
        $s = $this->paidSeminar($host);
        $paid = $this->buy($s, $this->person('Paid'));
        $this->billingCallback('ticket.paid', $paid->ticket_ref);
        $pending = $this->buy($s, $this->person('Pending'));

        $this->actingAs($host)->post('/seminars/'.$s->id.'/cancel')->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$paid->ticket_ref.'/refund'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$pending->ticket_ref.'/cancel'));
        $this->assertNotNull($paid->fresh()->refund_requested_at);
        $this->assertSame('cancelled', $pending->fresh()->status);

        $this->billingCallback('ticket.refunded', $paid->ticket_ref)->assertJson(['status' => 'applied']);
        $this->assertSame(['refunded', 'cancelled'], [$paid->fresh()->payment_status, $paid->fresh()->status]);
    }

    public function test_a_payment_that_arrives_after_the_seminar_was_cancelled_is_refunded(): void
    {
        $host = $this->host();
        $s = $this->paidSeminar($host);
        $late = $this->buy($s, $this->person());
        app(SeminarService::class)->cancel($s->fresh());
        Http::fake(['billing.test/*' => Http::response(['status' => 'ok'])]);

        $this->billingCallback('ticket.paid', $late->ticket_ref)->assertJson(['status' => 'applied']);

        $this->assertNotNull($late->fresh()->refund_requested_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/refund'));
    }

    public function test_unconfirmed_refunds_are_asked_for_again(): void
    {
        $s = $this->paidSeminar($this->host());
        $reg = $this->buy($s, $this->person());
        $this->billingCallback('ticket.paid', $reg->ticket_ref);
        $reg->forceFill(['refund_requested_at' => now()->subMinutes(30)])->save();

        $this->assertSame(1, app(SeminarService::class)->retryRefunds());
    }

    public function test_the_host_sees_what_they_took_with_a_ten_percent_share_for_infodot(): void
    {
        $host = $this->host();
        $s = $this->paidSeminar($host);
        foreach (['A', 'B'] as $n) {
            $reg = $this->buy($s, $this->person($n));
            $this->billingCallback('ticket.paid', $reg->ticket_ref);
        }

        $r = app(SeminarService::class)->hostRevenue($s);
        $this->assertSame([2, 40000, 4000, 36000], [$r['tickets'], $r['gross_cents'], $r['platform_cents'], $r['host_cents']]);
        $this->actingAs($host)->get('/seminars/'.$s->id)->assertSee('InfoDot keeps R40.00', false)->assertSee('before Paystack');
    }

    public function test_the_page_says_what_a_ticket_costs_and_the_refund_rule(): void
    {
        $s = $this->paidSeminar($this->host());

        $this->actingAs($this->person())->get('/seminars/'.$s->id)->assertOk()->assertSee('Buy a ticket (R200.00)')->assertSee('full refund')->assertSee('If the host cancels the seminar you are always refunded in full');
        $this->actingAs($this->person())->get('/seminars')->assertSee('R200.00');
    }

    public function test_billing_being_down_leaves_nothing_held_and_nothing_charged(): void
    {
        $s = $this->paidSeminar($this->host());
        Http::fake(['billing.test/*' => Http::response([], 500)]);
        $buyer = $this->person();

        $this->actingAs($buyer)->post('/seminars/'.$s->id.'/register')->assertSessionHasErrors('seminar');

        $reg = SeminarRegistration::where('user_id', $buyer->id)->first();
        $this->assertSame(['cancelled', 'free'], [$reg->status, $reg->payment_status]);
    }

    public function test_a_checkout_page_that_is_not_paystack_is_refused(): void
    {
        $s = $this->paidSeminar($this->host());
        Http::fake(['billing.test/*' => Http::response(['reference' => 'r', 'authorization_url' => 'https://evil.example/pay', 'status' => 'pending'], 201)]);
        $buyer = $this->person();

        $this->actingAs($buyer)->post('/seminars/'.$s->id.'/register')->assertSessionHasErrors('seminar');
        $this->assertSame('cancelled', SeminarRegistration::where('user_id', $buyer->id)->value('status'));
    }
}
