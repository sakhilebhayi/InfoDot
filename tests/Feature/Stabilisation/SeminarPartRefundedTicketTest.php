<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Seminar;
use App\Models\SeminarHostAgreement;
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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-49. A ticket the host refunded in part still holds the rest of the buyer's money. Cancelling the seminar asked
 * for refunds of fully paid tickets only, so the rest was never returned while the notice said refunded in full, and
 * a refund of such a ticket that did not reach Finance was never asked for again.
 */
class SeminarPartRefundedTicketTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->secret = Str::random(32);
        config(['seminars.paid.agreement_approved' => true, 'seminars.paid.enabled' => true, 'seminars.paid.callback_secret' => $this->secret,
            'licensing.finance.url' => 'https://finance.test', 'licensing.finance.secret' => Str::random(32)]);
        Http::fake([
            'finance.test/api/seminar-tickets' => Http::response(['reference' => 'ref_1', 'authorization_url' => 'https://checkout.example.test/abc', 'status' => 'pending'], 201),
            'finance.test/api/seminar-tickets/*' => Http::response(['status' => 'ok']),
        ]);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function seminar(): Seminar
    {
        $host = $this->person('Host');
        SeminarPayoutAccount::create(['user_id' => $host->id, 'finance_account_id' => 77, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);
        SeminarHostAgreement::create(['user_id' => $host->id, 'version' => 'v1', 'accepted_at' => now()]);

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

    public function test_cancelling_a_seminar_refunds_the_rest_of_a_ticket_that_was_already_refunded_in_part(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');
        $reg = $this->buy($seminar, $buyer);

        app(SeminarService::class)->hostRefund($seminar, $reg, 5000);
        $this->financeSays('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 5000, 'refunded_total_cents' => 5000, 'refund_key' => 'rf-'.$reg->id.'-1'])->assertJson(['status' => 'applied']);
        $this->assertSame('partially_refunded', $reg->fresh()->payment_status);
        Http::fake(['finance.test/api/seminar-tickets/*' => Http::response(['status' => 'ok'])]);

        app(SeminarService::class)->cancel($seminar->fresh());

        $this->assertNotNull($reg->fresh()->refund_requested_at, 'the rest of the ticket was not asked for');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$reg->ticket_ref.'/refund') && ($r->data()['amount_cents'] ?? null) === 15000);
    }

    public function test_a_refund_of_a_part_refunded_ticket_that_did_not_reach_finance_is_asked_for_again(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');
        $reg = $this->buy($seminar, $buyer);
        app(SeminarService::class)->hostRefund($seminar, $reg, 5000);
        $this->financeSays('ticket.refunded', $reg->ticket_ref, ['refund_amount_cents' => 5000, 'refunded_total_cents' => 5000, 'refund_key' => 'rf-'.$reg->id.'-1'])->assertJson(['status' => 'applied']);

        Http::fake(['finance.test/*' => Http::response([], 503)]);
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->assertNotNull($reg->fresh()->refund_requested_at);

        $this->travel(11)->minutes();
        Http::fake(['finance.test/api/seminar-tickets/*' => Http::response(['status' => 'ok'])]);

        $this->assertSame(1, app(SeminarService::class)->retryRefunds());
    }
}
