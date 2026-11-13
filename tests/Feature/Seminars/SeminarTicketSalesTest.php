<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarHostAgreement;
use App\Models\SeminarPayoutAccount;
use App\Models\SeminarRegistration;
use App\Models\SeminarTicketSale;
use App\Models\User;
use App\Services\Privacy\SeminarDataExporter;
use App\Services\Seminars\CommissionInvoices;
use App\Services\Seminars\SeminarService;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-49, finding L-03. A registration is a person's current place and is reused when they buy again; each paid checkout is
 * its own sale. Commission invoices, receipts, the data export and the refund sweep read sales.
 */
class SeminarTicketSalesTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->secret = Str::random(32);
        config(['seminars.paid.agreement_approved' => true, 'seminars.paid.enabled' => true, 'seminars.paid.callback_secret' => $this->secret,
            'licensing.finance.url' => 'https://finance.test', 'licensing.finance.secret' => Str::random(32),
            'seminars.paid.issuer' => ['name' => 'InfoDot (Pty) Ltd', 'address' => '1 Test Street, Cape Town', 'vat_number' => '4123456789']]);
        Http::fake([
            'finance.test/api/seminar-tickets' => Http::response(['reference' => 'ref_1', 'authorization_url' => 'https://checkout.example.test/abc', 'status' => 'pending'], 201),
            'finance.test/api/seminar-tickets/*' => Http::response(['status' => 'ok']),
        ]);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function seminar(?User $host = null): Seminar
    {
        $host ??= $this->person('Host');
        SeminarPayoutAccount::firstOrCreate(['user_id' => $host->id], ['finance_account_id' => 77, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);
        SeminarHostAgreement::firstOrCreate(['user_id' => $host->id, 'version' => 'v1'], ['accepted_at' => now()]);

        return app(SeminarService::class)->save($host, null, ['title' => 'Pricing your first product', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room', 'price' => '200.00'], true);
    }

    /** @param  array<string, mixed>  $extra */
    private function financeSays(string $event, string $ref, array $extra = []): TestResponse
    {
        $body = (string) json_encode(['event' => $event, 'ticket_ref' => $ref, 'reference' => 'ref_1', 'amount_cents' => 20000, 'currency' => 'ZAR'] + $extra);
        $h = Signature::headers($this->secret, $body);

        return $this->call('POST', '/api/finance/ticket-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_TIMESTAMP' => $h['X-InfoDot-Timestamp'], 'HTTP_X_INFODOT_SIGNATURE' => $h['X-InfoDot-Signature']], $body);
    }

    private function buy(Seminar $seminar, User $buyer): SeminarRegistration
    {
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register');
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->firstOrFail();
        $this->financeSays('ticket.paid', $reg->ticket_ref)->assertJson(['status' => 'applied']);

        return $reg->fresh();
    }

    private function refundInFull(string $ref): void
    {
        $this->financeSays('ticket.refunded', $ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'applied']);
    }

    /** Buy, give the place up in time, have the refund confirmed, buy again. @return array{0: Seminar, 1: User, 2: string, 3: string} */
    private function boughtTwice(): array
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');

        $first = $this->buy($seminar, $buyer)->ticket_ref;
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->refundInFull($first);
        $second = $this->buy($seminar, $buyer)->ticket_ref;

        return [$seminar, $buyer, $first, $second];
    }

    public function test_a_confirmed_payment_writes_one_sale(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');
        $reg = $this->buy($seminar, $buyer);

        $sale = SeminarTicketSale::sole();
        $this->assertSame([$reg->id, $seminar->id, $buyer->id, $reg->ticket_ref, 20000, 'ZAR', 'paid', 0],
            [$sale->registration_id, $sale->seminar_id, $sale->user_id, $sale->ticket_ref, $sale->amount_cents, $sale->currency, $sale->state, $sale->refunded_cents]);
        $this->assertNotNull($sale->paid_at);
        $this->assertNull($sale->refunded_at);
    }

    public function test_buying_refunding_and_buying_again_leaves_two_sales_and_one_registration(): void
    {
        [$seminar, $buyer, $first, $second] = $this->boughtTwice();

        $this->assertSame(1, SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->count());
        $this->assertSame(2, SeminarTicketSale::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->count());

        $a = SeminarTicketSale::where('ticket_ref', $first)->sole();
        $b = SeminarTicketSale::where('ticket_ref', $second)->sole();
        $this->assertSame(['refunded', 20000], [$a->state, $a->refunded_cents]);
        $this->assertNotNull($a->refunded_at);
        $this->assertNotNull($a->paid_at, 'the first sale keeps its own payment date');
        $this->assertSame(['paid', 0], [$b->state, $b->refunded_cents]);
        $this->assertSame($a->registration_id, $b->registration_id);

        // The registration is the person's current place: the second ticket.
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->sole();
        $this->assertSame([$second, 'registered', 'paid'], [$reg->ticket_ref, $reg->status, $reg->payment_status]);
    }

    public function test_the_commission_invoice_totals_both_sales_and_the_refund(): void
    {
        [$seminar, , $first, $second] = $this->boughtTwice();

        $invoice = app(CommissionInvoices::class)->issueFor(User::findOrFail($seminar->host_id), now());

        $this->assertNotNull($invoice);
        $lines = collect($invoice->lines);
        $this->assertSame(2, $lines->where('type', 'sale')->count(), 'both sales are on the invoice');
        $this->assertEqualsCanonicalizing([$first, $second], $lines->where('type', 'sale')->pluck('ticket')->all());
        $this->assertSame([$first], $lines->where('type', 'refund')->pluck('ticket')->all(), 'the refund names the sale it was made against, not the ticket held now');
        $this->assertSame(20000, (int) $lines->sum('amount_cents'));   // 200 + 200 - 200
        $this->assertSame(2000, $invoice->fee_cents);                   // 10% of what was kept
    }

    public function test_a_refund_is_applied_to_the_sale_it_names(): void
    {
        [$seminar, $buyer, $first, $second] = $this->boughtTwice();

        // The host refunds part of the second ticket.
        $reg = SeminarRegistration::where('ticket_ref', $second)->sole();
        app(SeminarService::class)->hostRefund($seminar, $reg, 5000);
        $this->financeSays('ticket.refunded', $second, ['refund_amount_cents' => 5000, 'refunded_total_cents' => 5000, 'refund_key' => 'rf-'.$reg->id.'-1'])->assertJson(['status' => 'applied']);

        $a = SeminarTicketSale::where('ticket_ref', $first)->sole();
        $b = SeminarTicketSale::where('ticket_ref', $second)->sole();
        $this->assertSame(['refunded', 20000], [$a->state, $a->refunded_cents], 'the first sale is untouched');
        $this->assertSame(['partially_refunded', 5000, 15000], [$b->state, $b->refunded_cents, $b->refundableCents()]);

        $this->assertSame([[$a->id, 20000], [$b->id, 5000]], DB::table('seminar_refund_lines')->orderBy('id')->get(['sale_id', 'amount_cents'])->map(fn ($l) => [(int) $l->sale_id, (int) $l->amount_cents])->all());
        $this->assertSame(15000, app(SeminarService::class)->hostRevenue($seminar)['gross_cents']);
    }

    public function test_a_callback_about_the_earlier_sale_never_touches_the_current_ticket(): void
    {
        [$seminar, $buyer, $first, $second] = $this->boughtTwice();

        // Finance reports a lost dispute on the first sale, which is fully refunded: nothing more to take back, and the current place stays.
        $this->financeSays('ticket.dispute_resolved', $first, ['outcome' => 'lost'])->assertJson(['status' => 'applied']);

        $this->assertSame('chargeback', SeminarTicketSale::where('ticket_ref', $first)->sole()->state);
        $this->assertSame('paid', SeminarTicketSale::where('ticket_ref', $second)->sole()->state);
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->sole();
        $this->assertSame(['registered', 'paid', null], [$reg->status, $reg->payment_status, $reg->dispute_status]);
        $this->assertSame(1, DB::table('seminar_refund_lines')->count(), 'no second line for money that had already gone back');

        // A refund total that does not add up on the earlier sale is refused.
        $this->financeSays('ticket.refunded', $first, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'again'])->assertJson(['status' => 'mismatch']);
    }

    public function test_repeated_callbacks_are_applied_once(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');
        $reg = $this->buy($seminar, $buyer);

        $this->financeSays('ticket.paid', $reg->ticket_ref)->assertJson(['status' => 'duplicate']);
        $this->assertSame(1, SeminarTicketSale::count());

        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->refundInFull($reg->ticket_ref);
        $this->financeSays('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, DB::table('seminar_refund_lines')->count());
        $this->assertSame(['refunded', 20000], [SeminarTicketSale::sole()->state, SeminarTicketSale::sole()->refunded_cents]);

        // The paid callback of the earlier sale arriving again after a second purchase makes no third sale and asks for no refund.
        $second = $this->buy($seminar, $buyer);
        Http::fake(['finance.test/*' => Http::response(['status' => 'ok'])]);
        $this->financeSays('ticket.paid', $reg->ticket_ref)->assertJson(['status' => 'duplicate']);
        $this->assertSame(2, SeminarTicketSale::count());
        $this->assertSame('paid', $second->fresh()->payment_status);
    }

    public function test_the_sweep_asks_again_for_the_refund_of_the_sale_that_is_waiting(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');
        $reg = $this->buy($seminar, $buyer);
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');

        $sale = SeminarTicketSale::sole();
        $this->assertNotNull($sale->refund_requested_at, 'giving the place up in time marks the sale');

        $this->travel(11)->minutes();
        Http::fake(['finance.test/*' => Http::response(['status' => 'ok'])]);
        $this->assertSame(1, app(SeminarService::class)->retryRefunds());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$reg->ticket_ref.'/refund'));

        // Once confirmed, there is nothing left to ask for.
        $this->refundInFull($reg->ticket_ref);
        $this->assertSame(0, app(SeminarService::class)->retryRefunds());
    }

    public function test_cancelling_a_seminar_marks_every_sale_for_refund(): void
    {
        $seminar = $this->seminar();
        $this->buy($seminar, $this->person('A'));
        $this->buy($seminar, $this->person('B'));

        app(SeminarService::class)->cancel($seminar);

        $this->assertSame(2, SeminarTicketSale::whereNotNull('refund_requested_at')->count());
    }

    public function test_the_receipt_is_per_sale_and_only_the_buyers(): void
    {
        [$seminar, $buyer, $first, $second] = $this->boughtTwice();
        $a = SeminarTicketSale::where('ticket_ref', $first)->sole();
        $b = SeminarTicketSale::where('ticket_ref', $second)->sole();

        $this->actingAs($buyer)->get('/seminars/'.$seminar->id.'/receipt')->assertOk()->assertSee($second)->assertSee($b->receiptNumber())->assertSee($a->receiptNumber())->assertDontSee('Refunded');
        $this->actingAs($buyer)->get('/seminars/'.$seminar->id.'/receipt?sale='.$a->id)->assertOk()->assertSee($first)->assertSee('Refunded');

        $other = $this->person('Other');
        $this->actingAs($other)->get('/seminars/'.$seminar->id.'/receipt')->assertNotFound();
        $this->actingAs($other)->get('/seminars/'.$seminar->id.'/receipt?sale='.$a->id)->assertNotFound();
    }

    /** Item 6: the pages a person reads say payments are handled by Dot.Finance and name no payment provider by brand. */
    public function test_seminar_pages_say_dot_finance_and_name_no_payment_provider(): void
    {
        $seminar = $this->seminar();
        $host = User::findOrFail($seminar->host_id);
        $buyer = $this->person('Buyer');
        $visitor = $this->person('Visitor');
        $moderator = $this->person('Moderator');
        $moderator->forceFill(['is_moderator' => true])->save();
        $this->buy($seminar, $buyer);

        $pages = [
            'the seminar, before buying' => $this->actingAs($visitor)->get('/seminars/'.$seminar->id)->assertOk()->assertSee('Payments are handled by Dot.Finance'),
            'the seminar, for its host' => $this->actingAs($host)->get('/seminars/'.$seminar->id)->assertOk()->assertSee('Payments are handled by Dot.Finance'),
            'the receipt' => $this->actingAs($buyer)->get('/seminars/'.$seminar->id.'/receipt')->assertOk()->assertSee('Payments are handled by Dot.Finance'),
            'payouts' => $this->actingAs($host)->get('/seminars/payouts')->assertOk()->assertSee('Payments are handled by Dot.Finance'),
            'invoices' => $this->actingAs($host)->get('/seminars/invoices')->assertOk(),
            'moderators\' payments' => $this->actingAs($moderator)->get('/moderation/seminar-payments')->assertOk()->assertSee('Payments are handled by Dot.Finance'),
        ];

        foreach ($pages as $name => $page) {
            // The host agreement, shown under the payout form, is a legal text that has to name the payment provider; everything above it is the page's own copy.
            $own = $name === 'payouts' ? (string) strstr((string) $page->getContent(), 'id="agreement-h"', true) : (string) $page->getContent();
            $this->assertNotSame('', $own, $name);
            $this->assertStringNotContainsStringIgnoringCase('paystack', $own, $name);
        }
    }

    public function test_the_data_export_lists_every_purchase(): void
    {
        [, $buyer, $first, $second] = $this->boughtTwice();

        $export = app(SeminarDataExporter::class)->export($buyer);

        $this->assertCount(1, $export['registrations']);
        $this->assertSame([$first, $second], array_column($export['ticket_purchases'], 'ticket_ref'));
        $this->assertSame(['refunded', 'paid'], array_column($export['ticket_purchases'], 'state'));
    }

    public function test_the_sale_outlives_the_buyers_account_with_nobody_on_it(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');
        $this->buy($seminar, $buyer);

        DB::table('users')->where('id', $buyer->id)->delete();

        $sale = SeminarTicketSale::sole();
        $this->assertNull($sale->user_id);
        $this->assertSame(20000, $sale->amount_cents);
    }

    public function test_the_migration_backfills_one_sale_per_paid_registration_and_reverses(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(14)->setTime(12, 0));   // mid-month, so the days counted back below stay inside it
        $migration = require database_path('migrations/2026_11_13_130000_create_seminar_ticket_sales.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('seminar_ticket_sales'));
        $this->assertFalse(Schema::hasColumn('seminar_refund_lines', 'sale_id'));

        $host = $this->person('Host');
        $seminar = Seminar::create(['host_id' => $host->id, 'title' => 'Older seminar', 'summary' => 'An hour.', 'starts_at' => now()->addDays(2), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/x', 'status' => 'published', 'price_cents' => 20000, 'currency' => 'ZAR']);
        $rows = [
            ['paid', 'old-paid', now()->subDays(3), 0, null],
            ['partially_refunded', 'old-part', now()->subDays(2), 5000, now()->subDay()],
            ['refunded', 'old-full', now()->subDays(4), 20000, now()->subDay()],
            ['chargeback', 'old-charge', now()->subDays(5), 0, null],
        ];
        $ids = [];

        foreach ($rows as [$state, $ref, $paidAt, $refunded, $refundedAt]) {
            $ids[$ref] = SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => $this->person()->id, 'status' => $state === 'paid' || $state === 'partially_refunded' ? 'registered' : 'cancelled',
                'payment_status' => $state, 'ticket_ref' => $ref, 'amount_cents' => 20000, 'currency' => 'ZAR', 'paid_at' => $paidAt, 'refunded_cents' => $refunded, 'refunded_at' => $refundedAt])->id;
        }

        // Not sales: a free place, a checkout still waiting for payment, and a ticket whose buyer's account is gone (kept, with nobody on it).
        SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => $this->person()->id, 'status' => 'registered', 'payment_status' => 'free']);
        SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => $this->person()->id, 'status' => 'pending_payment', 'payment_status' => 'pending', 'ticket_ref' => 'old-pending', 'amount_cents' => 20000, 'currency' => 'ZAR']);
        SeminarRegistration::create(['seminar_id' => $seminar->id, 'user_id' => 999999, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'old-gone', 'amount_cents' => 20000, 'currency' => 'ZAR', 'paid_at' => now()->subDays(6)]);
        DB::table('seminar_refund_lines')->insert(['registration_id' => $ids['old-part'], 'seminar_id' => $seminar->id, 'host_id' => $host->id, 'amount_cents' => 5000, 'reason' => 'refund', 'created_at' => now()->subDay()]);
        DB::table('seminar_refund_lines')->insert(['registration_id' => $ids['old-full'], 'seminar_id' => $seminar->id, 'host_id' => $host->id, 'amount_cents' => 20000, 'reason' => 'refund', 'created_at' => now()->subDay()]);

        $migration->up();
        $migration->up();   // a second run changes nothing

        $sales = SeminarTicketSale::orderBy('ticket_ref')->get()->keyBy('ticket_ref');
        $this->assertSame(['old-charge', 'old-full', 'old-gone', 'old-paid', 'old-part'], $sales->keys()->all());
        $this->assertSame(['chargeback', 'refunded', 'paid', 'paid', 'partially_refunded'], $sales->pluck('state')->all());
        $this->assertSame([0, 20000, 0, 0, 5000], $sales->pluck('refunded_cents')->all());
        $this->assertNull($sales['old-gone']->user_id);
        $this->assertSame($ids['old-part'], $sales['old-part']->registration_id);
        $this->assertTrue($sales['old-paid']->paid_at->isSameDay(now()->subDays(3)));
        $this->assertNotNull($sales['old-full']->refunded_at);

        $this->assertSame([$sales['old-part']->id, $sales['old-full']->id], DB::table('seminar_refund_lines')->orderBy('id')->pluck('sale_id')->map(fn ($v) => (int) $v)->all());

        // What reads sales now sees the older tickets: 3 holding money (paid, part, gone), the commission run covers all five.
        $this->assertSame(3, app(SeminarService::class)->hostRevenue($seminar)['tickets']);
        $invoice = app(CommissionInvoices::class)->issueFor($host, now());
        $this->assertSame(5, collect($invoice?->lines)->where('type', 'sale')->count());
    }
}
