<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarCommissionInvoice;
use App\Models\SeminarPayoutAccount;
use App\Models\SeminarRegistration;
use App\Models\SeminarSellerProfile;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Seminars\CommissionInvoices;
use App\Services\Seminars\SeminarService;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Attendee refunds, partial refunds, disputes, receipts and commission invoices for paid seminars. */
class SeminarRefundsAndTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['seminars.paid.agreement_approved' => true, 'seminars.paid.enabled' => true, 'seminars.paid.callback_secret' => 'cb', 'licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret',
            'seminars.paid.issuer' => ['name' => 'InfoDot (Pty) Ltd', 'address' => '1 Test Street, Cape Town', 'vat_number' => '4123456789']]);
        Http::fake([
            'billing.test/api/seminar-tickets' => Http::response(['reference' => 'ref_1', 'authorization_url' => 'https://checkout.paystack.com/abc', 'status' => 'pending'], 201),
            'billing.test/api/seminar-tickets/*' => Http::response(['status' => 'ok']),
        ]);
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

    private function seminar(User $host, int $daysAhead = 3): Seminar
    {
        return app(SeminarService::class)->save($host, null, ['title' => 'Pricing your first product', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays($daysAhead)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room', 'price' => '200.00'], true);
    }

    private function cb(string $event, string $ref, array $extra = [], int $amount = 20000): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => $event, 'ticket_ref' => $ref, 'reference' => 'ref_1', 'amount_cents' => $amount, 'currency' => 'ZAR'] + $extra);
        $h = Signature::headers('cb', $body);

        return $this->call('POST', '/api/billing/ticket-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_TIMESTAMP' => $h['X-InfoDot-Timestamp'], 'HTTP_X_INFODOT_SIGNATURE' => $h['X-InfoDot-Signature']], $body);
    }

    private function paidTicket(Seminar $s, ?User $u = null): SeminarRegistration
    {
        $u ??= $this->person('Buyer');
        $this->actingAs($u)->post('/seminars/'.$s->id.'/register');
        $reg = SeminarRegistration::where('seminar_id', $s->id)->where('user_id', $u->id)->firstOrFail();
        $this->cb('ticket.paid', $reg->ticket_ref)->assertJson(['status' => 'applied']);

        return $reg->fresh();
    }

    public function test_cancelling_a_day_or_more_before_refunds_in_full(): void
    {
        $s = $this->seminar($this->host());
        $reg = $this->paidTicket($s);

        $this->actingAs($reg->user)->post('/seminars/'.$s->id.'/unregister')->assertRedirect();

        $reg->refresh();
        $this->assertSame('cancelled', $reg->status);
        $this->assertNotNull($reg->refund_requested_at);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$reg->ticket_ref.'/refund') && $r->body() === '[]');

        $this->cb('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'applied']);
        $this->assertSame(['refunded', 20000], [$reg->fresh()->payment_status, $reg->fresh()->refunded_cents]);
        $this->assertSame([UserNotice::SEMINAR_REFUNDED], UserNotice::where('user_id', $reg->user_id)->pluck('kind')->all());
    }

    public function test_cancelling_inside_the_window_frees_the_place_with_no_refund(): void
    {
        $s = $this->seminar($this->host(), 3);
        $reg = $this->paidTicket($s);
        $this->travelTo($s->starts_at->copy()->subHours(5));
        Http::fake();

        $this->actingAs($reg->user)->post('/seminars/'.$s->id.'/unregister')->assertRedirect();

        $reg->refresh();
        $this->assertSame(['cancelled', 'paid', null], [$reg->status, $reg->payment_status, $reg->refund_requested_at]);
        Http::assertNothingSent();
        $this->assertSame(1, app(SeminarService::class)->hostRevenue($s)['tickets'], 'the host keeps what was paid');
    }

    public function test_the_page_states_the_rule_on_the_cancel_button(): void
    {
        $s = $this->seminar($this->host());
        $reg = $this->paidTicket($s);

        $this->actingAs($reg->user)->get('/seminars/'.$s->id)->assertSee('Cancel and get a full refund')->assertSee('View your receipt');
        $this->travelTo($s->starts_at->copy()->subHours(2));
        $this->actingAs($reg->user)->get('/seminars/'.$s->id)->assertSee('no refund: starts in under 24 hours');
    }

    public function test_a_host_refunds_part_of_a_ticket_and_each_refund_is_applied_once(): void
    {
        $host = $this->host();
        $s = $this->seminar($host);
        $reg = $this->paidTicket($s);

        $this->actingAs($host)->post('/seminars/'.$s->id.'/refund/'.$reg->user_id, ['amount' => '50.00'])->assertRedirect();
        Http::assertSent(function (Request $r) {
            $b = json_decode($r->body(), true);

            return str_ends_with($r->url(), '/refund') && $b['amount_cents'] === 5000 && str_starts_with($b['refund_key'], 'rf-');
        });
        $key1 = 'rf-'.$reg->id.'-1';

        $this->cb('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 5000, 'refunded_total_cents' => 5000, 'refund_key' => $key1])->assertJson(['status' => 'applied']);
        $this->cb('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 5000, 'refunded_total_cents' => 5000, 'refund_key' => $key1])->assertJson(['status' => 'duplicate']);
        $this->assertSame(['partially_refunded', 5000, 'registered'], [$reg->fresh()->payment_status, $reg->fresh()->refunded_cents, $reg->fresh()->status]);

        $this->actingAs($host)->post('/seminars/'.$s->id.'/refund/'.$reg->user_id, ['amount' => '25.00'])->assertRedirect();
        $this->cb('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 2500, 'refunded_total_cents' => 7500, 'refund_key' => 'rf-'.$reg->id.'-2'])->assertJson(['status' => 'applied']);
        $this->assertSame(7500, $reg->fresh()->refunded_cents);

        $r = app(SeminarService::class)->hostRevenue($s);
        $this->assertSame([12500, 1250, 11250], [$r['gross_cents'], $r['platform_cents'], $r['host_cents']], 'revenue and commission follow the net amount');
    }

    public function test_a_refund_is_refused_for_too_much_for_someone_elses_seminar_and_while_disputed(): void
    {
        $host = $this->host();
        $s = $this->seminar($host);
        $reg = $this->paidTicket($s);

        $this->actingAs($host)->post('/seminars/'.$s->id.'/refund/'.$reg->user_id, ['amount' => '250.00'])->assertSessionHasErrors('refund');
        $this->actingAs($host)->post('/seminars/'.$s->id.'/refund/'.$reg->user_id, ['amount' => 'abc'])->assertSessionHasErrors('amount');
        $this->actingAs($this->person('Other'))->post('/seminars/'.$s->id.'/refund/'.$reg->user_id, ['amount' => '10'])->assertForbidden();

        $this->cb('ticket.disputed', $reg->ticket_ref)->assertJson(['status' => 'applied']);
        $this->actingAs($host)->post('/seminars/'.$s->id.'/refund/'.$reg->user_id, ['amount' => '10'])->assertSessionHasErrors('refund');
        $this->assertSame(0, $reg->fresh()->refunded_cents);
    }

    public function test_a_refund_callback_that_does_not_add_up_is_not_applied(): void
    {
        $s = $this->seminar($this->host());
        $reg = $this->paidTicket($s);

        $this->cb('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 30000, 'refunded_total_cents' => 30000, 'refund_key' => 'x'])->assertJson(['status' => 'mismatch']);
        $this->assertSame(0, $reg->fresh()->refunded_cents);
    }

    public function test_a_dispute_tells_the_host_and_a_lost_one_takes_the_ticket_back(): void
    {
        $host = $this->host();
        $s = $this->seminar($host);
        $reg = $this->paidTicket($s);

        $this->cb('ticket.disputed', $reg->ticket_ref)->assertJson(['status' => 'applied']);
        $this->assertSame('open', $reg->fresh()->dispute_status);
        $this->assertSame([UserNotice::SEMINAR_DISPUTE], UserNotice::where('user_id', $host->id)->pluck('kind')->all());

        $this->cb('ticket.dispute_resolved', $reg->ticket_ref, ['outcome' => 'lost'])->assertJson(['status' => 'applied']);
        $reg->refresh();
        $this->assertSame(['chargeback', 'cancelled', 'lost'], [$reg->payment_status, $reg->status, $reg->dispute_status]);
        $this->assertSame(20000, (int) DB::table('seminar_refund_lines')->where('reason', 'chargeback')->value('amount_cents'));
        $this->assertSame(0, app(SeminarService::class)->hostRevenue($s)['tickets']);
    }

    public function test_a_won_dispute_leaves_the_ticket_alone(): void
    {
        $s = $this->seminar($this->host());
        $reg = $this->paidTicket($s);
        $this->cb('ticket.disputed', $reg->ticket_ref);

        $this->cb('ticket.dispute_resolved', $reg->ticket_ref, ['outcome' => 'won'])->assertJson(['status' => 'applied']);

        $this->assertSame(['paid', 'registered', 'won'], [$reg->fresh()->payment_status, $reg->fresh()->status, $reg->fresh()->dispute_status]);
    }

    public function test_moderators_see_disputes_and_unconfirmed_refunds(): void
    {
        $s = $this->seminar($this->host());
        $reg = $this->paidTicket($s);
        $this->cb('ticket.disputed', $reg->ticket_ref);
        $mod = $this->person('Mod');
        $mod->forceFill(['is_moderator' => true])->save();

        $this->actingAs($this->person('Nobody'))->get('/moderation/seminar-payments')->assertForbidden();
        $this->actingAs($mod->fresh())->get('/moderation/seminar-payments')->assertOk()->assertSee($reg->ticket_ref)->assertSee('Dispute open');
    }

    public function test_the_receipt_is_the_buyers_and_says_who_sells(): void
    {
        $host = $this->host();
        $s = $this->seminar($host);
        $reg = $this->paidTicket($s);

        $page = $this->actingAs($reg->user)->get('/seminars/'.$s->id.'/receipt')->assertOk()->getContent();
        $this->assertStringContainsString('Receipt', $page);
        $this->assertStringContainsString('R200.00', $page);
        $this->assertStringContainsString('Host', $page);
        $this->assertStringNotContainsString('Tax invoice', $page);
        $this->actingAs($this->person('Other'))->get('/seminars/'.$s->id.'/receipt')->assertNotFound();
    }

    public function test_a_tax_invoice_needs_the_switch_a_vat_number_and_the_hosts_authority(): void
    {
        $host = $this->host();
        $s = $this->seminar($host);
        $reg = $this->paidTicket($s);
        SeminarSellerProfile::create(['user_id' => $host->id, 'trading_name' => 'Host Trading', 'vat_number' => '4987654321', 'authorises_receipts' => true]);

        $this->actingAs($reg->user)->get('/seminars/'.$s->id.'/receipt')->assertSee('Host Trading')->assertDontSee('Tax invoice');

        config(['seminars.paid.issue_tax_invoices' => true]);
        $page = $this->actingAs($reg->user)->get('/seminars/'.$s->id.'/receipt')->getContent();
        $this->assertStringContainsString('Tax invoice', $page);
        $this->assertStringContainsString('4987654321', $page);
        $this->assertStringContainsString('includes VAT of R26.09 at 15%', $page, '20000 x 15 / 115 rounded half up');

        SeminarSellerProfile::where('user_id', $host->id)->update(['authorises_receipts' => false]);
        $this->actingAs($reg->user)->get('/seminars/'.$s->id.'/receipt')->assertDontSee('Tax invoice');
    }

    public function test_seller_details_check_the_vat_number_shape(): void
    {
        $host = $this->host();

        foreach (['12345', '5123456789', 'abcdefghij'] as $bad) {
            $this->actingAs($host)->post('/seminars/payouts/seller', ['trading_name' => 'Host', 'vat_number' => $bad])->assertSessionHasErrors('vat_number');
        }
        $this->actingAs($host)->post('/seminars/payouts/seller', ['trading_name' => 'Host', 'vat_number' => '4123456789', 'authorises_receipts' => '1'])->assertRedirect('/seminars/payouts');
        $this->assertSame(['4123456789', true], [SeminarSellerProfile::first()->vat_number, SeminarSellerProfile::first()->authorises_receipts]);
    }

    public function test_the_monthly_commission_invoice_takes_the_vat_out_of_the_ten_percent(): void
    {
        $host = $this->host();
        $s = $this->seminar($host, 40);
        $this->paidTicket($s, $this->person('A'));
        $this->paidTicket($s, $this->person('B'));

        $invoice = app(CommissionInvoices::class)->issueFor($host, now());

        $this->assertSame(['invoice', 4000, 522, 3478], [$invoice->kind, $invoice->fee_cents, $invoice->vat_cents, $invoice->excl_vat_cents]);
        $this->assertSame('INF-'.now()->format('Y').'-0001', $invoice->number);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame($invoice->id, app(CommissionInvoices::class)->issueFor($host, now())->id, 'issuing again returns the same invoice');
        $this->assertSame(1, SeminarCommissionInvoice::count());

        $page = $this->actingAs($host)->get('/seminars/invoices/'.$invoice->id)->assertOk()->getContent();
        $this->assertStringContainsString('InfoDot (Pty) Ltd', $page);
        $this->assertStringContainsString('4123456789', $page);
        $this->assertStringContainsString('R40.00', $page);
        $this->actingAs($this->person('Other'))->get('/seminars/invoices/'.$invoice->id)->assertNotFound();
        $this->actingAs($host)->get('/seminars/invoices')->assertOk()->assertSee($invoice->number);
    }

    public function test_a_month_of_refunds_is_a_credit_note_and_numbers_do_not_repeat(): void
    {
        $host = $this->host();
        $s = $this->seminar($host, 90);
        $reg = $this->paidTicket($s);
        $first = app(CommissionInvoices::class)->issueFor($host, now());

        $this->travelTo(now()->addMonth()->startOfMonth()->addDay());
        $this->cb('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'applied']);

        $credit = app(CommissionInvoices::class)->issueFor($host, now());
        $this->assertSame(['credit_note', -2000, -261], [$credit->kind, $credit->fee_cents, $credit->vat_cents]);
        $this->assertNotSame($first->number, $credit->number);
        $this->assertStringEndsWith('-0002', $credit->number);
    }

    public function test_nothing_is_issued_until_infodots_own_details_are_set(): void
    {
        $host = $this->host();
        $s = $this->seminar($host, 40);
        $this->paidTicket($s);
        config(['seminars.paid.issuer' => ['name' => null, 'address' => null, 'vat_number' => null]]);

        $this->assertNull(app(CommissionInvoices::class)->issueFor($host, now()));
        $this->artisan('seminars:invoice-commission', ['--month' => now()->format('Y-m')])->assertSuccessful()->expectsOutputToContain('nothing issued');
        $this->assertSame(0, SeminarCommissionInvoice::count());
    }

    public function test_the_command_invoices_every_host_with_activity_that_month(): void
    {
        $h1 = $this->host();
        $h2 = $this->host();
        $this->paidTicket($this->seminar($h1, 40));
        $this->paidTicket($this->seminar($h2, 40));

        $this->artisan('seminars:invoice-commission', ['--month' => now()->format('Y-m')])->assertSuccessful()->expectsOutputToContain('Issued 2 invoice');
        $this->assertSame(2, SeminarCommissionInvoice::count());
    }

    public function test_a_host_cannot_change_the_refund_rule_or_charge_without_the_switch(): void
    {
        config(['seminars.paid.enabled' => false]);
        $host = $this->host();

        $this->actingAs($host)->get('/seminars/invoices')->assertNotFound();
        $this->expectException(ValidationException::class);
        $this->seminar($host);
    }
}
