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
 * R-49. A buyer whose held place ran out of time could start a new checkout before the sweep released the old one.
 * The new reference replaced the old on the row, Dot.Finance was never told to stop the old payment page, and a
 * payment made on it matched no ticket: money taken, no place, no refund. The old page is now cancelled when it is
 * replaced, and a payment for a reference InfoDot issued but no longer holds is sent back.
 */
class SeminarReplacedCheckoutTest extends TestCase
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

    public function test_starting_again_after_the_hold_ran_out_cancels_the_old_payment_page(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');

        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register');
        $old = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->firstOrFail()->ticket_ref;

        $this->travel(45)->minutes();
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register');
        $new = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->firstOrFail()->ticket_ref;

        $this->assertNotSame($old, $new);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$old.'/cancel'));
    }

    public function test_a_payment_for_a_reference_that_was_replaced_is_sent_back(): void
    {
        $seminar = $this->seminar();
        $buyer = $this->person('Buyer');

        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register');
        $old = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $buyer->id)->firstOrFail()->ticket_ref;
        $this->travel(45)->minutes();
        $this->actingAs($buyer)->post('/seminars/'.$seminar->id.'/register');

        $this->financeSays('ticket.paid', $old)->assertJson(['status' => 'unknown']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/seminar-tickets/'.$old.'/refund'));
    }

    public function test_a_payment_for_a_reference_infodot_never_issued_is_only_recorded(): void
    {
        $this->seminar();

        $this->financeSays('ticket.paid', 'someone-elses-reference')->assertJson(['status' => 'unknown']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/refund'));
    }
}
