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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-49. One row holds a person's ticket for a seminar. Buying again reused the row with what was refunded on the
 * earlier ticket still on it, so the new ticket could not be refunded; and a new checkout could replace the
 * reference of a ticket whose money was still held or on its way back.
 */
class SeminarTicketBoughtAgainTest extends TestCase
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

    public function test_a_ticket_bought_again_after_a_full_refund_starts_with_nothing_refunded(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');

        $first = $this->buy($seminar, $buyer);
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->financeSays('ticket.refunded', $first->ticket_ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'applied']);
        $this->assertSame('refunded', $first->fresh()->payment_status);

        $second = $this->buy($seminar, $buyer);

        $this->assertNotSame($first->ticket_ref, $second->ticket_ref);
        $this->assertSame(['registered', 'paid', 0, 20000], [$second->status, $second->payment_status, (int) $second->refunded_cents, $second->refundableCents()]);
        $this->assertNull($second->refund_pending_cents);
        $this->assertNull($second->dispute_status);

        // The second ticket is refunded in full when the buyer gives it up in time.
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->assertSame(20000, (int) $second->fresh()->refund_pending_cents);
        $this->financeSays('ticket.refunded', $second->ticket_ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'applied']);
        $this->assertSame(['refunded', 20000], [$second->fresh()->payment_status, (int) $second->fresh()->refunded_cents]);
    }

    public function test_a_new_ticket_cannot_be_started_while_the_refund_of_the_last_one_is_on_its_way(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');

        $first = $this->buy($seminar, $buyer);
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->assertNotNull($first->fresh()->refund_requested_at);

        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register')->assertSessionHasErrors('seminar');

        $this->assertSame($first->ticket_ref, $first->fresh()->ticket_ref, 'the reference Finance will confirm the refund against is still on the row');
        $this->financeSays('ticket.refunded', $first->ticket_ref, ['refund_amount_cents' => 20000, 'refunded_total_cents' => 20000, 'refund_key' => 'full'])->assertJson(['status' => 'applied']);
    }

    public function test_a_paid_ticket_given_up_after_the_refund_deadline_is_not_overwritten_by_a_new_purchase(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');

        $first = $this->buy($seminar, $buyer);
        $this->travelTo($seminar->starts_at->copy()->subHours(5));
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/unregister');
        $this->assertSame(['cancelled', 'paid'], [$first->fresh()->status, $first->fresh()->payment_status]);

        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register')->assertSessionHasErrors('seminar');

        $kept = $first->fresh();
        $this->assertSame([$first->ticket_ref, 'paid'], [$kept->ticket_ref, $kept->payment_status]);
        $this->assertNotNull($kept->paid_at);
        $this->assertSame(1, app(SeminarService::class)->hostRevenue($seminar)['tickets']);
    }
}
