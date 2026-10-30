<?php

namespace Tests\Feature\Hardening;

use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Who may do what with a paid seminar, route by route: the host, a ticket holder, a stranger and a moderator. Each denied case also checks that
 * nothing changed and that Billing was never called, because a refusal that still did the work would be worse than none.
 */
class SeminarAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private User $buyer;

    private User $stranger;

    private User $otherHost;

    private Seminar $seminar;

    private SeminarRegistration $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 's', 'seminars.paid.enabled' => true, 'seminars.paid.agreement_approved' => true, 'seminars.paid.callback_secret' => 'cb']);
        Http::fake(['billing.test/*' => Http::response(['banks' => [], 'status' => 'ok'])]);

        foreach (['host', 'buyer', 'stranger', 'otherHost'] as $who) {
            $this->$who = User::factory()->create(['name' => ucfirst($who).' Person', 'email_verified_at' => Carbon::now()]);
        }

        DB::table('seminar_payout_accounts')->insert(['user_id' => $this->host->id, 'billing_account_id' => 77, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);
        $this->seminar = app(SeminarService::class)->save($this->host, null, ['title' => 'Matrix seminar', 'summary' => 'An hour.', 'description' => 'Secret agenda text.', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/private-room-abc'], true);
        $this->seminar->forceFill(['price_cents' => 20000, 'currency' => 'ZAR'])->save();
        $this->ticket = SeminarRegistration::create(['seminar_id' => $this->seminar->id, 'user_id' => $this->buyer->id, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'tk1', 'amount_cents' => 20000, 'currency' => 'ZAR', 'paid_at' => now()]);
        DB::table('seminar_feedback')->insert(['seminar_id' => $this->seminar->id, 'user_id' => $this->buyer->id, 'rating' => 2, 'comment' => 'PRIVATE-NOTE-TO-HOST']);
        DB::table('seminar_commission_invoices')->insert(['host_id' => $this->host->id, 'period' => '2026-09', 'year' => 2026, 'seq' => 1, 'number' => 'INV-2026-0001', 'kind' => 'invoice', 'fee_cents' => 2000, 'vat_cents' => 261, 'excl_vat_cents' => 1739, 'lines' => '[]', 'issuer' => json_encode(['name' => 'InfoDot (Pty) Ltd', 'address' => '1 Test Street', 'vat_number' => '4123456789', 'vat_rate_percent' => 15, 'commission_includes_vat' => true]), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('seminar_host_debts')->insert(['user_id' => $this->host->id, 'billing_debt_id' => 5, 'amount_cents' => 777700, 'remaining_cents' => 777700, 'status' => 'open', 'opened_at' => now(), 'last_event_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function billingCalls(): int
    {
        return Http::recorded(fn ($r) => str_contains($r->url(), '/api/seminar-tickets'))->count();
    }

    // ---- host-only actions -----------------------------------------------------------------------------------------------

    public function test_only_the_host_can_open_the_edit_page_and_change_or_cancel_the_seminar(): void
    {
        $id = $this->seminar->id;
        $this->actingAs($this->host)->get("/seminars/$id/edit")->assertOk();

        foreach ([$this->buyer, $this->stranger, $this->otherHost] as $who) {
            $this->actingAs($who)->get("/seminars/$id/edit")->assertForbidden();
            $this->actingAs($who)->post("/seminars/$id/cancel")->assertForbidden();
            $this->actingAs($who)->put("/seminars/$id", ['title' => 'Hijacked'])->assertForbidden();
        }

        $this->assertSame('published', $this->seminar->fresh()->status);
        $this->assertSame('Matrix seminar', $this->seminar->fresh()->title);
        $this->assertSame('paid', $this->ticket->fresh()->payment_status);
        $this->assertSame(0, $this->billingCalls(), 'a refused cancel must not have refunded anyone');
    }

    public function test_only_the_host_can_mark_attendance_or_refund_a_ticket(): void
    {
        $id = $this->seminar->id;
        $buyerId = $this->buyer->id;

        foreach ([$this->buyer, $this->stranger, $this->otherHost] as $who) {
            $this->actingAs($who)->post("/seminars/$id/attendance/$buyerId", ['attended' => 1])->assertForbidden();
            $this->actingAs($who)->post("/seminars/$id/refund/$buyerId", ['amount' => '50.00'])->assertForbidden();
        }

        $this->assertNull($this->ticket->fresh()->attended_at);
        $this->assertNull($this->ticket->fresh()->refund_requested_at);
        $this->assertSame(0, $this->billingCalls());
    }

    public function test_a_host_cannot_refund_or_mark_a_person_on_another_hosts_seminar(): void
    {
        // otherHost hosts a different seminar and tries to use their own seminar id with the first seminar's buyer.
        $mine = app(SeminarService::class)->save($this->otherHost, null, ['title' => 'Mine', 'summary' => 'x', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/x'], true);

        $this->actingAs($this->otherHost)->post("/seminars/{$mine->id}/refund/{$this->buyer->id}", ['amount' => '50.00'])->assertStatus(404);
        $this->actingAs($this->otherHost)->post("/seminars/{$mine->id}/attendance/{$this->buyer->id}", ['attended' => 1]);   // answers with a message; what matters is that nothing changed

        $this->assertNull($this->ticket->fresh()->refund_requested_at);
        $this->assertSame(0, $this->billingCalls());
    }

    // ---- what each person is shown ---------------------------------------------------------------------------------------

    public function test_the_seminar_page_shows_the_host_the_ratings_and_people_and_nobody_else(): void
    {
        $id = $this->seminar->id;

        $hostPage = $this->actingAs($this->host)->get("/seminars/$id")->assertOk()->getContent();
        $this->assertStringContainsString('PRIVATE-NOTE-TO-HOST', $hostPage);
        $this->assertStringContainsString('Buyer Person', $hostPage);

        foreach ([$this->buyer, $this->stranger] as $who) {
            $page = $this->actingAs($who)->get("/seminars/$id")->assertOk()->getContent();
            $this->assertStringNotContainsString('PRIVATE-NOTE-TO-HOST', $page);
            $this->assertStringNotContainsString('private-room-abc', $page, 'the meeting link is never printed on the page');
            $this->assertStringNotContainsString('Buyer Person', $page);
        }
    }

    public function test_the_meeting_link_opens_only_for_a_registered_person_inside_the_window(): void
    {
        $id = $this->seminar->id;

        $this->actingAs($this->stranger)->post("/seminars/$id/join")->assertStatus(302);
        $this->assertNull($this->ticket->fresh()->attended_at);

        $this->actingAs($this->buyer)->post("/seminars/$id/join");   // two days before: the window is not open
        $this->assertNull($this->ticket->fresh()->attended_at, 'opening before the window must not count as attending');
    }

    public function test_the_room_and_the_small_room_refuse_people_who_are_not_registered(): void
    {
        $id = $this->seminar->id;
        $this->seminar->forceFill(['starts_at' => now()->addMinutes(5)])->save();

        // The rooms are switched off by default (404); switched on, a stranger gets 403. Either way, never a pass.
        foreach ([['p2p/join', []], ['p2p/poll', ['peer_id' => 'x']], ['room/token', []]] as [$path, $body]) {
            $this->assertContains($this->actingAs($this->stranger)->postJson("/seminars/$id/$path", $body)->getStatusCode(), [403, 404], $path);
        }

        config(['seminars.p2p.enabled' => true]);
        $this->assertSame(403, $this->actingAs($this->stranger)->postJson("/seminars/$id/p2p/join")->getStatusCode(), 'rooms on: a stranger is refused');
    }

    // ---- money pages -----------------------------------------------------------------------------------------------------

    public function test_a_receipt_belongs_to_the_person_who_paid(): void
    {
        $id = $this->seminar->id;

        $this->actingAs($this->buyer)->get("/seminars/$id/receipt")->assertOk();

        foreach ([$this->stranger, $this->host, $this->otherHost] as $who) {
            $this->actingAs($who)->get("/seminars/$id/receipt")->assertNotFound();
        }
    }

    public function test_commission_invoices_and_debts_are_the_hosts_own(): void
    {
        $invoice = DB::table('seminar_commission_invoices')->value('id');

        $this->actingAs($this->host)->get("/seminars/invoices/$invoice")->assertOk();
        $this->actingAs($this->host)->get('/seminars/payouts')->assertOk()->assertSee('What you owe InfoDot');

        foreach ([$this->otherHost, $this->buyer, $this->stranger] as $who) {
            $this->actingAs($who)->get("/seminars/invoices/$invoice")->assertNotFound();
            $list = $this->actingAs($who)->get('/seminars/invoices')->assertOk()->getContent();
            $this->assertStringNotContainsString('INV-2026-0001', $list);
            $payouts = $this->actingAs($who)->get('/seminars/payouts')->assertOk()->getContent();
            $this->assertStringNotContainsString('7 777', $payouts, "another host's debt amount is never shown");
            $this->assertStringNotContainsString('4321', $payouts, "another host's account digits are never shown");
        }
    }

    // ---- exports ---------------------------------------------------------------------------------------------------------

    public function test_the_data_download_holds_only_the_signed_in_persons_data(): void
    {
        $mine = $this->actingAs($this->buyer)->get('/account/seminar-data')->assertOk()->streamedContent();
        $this->assertStringContainsString('tk1', $mine);
        $this->assertStringNotContainsString('4321', $mine);
        $this->assertStringNotContainsString('777700', $mine);
        $this->assertStringNotContainsString('Host Person', $mine);

        $stranger = $this->actingAs($this->stranger)->get('/account/seminar-data')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('tk1', $stranger);
        $this->assertStringNotContainsString('Matrix seminar', $stranger);
    }

    // ---- moderation ------------------------------------------------------------------------------------------------------

    public function test_the_seminar_moderation_screens_are_for_moderators_only(): void
    {
        $id = $this->seminar->id;

        foreach ([$this->host, $this->buyer, $this->stranger] as $who) {
            $this->actingAs($who)->get('/moderation/seminars')->assertForbidden();
            $this->actingAs($who)->get('/moderation/seminar-payments')->assertForbidden();
            $this->actingAs($who)->post("/moderation/seminars/$id/remove", ['reason' => 'A reason that is long enough.'])->assertForbidden();
        }

        $this->assertNull($this->seminar->fresh()->removed_at);
        $this->assertSame(0, $this->billingCalls());
    }

    // ---- signed out ------------------------------------------------------------------------------------------------------

    public function test_signed_out_visitors_are_sent_to_sign_in_for_every_seminar_page(): void
    {
        $id = $this->seminar->id;

        foreach (["/seminars/$id", "/seminars/$id/edit", "/seminars/$id/receipt", '/seminars/payouts', '/seminars/invoices', '/account/seminar-data', '/moderation/seminars'] as $url) {
            $this->get($url)->assertRedirect();
        }

        $this->post("/seminars/$id/cancel")->assertRedirect();
        $this->post("/seminars/$id/refund/{$this->buyer->id}", ['amount' => '1.00'])->assertRedirect();
        $this->assertSame(0, $this->billingCalls());
    }
}
