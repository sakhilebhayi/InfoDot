<?php

namespace Tests\Feature\Licensing;

use App\Actions\Jetstream\InviteTeamMember;
use App\Models\LicenceBatch;
use App\Models\PlatformPaymentConfirmation;
use App\Models\Team;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\LicenceBatches;
use App\Services\Licensing\SeatLedger;
use App\Services\Licensing\SeatLimitReached;
use App\Services\Ops\OpsCheck;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** The R-53 contract on POST /api/finance/platform-subscription-events (`kind: licence_batch`): signed, once per event, never active before `paid`. */
class LicenceBatchEventsTest extends LicenceTestCase
{
    private function capacity(Team $team): int
    {
        return app(SeatLedger::class)->summary($team->fresh())['capacity'];
    }

    public function test_a_verified_paid_event_activates_the_batch_and_adds_capacity_only_then(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 3);
        $this->assertSame(1, $this->capacity($team));

        $event = $this->licenceEvent($batch);
        $this->finance($event)->assertOk()->assertExactJson(['status' => 'applied']);

        $batch = $batch->fresh();
        $this->assertSame(['active', $event['paystack_reference']], [$batch->status, $batch->payment_ref]);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $batch->period_end->timestamp, 5);
        $this->assertSame(4, $this->capacity($team));

        $row = PlatformPaymentConfirmation::where('event_id', $event['event_id'])->sole();
        $this->assertSame(['paid', 'licences', 29700, 'ZAR', 'applied', $team->id], [$row->event, $row->platform_key, $row->amount_minor, $row->currency, $row->outcome, $row->team_id]);
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'batch_activated', 'subject' => $batch->uuid]);
    }

    public function test_the_invitations_that_fit_are_exactly_the_paid_licences(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        $invite = fn (string $email) => app(InviteTeamMember::class)->invite($team->owner, $team, $email, 'member');

        $invite('a@example.test');
        $invite('b@example.test');
        $this->expectException(SeatLimitReached::class);
        $invite('c@example.test');
    }

    public function test_a_wrong_or_missing_signature_changes_nothing_and_says_nothing(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 2);
        $event = $this->licenceEvent($batch);
        $raw = (string) json_encode($event);

        $this->finance($raw, 'not-the-secret')->assertStatus(401)->assertExactJson(['error' => 'invalid_request']);
        $this->finance($raw, null, time() - 4000)->assertStatus(401);
        $this->call('POST', '/api/finance/platform-subscription-events', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw)->assertStatus(401);

        config(['platform_subscriptions.confirmation_secret' => null]);
        $this->finance($raw, '')->assertStatus(401);

        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertSame(0, PlatformPaymentConfirmation::count());
    }

    public function test_a_repeated_delivery_is_applied_once(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 2);
        $event = $this->licenceEvent($batch);

        $this->finance($event)->assertExactJson(['status' => 'applied']);
        $this->finance($event)->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame(1, PlatformPaymentConfirmation::where('event_id', $event['event_id'])->count());
        $this->assertSame(1, TeamLicenceEvent::where('type', 'batch_activated')->count());
        $this->assertSame(3, $this->capacity($team));
    }

    public function test_a_payment_that_does_not_match_what_was_priced_is_refused_and_stores_nothing(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 2);

        foreach ([['amount_minor' => 100], ['quantity' => 9], ['interval' => 'year'], ['currency' => 'USD']] as $wrong) {
            $this->finance($this->licenceEvent($batch, 'paid', $wrong))->assertStatus(422)->assertExactJson(['error' => 'unprocessable']);
        }

        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertSame(0, PlatformPaymentConfirmation::count());
        $this->assertSame(1, $this->capacity($team));
    }

    public function test_a_confirmation_for_another_companys_batch_or_an_unknown_one_is_refused(): void
    {
        $a = $this->company('A Co');
        $b = $this->company('B Co');
        $batchOfA = $this->pending($a, 2);

        $event = $this->licenceEvent($batchOfA, 'paid', ['team_uuid' => (string) $b->uuid]);
        $this->finance($event)->assertStatus(422);
        $this->finance($this->licenceEvent($batchOfA, 'paid', ['batch_uuid' => (string) Str::uuid()]))->assertStatus(422);
        $this->finance($this->licenceEvent($batchOfA, 'paid', ['team_uuid' => (string) Str::uuid()]))->assertStatus(422);

        $this->assertSame('pending', $batchOfA->fresh()->status);
        $this->assertSame([1, 1], [$this->capacity($a), $this->capacity($b)]);
    }

    public function test_malformed_events_are_refused_as_bad_requests(): void
    {
        $batch = $this->pending($this->company(), 1);
        $good = $this->licenceEvent($batch);

        foreach ([
            ['kind' => 'something_else'] + $good,
            ['type' => 'exploded'] + $good,
            array_diff_key($good, ['batch_uuid' => 1]),
            ['quantity' => 0] + $good,
            ['quantity' => '2'] + $good,
            ['interval' => 'week'] + $good,
            ['amount_minor' => 0] + $good,
            array_diff_key($good, ['amount_minor' => 1, 'currency' => 1]),
            ['period_end' => now()->subDay()->toIso8601String()] + $good,
            ['period_start' => now()->addYear()->toIso8601String()] + $good,
            ['event_id' => 'bad id with spaces'] + $good,
            ['paystack_reference' => ''] + $good,
        ] as $bad) {
            $this->finance($bad)->assertStatus(400);
        }

        $this->finance('not json')->assertStatus(400);
        $this->assertSame('pending', $batch->fresh()->status);
    }

    public function test_a_licence_is_never_active_without_a_verified_paid_event(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 2);

        // Every other kind of event, in every order, cannot make a pending batch active.
        foreach (['renewed', 'payment_failed', 'cancelled', 'refunded'] as $type) {
            $b = $this->pending($team, 1);
            $this->finance($this->licenceEvent($b, $type))->assertOk();
            $this->assertNotSame('active', $b->fresh()->status, "$type must not activate");
        }

        $this->assertSame(1, $this->capacity($team));
        $this->assertSame('pending', $batch->fresh()->status);
        $this->assertSame(0, LicenceBatch::where('status', 'active')->count());
    }

    public function test_a_second_paid_event_for_an_active_batch_and_a_late_one_for_an_ended_batch_are_ignored(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $this->finance($this->licenceEvent($batch->fresh(), 'paid'))->assertExactJson(['status' => 'ignored']);
        $this->assertSame(3, $this->capacity($team));

        $this->finance($this->licenceEvent($batch, 'cancelled'))->assertExactJson(['status' => 'applied']);
        $this->travel(2)->months();
        $this->artisan('licence:sweep')->assertSuccessful();
        $this->assertSame('ended', $batch->fresh()->status);

        $this->finance($this->licenceEvent($batch->fresh(), 'renewed'))->assertExactJson(['status' => 'ignored']);
        $this->finance($this->licenceEvent($batch->fresh(), 'paid'))->assertExactJson(['status' => 'ignored']);
        $this->assertSame('ended', $batch->fresh()->status, 'an ended batch is not revived by a late event');
        $this->assertSame(1, $this->capacity($team));
    }

    public function test_a_renewal_extends_the_period_and_an_older_one_changes_nothing(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $end = $batch->period_end;

        $next = now()->addMonths(2);
        $this->finance($this->licenceEvent($batch, 'renewed', ['period_start' => $end->toIso8601String(), 'period_end' => $next->toIso8601String()]))->assertExactJson(['status' => 'applied']);
        $this->assertEqualsWithDelta($next->timestamp, $batch->fresh()->period_end->timestamp, 5);

        $this->finance($this->licenceEvent($batch, 'renewed', ['period_end' => $end->copy()->addDay()->toIso8601String()]))->assertExactJson(['status' => 'ignored']);
        $this->assertEqualsWithDelta($next->timestamp, $batch->fresh()->period_end->timestamp, 5);
    }

    public function test_a_late_failure_cannot_undo_a_renewal_that_happened_after_it(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $failedAt = now()->addDay();
        $renewedAt = now()->addDays(2);

        $this->finance($this->licenceEvent($batch, 'renewed', ['occurred_at' => $renewedAt->toIso8601String(), 'period_end' => now()->addMonths(2)->toIso8601String()]))->assertExactJson(['status' => 'applied']);
        $this->finance($this->licenceEvent($batch, 'payment_failed', ['occurred_at' => $failedAt->toIso8601String()]))->assertExactJson(['status' => 'ignored']);

        $this->assertSame('active', $batch->fresh()->status);
        $this->assertSame(3, $this->capacity($team));
        $this->assertSame('ignored', PlatformPaymentConfirmation::where('event', 'payment_failed')->value('outcome'), 'the late event is kept as evidence');
    }

    public function test_a_failed_payment_starts_fourteen_days_of_grace_then_the_extra_people_lapse_and_nothing_is_deleted(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $admin = $this->join($team, 'admin');
        $one = $this->join($team);
        $two = $this->join($team);   // owner + admin + 2 members = 4 people on 3 licences? the admin is one of the paid two: 4 on capacity 3
        $this->assertSame(3, $this->capacity($team));
        $team = $team->fresh();

        $this->finance($this->licenceEvent($batch, 'payment_failed'))->assertExactJson(['status' => 'applied']);
        $this->assertSame('grace', $batch->fresh()->status);
        $this->assertEqualsWithDelta(now()->addMonth()->addDays(14)->timestamp, $batch->fresh()->grace_until->timestamp, 5, 'fourteen days after the period end, which is when it was due');
        $this->assertSame(3, $this->capacity($team), 'the licences keep counting through the grace');

        $this->travel(2)->months();
        $this->artisan('licence:sweep')->assertSuccessful();

        $this->assertSame('ended', $batch->fresh()->status);
        $this->assertSame(1, $this->capacity($team));
        $access = app(LicenceAssignments::class);
        $this->assertTrue($access->isLicensed($team, $team->owner));
        $this->assertTrue($access->isLicensed($team, $admin), 'administrators never lapse');
        $this->assertFalse($access->isLicensed($team, $two), 'the most recently added lapse first');
        $this->assertFalse($access->isLicensed($team, $one));
        $this->assertSame(3, $team->users()->count(), 'nobody was removed from the company');
        $this->assertNotNull(User::find($one->id), 'and no account was deleted');
    }

    public function test_cancelling_ends_the_batch_at_the_period_end_then_gives_the_leftovers_fourteen_days(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $one = $this->join($team);
        $two = $this->join($team);
        $this->finance($this->licenceEvent($batch, 'cancelled'))->assertExactJson(['status' => 'applied']);

        $this->assertSame('active', $batch->fresh()->status, 'a cancelled subscription keeps what was paid for');
        $this->assertNotNull($batch->fresh()->cancelled_at);
        $this->assertSame(3, $this->capacity($team));

        $this->travel(32)->days();   // past the paid month
        $this->artisan('licence:sweep')->assertSuccessful();
        $licence = $team->licence()->first();

        $this->assertSame('ended', $batch->fresh()->status);
        $this->assertNotNull($licence->grace_until, 'the leftovers get 14 days (D5), they do not lapse on the day');
        $access = app(LicenceAssignments::class);
        $this->assertTrue($access->isLicensed($team->fresh(), $one) && $access->isLicensed($team->fresh(), $two));

        $this->travel(15)->days();
        $this->artisan('licence:sweep')->assertSuccessful();

        $this->assertFalse($access->isLicensed($team->fresh(), $two));
        $this->assertFalse($access->isLicensed($team->fresh(), $one));
        $this->assertSame(2, $team->users()->count());
        $this->assertNull($team->licence()->first()->grace_until);
    }

    public function test_a_refund_gives_fourteen_days_then_lapses_the_people_it_covered(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 1);
        $one = $this->join($team);

        $this->finance($this->licenceEvent($batch, 'refunded', ['amount_minor' => $batch->total_minor, 'currency' => 'ZAR']))->assertExactJson(['status' => 'applied']);
        $this->assertSame('refunded', $batch->fresh()->status);
        $this->assertSame(2, $this->capacity($team), 'not lapsed on the day of the refund');

        $this->travel(13)->days();
        $this->artisan('licence:sweep')->assertSuccessful();
        $this->assertTrue(app(LicenceAssignments::class)->isLicensed($team->fresh(), $one));

        $this->travel(2)->days();
        $this->artisan('licence:sweep')->assertSuccessful();
        $this->assertFalse(app(LicenceAssignments::class)->isLicensed($team->fresh(), $one));
        $this->assertSame(1, $this->capacity($team));
    }

    public function test_a_refund_before_activation_closes_the_batch_without_ever_granting_anything(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 2);

        $this->finance($this->licenceEvent($batch, 'refunded', ['amount_minor' => 0, 'currency' => 'ZAR']))->assertExactJson(['status' => 'applied']);

        $this->assertSame('refunded', $batch->fresh()->status);
        $this->assertSame(1, $this->capacity($team));
    }

    public function test_a_renewal_at_a_smaller_quantity_is_a_reduction_that_starts_the_leftover_grace_without_removing_anyone(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 3);
        $a = $this->join($team);
        $b = $this->join($team);
        $c = $this->join($team);
        app(LicenceBatches::class)->reduceAtPeriodEnd($batch, $team->owner, 1);

        $this->finance($this->licenceEvent($batch, 'renewed', ['quantity' => 1, 'amount_minor' => 9900, 'period_end' => now()->addMonths(2)->toIso8601String()]))->assertExactJson(['status' => 'applied']);

        $fresh = $batch->fresh();
        $this->assertSame([1, null, 9900], [$fresh->quantity, $fresh->quantity_next, $fresh->total_minor]);
        $this->assertSame(2, $this->capacity($team));
        $this->assertNotNull($team->licence()->first()->grace_until);
        foreach ([$a, $b, $c] as $m) {
            $this->assertTrue(app(LicenceAssignments::class)->isLicensed($team->fresh(), $m), 'nobody is removed on the day');
        }

        $this->travel(15)->days();
        $this->artisan('licence:sweep')->assertSuccessful();
        $licensed = collect([$a, $b, $c])->filter(fn ($m) => app(LicenceAssignments::class)->isLicensed($team->fresh(), $m));
        $this->assertSame([$a->id], $licensed->pluck('id')->all(), 'one licence stays: the longest-standing member keeps it');
    }

    public function test_a_renewal_cannot_add_licences(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);

        $this->finance($this->licenceEvent($batch, 'renewed', ['quantity' => 5, 'amount_minor' => 49500]))->assertStatus(422);
        $this->assertSame(2, $batch->fresh()->quantity);
    }

    public function test_the_existing_platform_events_still_work_alongside(): void
    {
        $team = $this->company();
        $this->plan('hr');
        $event = ['event_id' => 'evt-x1', 'event' => 'paid', 'company_uuid' => (string) $team->uuid, 'platform' => 'hr', 'plan' => 'team', 'amount_minor' => 24900, 'currency' => 'ZAR',
            'reference' => 'SUB-1', 'period_end' => now()->addMonth()->toIso8601String(), 'occurred_at' => now()->toIso8601String()];

        $this->finance($event)->assertOk()->assertExactJson(['status' => 'applied']);
        $this->finance($event + ['kind' => 'platform'])->assertStatus(400);
    }

    public function test_a_refused_licence_event_is_logged_for_the_operator_not_explained_to_the_caller(): void
    {
        Log::spy();
        $team = $this->company();
        $batch = $this->pending($team, 2);

        $this->finance($this->licenceEvent($batch, 'paid', ['amount_minor' => 5]))->assertStatus(422)->assertExactJson(['error' => 'unprocessable']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => $m === 'A signed licence event was refused.')->once();
    }

    public function test_a_confirmed_licence_payment_shows_on_the_administrators_payments_page_without_a_platform_link(): void
    {
        $team = $this->company('Ledger Licences Co');
        $this->paid($team, 2);

        $this->actingAs($this->administrator())->get('/admin/platforms/payments')->assertOk()->assertSee('Ledger Licences Co')->assertSee('ZAR 198.00')->assertDontSee('platform=licences', false);
    }

    public function test_ops_check_reports_a_batch_that_counts_without_a_recorded_activation(): void
    {
        $team = $this->company();
        $this->paid($team, 2);
        $this->assertSame(0, app(OpsCheck::class)->run()['licence_batch_active_without_activation_record']);

        // A row written around the services: active, with no `paid` behind it.
        LicenceBatch::create(['team_id' => $team->id, 'quantity' => 4, 'interval' => 'month', 'unit_price_minor' => 9900, 'total_minor' => 39600, 'currency' => 'ZAR'])
            ->forceFill(['status' => 'active', 'period_end' => now()->addMonth()])->save();

        $this->assertSame(1, app(OpsCheck::class)->run()['licence_batch_active_without_activation_record']);
    }

    public function test_the_sweep_is_idempotent_and_never_touches_a_healthy_company(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 2);
        $one = $this->join($team);
        $before = [$batch->fresh()->status, TeamLicenceEvent::count()];

        $this->artisan('licence:sweep')->expectsOutputToContain('moved 0 batch(es) to grace, ended 0')->assertSuccessful();
        $this->artisan('licence:sweep')->assertSuccessful();

        $this->assertSame($before, [$batch->fresh()->status, TeamLicenceEvent::count()]);
        $this->assertTrue(app(LicenceAssignments::class)->isLicensed($team->fresh(), $one));
    }

    public function test_a_batch_whose_renewal_never_comes_waits_in_grace_instead_of_cutting_people_off(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 1);
        $one = $this->join($team);

        $this->travel(31)->days();   // just past the paid month, no event from Finance
        $this->artisan('licence:sweep')->assertSuccessful();

        $this->assertSame('grace', $batch->fresh()->status);
        $this->assertEqualsWithDelta($batch->fresh()->period_end->copy()->addDays(14)->timestamp, $batch->fresh()->grace_until->timestamp, 5);
        $this->assertTrue(app(LicenceAssignments::class)->isLicensed($team->fresh(), $one));

        // Finance catches up with a renewal: the batch is active again and the grace is gone.
        $this->finance($this->licenceEvent($batch, 'renewed', ['period_end' => now()->addMonth()->toIso8601String()]))->assertExactJson(['status' => 'applied']);
        $this->assertSame(['active', null], [$batch->fresh()->status, $batch->fresh()->grace_until]);
    }

    public function test_signing_matches_the_contract_scheme(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 1);
        $raw = (string) json_encode($this->licenceEvent($batch));
        $ts = time();

        // HMAC-SHA256 of "{timestamp}.{raw body}" with the shared secret, header X-InfoDot-Signature = sha256=<hex>.
        $this->assertSame('sha256='.hash_hmac('sha256', $ts.'.'.$raw, $this->secret), Signature::sign($this->secret, $ts, $raw));
        $this->finance($raw, null, $ts)->assertOk();
    }
}
