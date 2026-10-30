<?php

namespace Tests\Feature\Hardening;

use App\Livewire\Answers;
use App\Livewire\Comments;
use App\Livewire\HiddenNotice;
use App\Livewire\ReportContent;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\Team;
use App\Models\User;
use App\Services\Seminars\CommissionInvoices;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\ResultStatus;
use Sentry\SentrySdk;
use Sentry\Transport\Result;
use Sentry\Transport\TransportInterface;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** Regression tests for the money-path and tenant-boundary findings of the 2026-10-30 enterprise audit. */
class MoneyAndTenantFixesTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret', 'seminars.paid.enabled' => true, 'seminars.paid.agreement_approved' => true, 'seminars.paid.callback_secret' => 'cb']);
        Http::fake(['billing.test/api/seminar-tickets' => Http::response(['reference' => 'r1', 'authorization_url' => 'https://checkout.paystack.com/abc', 'status' => 'pending'], 201), 'billing.test/*' => Http::response(['status' => 'ok'])]);
    }

    private function member(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function paidSeminar(User $host): Seminar
    {
        DB::table('seminar_payout_accounts')->insert(['user_id' => $host->id, 'billing_account_id' => 77, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);
        $s = app(SeminarService::class)->save($host, null, ['title' => 'Pricing', 'summary' => 'An hour.', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r'], true);
        $s->forceFill(['price_cents' => 20000, 'currency' => 'ZAR'])->save();

        return $s->fresh();
    }

    private function sentTo(string $needle): int
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), $needle))->count();
    }

    // ---- M-01 double refund ----------------------------------------------------------------------------------------------

    public function test_a_second_partial_refund_while_the_first_is_on_its_way_is_refused(): void
    {
        $host = $this->member('Host');
        $s = $this->paidSeminar($host);
        $reg = SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $this->member('Buyer')->id, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'tk1', 'amount_cents' => 20000, 'currency' => 'ZAR', 'paid_at' => now()]);

        app(SeminarService::class)->hostRefund($s, $reg, 5000);

        try {
            app(SeminarService::class)->hostRefund($s, $reg->fresh(), 5000);
            $this->fail('a second refund request must be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already on its way', $e->errors()['refund'][0]);
        }

        $this->assertSame(1, $this->sentTo('/refund'), 'Billing was asked once');
    }

    // ---- M-02 double click on "buy" --------------------------------------------------------------------------------------

    public function test_a_second_checkout_while_the_first_waits_for_billing_does_not_replace_the_ticket(): void
    {
        $s = $this->paidSeminar($this->member('Host'));
        $buyer = $this->member('Buyer');
        $inFlight = SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $buyer->id, 'status' => 'pending_payment', 'payment_status' => 'pending', 'ticket_ref' => 'first-ref', 'amount_cents' => 20000, 'currency' => 'ZAR',
            'hold_expires_at' => now()->addMinutes(30), 'checkout_url' => null]);

        try {
            app(SeminarService::class)->startCheckout($s, $buyer);
            $this->fail('a second checkout must wait');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('being prepared', $e->errors()['seminar'][0]);
        }

        $this->assertSame('first-ref', $inFlight->fresh()->ticket_ref, 'the first ticket reference was not replaced');
        $this->assertSame(0, $this->sentTo('/api/seminar-tickets'));
    }

    public function test_an_abandoned_checkout_attempt_can_be_started_again_after_a_minute(): void
    {
        $s = $this->paidSeminar($this->member('Host'));
        $buyer = $this->member('Buyer');
        $reg = SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $buyer->id, 'status' => 'pending_payment', 'payment_status' => 'pending', 'ticket_ref' => 'old-ref', 'amount_cents' => 20000, 'currency' => 'ZAR',
            'hold_expires_at' => now()->addMinutes(30), 'checkout_url' => null]);
        $reg->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();

        $this->assertSame('https://checkout.paystack.com/abc', app(SeminarService::class)->startCheckout($s, $buyer));
        $this->assertNotSame('old-ref', $reg->fresh()->ticket_ref);
    }

    // ---- M-03 a payment confirmed while the sweep runs --------------------------------------------------------------------

    public function test_the_sweep_never_releases_a_ticket_that_has_been_paid(): void
    {
        $s = $this->paidSeminar($this->member('Host'));
        $paid = SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $this->member('A')->id, 'status' => 'pending_payment', 'payment_status' => 'paid', 'ticket_ref' => 'paid-ref', 'amount_cents' => 20000, 'currency' => 'ZAR',
            'hold_expires_at' => now()->subMinute(), 'paid_at' => now()]);
        $unpaid = SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $this->member('B')->id, 'status' => 'pending_payment', 'payment_status' => 'pending', 'ticket_ref' => 'unpaid-ref', 'amount_cents' => 20000, 'currency' => 'ZAR',
            'hold_expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(SeminarService::class)->expireHolds());

        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('pending_payment', $paid->fresh()->status);
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertSame(1, $this->sentTo('/cancel'), 'Billing was told to drop only the unpaid hold');
    }

    // ---- M-04 monthly invoice run ----------------------------------------------------------------------------------------

    public function test_a_deleted_host_does_not_stop_the_monthly_invoice_run(): void
    {
        config(['seminars.paid.issuer' => ['name' => 'InfoDot (Pty) Ltd', 'address' => '1 Test Street', 'vat_number' => '4123456789']]);
        $month = now()->startOfMonth();
        DB::table('seminar_refund_lines')->insert(['registration_id' => 1, 'seminar_id' => 1, 'host_id' => 987654, 'amount_cents' => 100, 'reason' => 'refund', 'created_at' => $month->copy()->addDay()]);

        $this->assertSame(0, app(CommissionInvoices::class)->issueMonth($month));
    }

    // ---- T-01 tenant boundary --------------------------------------------------------------------------------------------

    private function companyWithPrivateQuestion(): array
    {
        $owner = $this->member('Owner');
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $leaver = $this->member('Leaver');
        $team->users()->attach($leaver, ['role' => 'member']);
        $leaver->forceFill(['current_team_id' => $team->id])->save();
        $question = $this->question($owner->fresh(), 'private', 'Our supplier contract terms');

        return [$owner, $team, $leaver->fresh(), $question];
    }

    public function test_a_removed_member_with_an_open_page_cannot_keep_reading_or_writing_the_company_thread(): void
    {
        [, $team, $leaver, $question] = $this->companyWithPrivateQuestion();
        $open = fn (string $class, array $params) => Livewire::actingAs($leaver)->test($class, $params);
        $likes = $open(Comments::class, ['model' => $question, 'question' => $question]);
        $posts = $open(Comments::class, ['model' => $question, 'question' => $question]);
        $answers = $open(Answers::class, ['question' => $question]);

        $team->removeUser($leaver);
        $this->actingAs(User::find($leaver->id));   // the next request loads the person fresh, as every real request does

        $likes->call('storeLike')->assertNotFound();
        $posts->set('newCommentState.body', 'Can I still post?')->assertNotFound();   // the update request itself is refused
        $answers->call('$refresh')->assertNotFound();
        $this->assertSame(0, $question->comments()->count());
    }

    public function test_the_ids_in_the_report_and_hidden_notice_components_cannot_be_changed_from_the_browser(): void
    {
        $asker = $this->member('Asker');
        $question = $this->question($asker, 'public');
        $answer = $this->answer($question, $this->member('Helper'));

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->member('Other'))->test(ReportContent::class, ['kind' => 'answer', 'itemId' => $answer->id])->set('itemId', 999);
    }

    public function test_the_hidden_notice_kind_is_locked_too(): void
    {
        $asker = $this->member('Asker');
        $question = $this->question($asker, 'public');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($asker)->test(HiddenNotice::class, ['kind' => 'question', 'itemId' => $question->id])->set('kind', 'answer');
    }

    // ---- T-02 team management --------------------------------------------------------------------------------------------

    public function test_an_ordinary_member_cannot_cancel_a_companys_pending_invitations(): void
    {
        [$owner, $team, $leaver] = $this->companyWithPrivateQuestion();
        $invitation = $team->teamInvitations()->create(['email' => 'pending@example.test', 'role' => 'member']);

        Livewire::actingAs($leaver)->test('teams.team-member-manager', ['team' => $team])->call('cancelTeamInvitation', $invitation->id)->assertForbidden();
        $this->assertSame(1, $team->teamInvitations()->count());

        Livewire::actingAs($owner)->test('teams.team-member-manager', ['team' => $team->fresh()])->call('cancelTeamInvitation', $invitation->id);
        $this->assertSame(0, $team->teamInvitations()->count());
    }

    // ---- T-03 seminar moderation conflicts of interest -------------------------------------------------------------------

    public function test_a_moderator_cannot_decide_a_report_or_an_appeal_about_their_own_seminar_or_remove_a_draft(): void
    {
        $mod = $this->moderator();
        $other = $this->moderator();
        $service = app(SeminarService::class);
        $s = $service->save($mod, null, ['title' => 'Mine', 'summary' => 'x', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r'], true);
        $report = $service->report($s, $this->member('Reporter'), 'spam', null);

        try {
            $service->dismissReport($report, $mod, 'Looks fine to me, I wrote it.');
            $this->fail('the host-moderator must not dismiss a report about their own seminar');
        } catch (ValidationException) {
            $this->assertSame('open', $report->fresh()->status);
        }

        $service->removeByModerator($s, $other, 'Looks like a scam to us.');
        $appeal = $service->appealRemoval($s->fresh(), $mod, 'This was a mistake, please reinstate it.');

        $this->expectException(ValidationException::class);
        $service->decideRemovalAppeal($appeal, $mod, true, 'I agree with myself.');
    }

    public function test_a_draft_seminar_cannot_be_removed_by_a_moderator(): void
    {
        $host = $this->member('Host');
        $draft = app(SeminarService::class)->save($host, null, ['title' => 'Draft', 'summary' => 'x', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r'], false);

        $this->expectException(ValidationException::class);
        app(SeminarService::class)->removeByModerator($draft, $this->moderator(), 'Should not be possible at all.');
    }

    // ---- O-01 error reporting --------------------------------------------------------------------------------------------

    public function test_an_unhandled_exception_reaches_sentry(): void
    {
        $transport = new class implements TransportInterface
        {
            public array $events = [];

            public function send(Event $event): Result
            {
                $this->events[] = $event;

                return new Result(ResultStatus::success());
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };
        $client = ClientBuilder::create(['dsn' => 'https://public@example.invalid/1'])->setTransport($transport)->getClient();
        SentrySdk::getCurrentHub()->bindClient($client);

        app(ExceptionHandler::class)->report(new \RuntimeException('boom from a test'));

        $this->assertCount(1, $transport->events);
    }
}
