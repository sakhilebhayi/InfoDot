<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarHostAgreement;
use App\Models\SeminarHostDebt;
use App\Models\SeminarPayoutAccount;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Seminars\SeminarService;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The host agreement (shown and accepted only once approved), the price gate that depends on it, and the mirror of chargeback debts Billing reports. */
class HostAgreementAndDebtsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['seminars.paid.enabled' => true, 'seminars.paid.callback_secret' => 'cb', 'licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret',
            'seminars.paid.agreement_approved' => false, 'seminars.paid.agreement_version' => 'v1']);
    }

    private function host(bool $withAccount = true): User
    {
        $h = User::factory()->create(['name' => 'Host', 'email_verified_at' => Carbon::now()]);

        if ($withAccount) {
            SeminarPayoutAccount::create(['user_id' => $h->id, 'billing_account_id' => 77, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);
        }

        return $h;
    }

    private function save(User $host, ?string $price = '200.00'): Seminar
    {
        return app(SeminarService::class)->save($host, null, ['title' => 'Pricing', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room', 'price' => $price], true);
    }

    private function billingCallback(array $body): TestResponse
    {
        $raw = json_encode($body);
        $h = Signature::headers('cb', $raw);

        return $this->call('POST', '/api/billing/ticket-events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_INFODOT_TIMESTAMP' => $h['X-InfoDot-Timestamp'], 'HTTP_X_INFODOT_SIGNATURE' => $h['X-InfoDot-Signature']], $raw);
    }

    private function debt(array $over = []): array
    {
        return array_merge(['event' => 'host_debt.opened', 'ticket_ref' => 'sem1-u2-abc', 'debt_id' => 9, 'payout_account_id' => 77, 'host_ref' => 'u1', 'currency' => 'ZAR', 'amount_cents' => 10000, 'recovered_cents' => 0, 'remaining_cents' => 10000, 'opened_at' => '2026-10-29T10:00:00+00:00'], $over);
    }

    public function test_nobody_can_charge_until_the_agreement_is_approved_and_the_host_accepted_it(): void
    {
        $host = $this->host();

        try {
            $this->save($host);
            $this->fail('not approved');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('once the host agreement has been approved', $e->errors()['price'][0]);
        }

        config(['seminars.paid.agreement_approved' => true]);
        try {
            $this->save($host);
            $this->fail('not accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('accept the host agreement', $e->errors()['price'][0]);
        }

        SeminarHostAgreement::create(['user_id' => $host->id, 'version' => 'v1', 'accepted_at' => now()]);
        $this->assertSame(20000, $this->save($host)->price_cents);
    }

    public function test_a_new_agreement_version_must_be_accepted_again(): void
    {
        config(['seminars.paid.agreement_approved' => true]);
        $host = $this->host();
        SeminarHostAgreement::create(['user_id' => $host->id, 'version' => 'v1', 'accepted_at' => now()]);
        config(['seminars.paid.agreement_version' => 'v2']);

        $this->expectException(ValidationException::class);
        $this->save($host);
    }

    public function test_free_seminars_never_need_the_agreement(): void
    {
        $host = $this->host(false);

        $this->assertNull($this->save($host, null)->price_cents);
    }

    public function test_the_page_does_not_show_the_agreement_text_before_it_is_approved(): void
    {
        $host = $this->host();
        Http::fake(['billing.test/*' => Http::response(['banks' => []])]);

        $page = $this->actingAs($host)->get('/seminars/payouts')->assertOk()->getContent();

        $this->assertStringContainsString('Paid seminars open once InfoDot has finished and approved the host agreement', $page);
        $this->assertStringNotContainsString('Chargebacks on your tickets', $page);
        $this->actingAs($host)->post('/seminars/payouts/agreement', ['accept' => '1'])->assertNotFound();
    }

    public function test_the_text_hosts_are_shown_carries_no_draft_markers_and_has_both_parts(): void
    {
        $text = (string) file_get_contents(resource_path('markdown/host-agreement.md'));

        foreach (['[', ']', 'DRAFT', 'Adviser', 'adviser', 'UNREVIEWED', 'owner:', 'placeholder'] as $marker) {
            $this->assertStringNotContainsString($marker, $text, "a draft marker ($marker) must not reach hosts");
        }

        $this->assertStringContainsString('Part A. Chargebacks on your tickets', $text);
        $this->assertStringContainsString('Part B. If a moderator removes your seminar', $text);

        config(['seminars.paid.agreement_approved' => true]);
        Http::fake(['billing.test/*' => Http::response(['banks' => []])]);
        $this->actingAs($this->host())->get('/seminars/payouts')->assertOk()->assertSee('If a moderator removes your seminar')->assertSee('support@infodot.co.za');
    }

    public function test_once_approved_the_host_reads_and_accepts_it_and_billing_is_told(): void
    {
        config(['seminars.paid.agreement_approved' => true]);
        $host = $this->host();
        Http::fake(['billing.test/api/payout-banks' => Http::response(['banks' => []]), 'billing.test/api/payout-accounts/77/agreement' => Http::response(['status' => 'accepted'], 201)]);

        $this->actingAs($host)->get('/seminars/payouts')->assertOk()->assertSee('Chargebacks on your tickets')->assertSee('Accept the agreement');
        $this->actingAs($host)->post('/seminars/payouts/agreement', [])->assertSessionHasErrors('accept');
        $this->actingAs($host)->post('/seminars/payouts/agreement', ['accept' => '1'])->assertRedirect('/seminars/payouts');

        $this->assertDatabaseHas('seminar_host_agreements', ['user_id' => $host->id, 'version' => 'v1']);
        Http::assertSent(function (Request $r) use ($host) {
            return str_ends_with($r->url(), '/api/payout-accounts/77/agreement') && $r['version'] === 'v1' && $r['accepted_by_ref'] === 'u'.$host->id
                && Signature::valid('platform-secret', $r->header('X-InfoDot-Timestamp')[0], $r->header('X-InfoDot-Signature')[0], $r->body(), 300);
        });
        $this->actingAs($host)->get('/seminars/payouts')->assertSee('Accepted')->assertDontSee('Accept the agreement');
    }

    public function test_the_agreement_needs_a_payout_account_and_billing_must_confirm_it(): void
    {
        config(['seminars.paid.agreement_approved' => true]);
        $nobody = $this->host(false);
        $host = $this->host();
        Http::fake(['billing.test/*' => Http::response([], 500)]);

        $this->actingAs($nobody)->post('/seminars/payouts/agreement', ['accept' => '1'])->assertSessionHasErrors('accept');
        $this->actingAs($host)->post('/seminars/payouts/agreement', ['accept' => '1'])->assertSessionHasErrors('accept');
        $this->assertSame(0, SeminarHostAgreement::count(), 'nothing is recorded as accepted if Billing was not told');
    }

    public function test_a_lost_chargeback_debt_is_mirrored_and_the_host_is_told_once(): void
    {
        $host = $this->host();
        $s = $this->save($host, null);
        SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => User::factory()->create()->id, 'status' => 'registered', 'ticket_ref' => 'sem1-u2-abc']);

        $this->billingCallback($this->debt(['host_ref' => 'u'.$host->id]))->assertOk()->assertJson(['status' => 'applied']);
        $this->billingCallback($this->debt(['host_ref' => 'u'.$host->id]))->assertOk();

        $d = SeminarHostDebt::first();
        $this->assertSame([$host->id, 10000, 10000, 'open'], [$d->user_id, $d->amount_cents, $d->remaining_cents, $d->status]);
        $this->assertSame([UserNotice::SEMINAR_DEBT_OPENED], UserNotice::where('user_id', $host->id)->pluck('kind')->all(), 'the same event twice is one notice');
        $page = $this->actingAs($host)->get('/notifications')->getContent();
        $this->assertStringContainsString('chargeback on a ticket for your seminar', $page);
        $this->assertStringNotContainsString('sem1-u2-abc', $page);
    }

    public function test_the_debt_follows_billing_through_recovery_clearing_reopening_and_write_off(): void
    {
        $host = $this->host();
        $ref = 'u'.$host->id;

        $this->billingCallback($this->debt(['host_ref' => $ref]))->assertJson(['status' => 'applied']);
        $this->billingCallback($this->debt(['host_ref' => $ref, 'event' => 'host_debt.cleared', 'recovered_cents' => 10000, 'remaining_cents' => 0, 'cleared_at' => '2026-10-29T12:00:00+00:00']))->assertJson(['status' => 'applied']);
        $this->assertSame(['cleared', 0], [SeminarHostDebt::first()->status, SeminarHostDebt::first()->remaining_cents]);

        $this->billingCallback($this->debt(['host_ref' => $ref, 'event' => 'host_debt.reopened', 'recovered_cents' => 9000, 'remaining_cents' => 1000, 'reopened_at' => '2026-10-29T13:00:00+00:00']))->assertJson(['status' => 'applied']);
        $this->assertSame(['open', 1000], [SeminarHostDebt::first()->status, SeminarHostDebt::first()->remaining_cents]);

        $this->billingCallback($this->debt(['host_ref' => $ref, 'event' => 'host_debt.written_off', 'recovered_cents' => 9000, 'remaining_cents' => 1000, 'written_off_at' => '2026-10-29T14:00:00+00:00', 'note' => 'Goodwill, first offence.']))->assertJson(['status' => 'applied']);
        $d = SeminarHostDebt::first();
        $this->assertSame(['written_off', 'Goodwill, first offence.'], [$d->status, $d->note]);
        $this->assertSame(1, SeminarHostDebt::count());
    }

    public function test_a_late_callback_never_moves_a_debt_backwards(): void
    {
        $host = $this->host();
        $ref = 'u'.$host->id;
        $this->billingCallback($this->debt(['host_ref' => $ref, 'event' => 'host_debt.cleared', 'recovered_cents' => 10000, 'remaining_cents' => 0, 'cleared_at' => '2026-10-29T12:00:00+00:00']));

        $this->billingCallback($this->debt(['host_ref' => $ref, 'opened_at' => '2026-10-29T10:00:00+00:00']))->assertJson(['status' => 'stale']);

        $this->assertSame('cleared', SeminarHostDebt::first()->status);
    }

    public function test_debt_callbacks_for_an_unknown_host_or_with_a_bad_reference_are_ignored(): void
    {
        $this->billingCallback($this->debt(['host_ref' => 'u999999']))->assertJson(['status' => 'unknown']);
        $this->billingCallback($this->debt(['host_ref' => 'someone']))->assertJson(['status' => 'ignored']);
        $this->billingCallback($this->debt(['debt_id' => 'nine']))->assertJson(['status' => 'ignored']);
        $this->assertSame(0, SeminarHostDebt::count());
    }

    public function test_debt_callbacks_need_the_signature_like_every_other_callback(): void
    {
        $this->postJson('/api/billing/ticket-events', $this->debt())->assertUnauthorized();
    }

    public function test_the_payouts_page_shows_a_host_what_they_owe_and_how_it_is_taken(): void
    {
        $host = $this->host();
        $this->billingCallback($this->debt(['host_ref' => 'u'.$host->id, 'recovered_cents' => 4000, 'remaining_cents' => 6000]));
        Http::fake(['billing.test/*' => Http::response(['banks' => []])]);

        $page = $this->actingAs($host)->get('/seminars/payouts')->assertOk()->getContent();

        $this->assertStringContainsString('What you owe InfoDot', $page);
        $this->assertStringContainsString('R100.00', $page);
        $this->assertStringContainsString('R60.00', $page);
        $this->assertStringContainsString('never more than half of your share', $page);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get('/seminars/payouts')->assertOk()->assertDontSee('What you owe InfoDot');
    }

    public function test_a_new_payout_account_reuses_an_accepted_agreement_by_telling_billing(): void
    {
        config(['seminars.paid.agreement_approved' => true]);
        $host = $this->host(false);
        SeminarHostAgreement::create(['user_id' => $host->id, 'version' => 'v1', 'accepted_at' => now()]);
        Http::fake(['billing.test/api/payout-accounts' => Http::response(['id' => 88, 'status' => 'active', 'last4' => '1111', 'bank_code' => '058'], 201), 'billing.test/api/payout-accounts/88/agreement' => Http::response(['status' => 'accepted'], 200)]);

        $this->actingAs($host)->post('/seminars/payouts', ['business_name' => 'Host', 'settlement_bank' => '058', 'account_number' => '1234561111'])->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/api/payout-accounts/88/agreement'));
    }
}
