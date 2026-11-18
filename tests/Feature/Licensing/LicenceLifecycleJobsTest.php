<?php

namespace Tests\Feature\Licensing;

use App\Mail\LicenceNotice;
use App\Models\LicenceAssignment;
use App\Models\LicenceBatch;
use App\Models\LicenceNoticeSent;
use App\Models\Team;
use App\Models\TeamLicence;
use App\Services\Licensing\LicenceBatches;
use App\Services\Licensing\LicenceLifecycle;
use App\Services\Licensing\LicenceReminders;
use App\Services\Licensing\SeatLedger;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * R-53 phase 4: the scheduled jobs. Every job here is run twice on purpose: the second run must change and send nothing.
 */
class LicenceLifecycleJobsTest extends LicenceTestCase
{
    private function remind(): array
    {
        return app(LicenceReminders::class)->run();
    }

    private function sentTo(string $email): int
    {
        return Mail::sent(LicenceNotice::class, fn (LicenceNotice $m) => $m->hasTo($email))->count();
    }

    // ---------------------------------------------------------------- renewal reminders

    public function test_a_renewal_reminder_goes_to_the_owner_once_and_only_inside_the_window(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $batch = $this->paid($team, 2, 'month', ['period_end' => now()->addDays(10)->toIso8601String()]);

        $this->assertSame(0, $this->remind()['renewal'], 'Ten days away is outside the 3 day window.');
        $this->travel(7.5)->days();
        $this->assertSame(1, $this->remind()['renewal']);
        $this->assertSame(0, $this->remind()['renewal'], 'A second run sends nothing.');
        $this->assertSame(1, $this->sentTo($team->owner->email));
        $this->assertSame(0, $this->sentTo($admin->email), 'Renewal reminders are about billing: the owner only.');
        $this->assertSame(1, LicenceNoticeSent::where('team_id', $team->id)->where('kind', 'renewal')->count());
        $this->assertNotNull($batch->fresh());
    }

    public function test_annual_purchases_are_reminded_at_30_and_7_days_and_a_cancelled_one_is_not_reminded(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 1, 'year', ['period_end' => now()->addDays(60)->toIso8601String()]);
        $this->assertSame(0, $this->remind()['renewal']);
        $this->travel(31)->days();
        $this->assertSame(1, $this->remind()['renewal']);
        $this->travel(23)->days();
        $this->assertSame(1, $this->remind()['renewal']);
        $this->assertSame(0, $this->remind()['renewal']);
        $this->assertSame(2, $this->sentTo($team->owner->email));

        $other = $this->company('Second Co');
        $b2 = $this->paid($other, 1, 'month', ['period_end' => now()->addDays(2)->toIso8601String()]);
        $b2->forceFill(['cancelled_at' => now()])->save();
        $this->assertSame(0, $this->remind()['renewal']);
        $this->assertNotNull($batch);
    }

    // ---------------------------------------------------------------- failed payment: banner, email at 14, 7 and 1 days

    public function test_a_failed_payment_emails_owner_and_admins_at_14_7_and_1_days_and_never_twice(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $member = $this->memberOf($team, 'member');
        $batch = $this->paid($team, 2, 'month', ['period_end' => now()->addDays(1)->toIso8601String()]);
        $this->travel(2)->days();
        $this->finance($this->licenceEvent($batch->fresh(), 'payment_failed'))->assertOk();
        $this->assertSame('grace', $batch->fresh()->status);

        $this->assertSame(1, $this->remind()['payment']);
        $this->assertSame(0, $this->remind()['payment']);
        $this->assertSame(1, $this->sentTo($team->owner->email));
        $this->assertSame(1, $this->sentTo($admin->email));
        $this->assertSame(0, $this->sentTo($member->email));

        $this->travel(7)->days();
        $this->assertSame(1, $this->remind()['payment']);
        $this->assertSame(0, $this->remind()['payment']);
        $this->travel(6.5)->days();
        $this->assertSame(1, $this->remind()['payment']);
        $this->assertSame(0, $this->remind()['payment']);
        $this->assertSame(3, $this->sentTo($team->owner->email));
        $this->travel(10)->days();
        $this->assertSame(0, $this->remind()['payment'], 'After the grace nothing more is sent.');
    }

    public function test_a_late_first_run_sends_only_the_current_reminder_not_a_backlog(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 1, 'month', ['period_end' => now()->addDay()->toIso8601String()]);
        $this->travel(2)->days();
        $this->finance($this->licenceEvent($batch->fresh(), 'payment_failed'))->assertOk();
        $this->travel(12.5)->days();
        $this->assertSame(1, $this->remind()['payment']);
        $this->assertSame(1, $this->sentTo($team->owner->email));
    }

    public function test_the_banner_shows_to_the_owner_and_admins_only_during_a_grace(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $member = $this->memberOf($team, 'member');
        $other = $this->company('Elsewhere Ltd');
        $batch = $this->paid($team, 2);
        $this->travel(32)->days();
        $this->finance($this->licenceEvent($batch->fresh(), 'payment_failed'))->assertOk();

        $this->actingAs($team->owner)->get('/people')->assertOk()->assertSee('data-licence-banner', false)->assertSee('A licence payment failed.')->assertSee('keep working until');
        $this->actingAs($admin)->get('/people')->assertSee('A licence payment failed.')->assertSee('Tell the owner.');
        $this->actingAs($member)->get('/people')->assertOk()->assertDontSee('data-licence-banner', false);
        $this->actingAs($other->owner)->get('/people')->assertOk()->assertDontSee('data-licence-banner', false);
    }

    // ---------------------------------------------------------------- grace expiry (D5), reduction, reactivation

    public function test_after_the_grace_the_newest_members_lapse_owner_and_admins_never_and_nothing_is_deleted(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $old = $this->join($team);
        $mid = $this->join($team);
        $new = $this->join($team);
        $batch = $this->paid($team, 2, 'month', ['period_end' => now()->addDays(3)->toIso8601String()]);
        $this->travel(4)->days();

        // The paid month is over and the batch was never cancelled: it waits in grace for Finance's word, still counting.
        app(LicenceLifecycle::class)->sweep();
        $this->assertSame('grace', $batch->fresh()->status);
        $this->assertSame(0, LicenceAssignment::where('team_id', $team->id)->where('state', 'lapsed')->count());

        $this->travel(15)->days();
        $first = app(LicenceLifecycle::class)->sweep();
        $second = app(LicenceLifecycle::class)->sweep();
        $this->assertSame('ended', $batch->fresh()->status);
        $this->assertSame(0, $second['to_grace'] + $second['ended'] + $second['abandoned']);

        // Capacity is 1 (the free licence): people are owner, admin, old, mid, new. Owner and admin never lapse; newest first.
        $states = LicenceAssignment::where('team_id', $team->id)->pluck('state', 'user_id');
        $this->assertSame('lapsed', $states[$new->id]);
        $this->assertSame('lapsed', $states[$mid->id]);
        $this->assertSame('lapsed', $states[$old->id]);
        $this->assertArrayNotHasKey($admin->id, $states->all(), 'An administrator is never lapsed.');
        foreach ([$old, $mid, $new, $admin] as $u) {
            $this->assertTrue($team->fresh()->hasUser($u), 'Nobody is removed from the company.');
        }
        $this->assertSame($first['rebalanced'] >= 1, true);

        // Buying again gives the licences back at once, longest-standing first.
        $this->paid($team, 2);
        app(LicenceLifecycle::class)->sweep();
        $after = LicenceAssignment::where('team_id', $team->id)->pluck('state', 'user_id');
        // Capacity 3: the owner and the administrator hold two, so one place comes back, to the longest-standing member.
        $this->assertSame('active', $after[$old->id]);
        $this->assertSame('lapsed', $after[$mid->id]);
        $this->assertSame('lapsed', $after[$new->id]);
    }

    public function test_a_renewal_at_a_smaller_quantity_applies_the_reduction_with_a_14_day_grace_for_the_leftovers(): void
    {
        $team = $this->company();
        $a = $this->join($team);
        $b = $this->join($team);
        $c = $this->join($team);
        $batch = $this->paid($team, 3);
        $this->actingAs($team->owner);
        app(LicenceBatches::class)->reduceAtPeriodEnd($batch, $team->owner, 1);
        $this->assertSame(1, $batch->fresh()->quantity_next);
        $this->assertSame(4, app(SeatLedger::class)->summary($team->fresh())['capacity']);

        $this->travel(31)->days();
        $this->finance($this->licenceEvent($batch->fresh(), 'renewed', ['quantity' => 1]))->assertOk();
        $this->assertSame(2, app(SeatLedger::class)->summary($team->fresh())['capacity']);
        $this->assertNull($batch->fresh()->quantity_next);
        $this->assertNotNull(TeamLicence::where('team_id', $team->id)->value('grace_until'));
        $this->assertSame(0, LicenceAssignment::where('team_id', $team->id)->where('state', 'lapsed')->count(), 'Nobody is removed on the day.');

        $this->travel(15)->days();
        app(LicenceLifecycle::class)->sweep();
        app(LicenceLifecycle::class)->sweep();
        $states = LicenceAssignment::where('team_id', $team->id)->pluck('state', 'user_id');
        $this->assertSame('lapsed', $states[$c->id]);
        $this->assertSame('lapsed', $states[$b->id]);
        $this->assertSame('active', $states[$a->id]);
    }

    public function test_a_shortfall_inside_its_grace_is_emailed_naming_who_would_lose_access(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 3);
        $admin = $this->memberOf($team, 'admin');
        $kept = $this->join($team);
        $newest = $this->join($team);
        $this->finance($this->licenceEvent($batch, 'cancelled'))->assertOk();
        $this->travel(32)->days();
        app(LicenceLifecycle::class)->sweep();
        $this->assertNotNull(TeamLicence::where('team_id', $team->id)->value('grace_until'));

        $this->assertSame(1, $this->remind()['shortfall']);
        $this->assertSame(0, $this->remind()['shortfall']);
        Mail::assertSent(LicenceNotice::class, fn (LicenceNotice $m) => $m->hasTo($team->owner->email) && collect($m->lines)->contains(fn ($l) => str_contains($l, $newest->name)) && ! collect($m->lines)->contains(fn ($l) => str_contains($l, $admin->name)));
        $this->assertSame(1, $this->sentTo($admin->email));
        $this->assertNotNull($kept);
    }

    // ---------------------------------------------------------------- grandfather reminders

    public function test_grandfather_reminders_go_out_at_day_0_30_60_80_and_89_and_stop_when_the_period_ends(): void
    {
        $team = $this->company();
        $this->memberOf($team, 'admin');
        $this->join($team);
        $this->join($team);
        Artisan::call('licence:grandfather', ['--apply' => true, '--team' => [$team->id]]);
        $until = TeamLicence::where('team_id', $team->id)->value('grandfathered_until');
        $this->assertNotNull($until);

        $this->assertSame(1, $this->remind()['grandfather']);
        $this->assertSame(0, $this->remind()['grandfather']);
        Mail::assertSent(LicenceNotice::class, fn (LicenceNotice $m) => $m->hasTo($team->owner->email) && collect($m->lines)->contains(fn ($l) => str_contains($l, 'extra people') && str_contains($l, 'a month')));

        $count = 1;
        foreach ([31, 30, 20, 9.5] as $skip) {   // day 31, 61, 81, 90.5
            $this->travel($skip)->days();
            $sent = $this->remind()['grandfather'];
            $this->assertSame($skip === 9.5 ? 0 : 1, $sent, "after {$skip} more days");
            $this->assertSame(0, $this->remind()['grandfather']);
            $count += $sent;
        }
        $this->assertSame(4, $count);
        $this->travel(1)->days();
        $this->assertSame(0, $this->remind()['grandfather'], 'Nothing after the period has ended.');
    }

    public function test_the_89_day_reminder_is_sent_when_the_job_runs_on_day_89(): void
    {
        $team = $this->company();
        $this->join($team);
        Artisan::call('licence:grandfather', ['--apply' => true, '--team' => [$team->id]]);
        $this->remind();
        $this->travel(89.2)->days();
        $this->assertSame(1, $this->remind()['grandfather']);
        $this->assertSame(0, $this->remind()['grandfather']);
        $this->assertSame(2, LicenceNoticeSent::where('team_id', $team->id)->where('kind', 'grandfather')->count());
    }

    public function test_reminders_are_never_sent_across_companies(): void
    {
        $a = $this->company('Alpha');
        $b = $this->company('Beta');
        $batch = $this->paid($a, 1, 'month', ['period_end' => now()->addDays(2)->toIso8601String()]);
        $this->assertSame(1, $this->remind()['renewal']);
        $this->assertSame(1, $this->sentTo($a->owner->email));
        $this->assertSame(0, $this->sentTo($b->owner->email));
        $this->assertNotNull($batch);
    }

    // ---------------------------------------------------------------- pending cleanup, sweep twice

    public function test_unpaid_purchases_are_closed_after_their_window_and_a_second_sweep_does_nothing(): void
    {
        $team = $this->company();
        $pending = $this->pending($team, 2);
        $paid = $this->paid($team, 1);
        $this->travel(8)->days();
        $one = app(LicenceLifecycle::class)->sweep();
        $two = app(LicenceLifecycle::class)->sweep();
        $this->assertSame(1, $one['abandoned']);
        $this->assertSame(0, $two['abandoned']);
        $this->assertSame('ended', $pending->fresh()->status);
        $this->assertSame('active', $paid->fresh()->status);
        $this->assertSame(1, app(SeatLedger::class)->summary($team->fresh())['purchased'] >= 0 ? 1 : 0);
    }

    // ---------------------------------------------------------------- reconcile read for Finance

    private function signedGet(string $url, ?string $key = null, ?int $ts = null)
    {
        $ts ??= time();

        return $this->call('GET', $url, [], [], [], ['HTTP_X_INFODOT_TIMESTAMP' => (string) $ts, 'HTTP_X_INFODOT_SIGNATURE' => Signature::sign($key ?? $this->secret, $ts, '')]);
    }

    public function test_the_reconcile_read_answers_the_shape_finance_expects_for_one_company(): void
    {
        $a = $this->company('Alpha');
        $b = $this->company('Beta');
        $batchA = $this->paid($a, 2, 'year');
        $this->paid($b, 5);
        $this->pending($a, 1);

        $json = $this->signedGet('/api/licences/reconcile/'.$a->uuid)->assertOk()->json();
        $this->assertSame($a->uuid, $json['team_uuid']);
        $this->assertCount(1, $json['batches'], 'Only this company\'s paid batches; pending ones are not listed.');
        $this->assertSame(['batch_uuid' => $batchA->uuid, 'status' => 'active', 'quantity' => 2, 'interval' => 'year', 'period_end' => $batchA->fresh()->period_end->utc()->toIso8601String(), 'counts_now' => true], $json['batches'][0]);
        $this->assertStringNotContainsString('@', json_encode($json), 'No email or person is carried.');
        $this->assertStringNotContainsString('total', json_encode($json), 'No price is carried.');
    }

    public function test_the_reconcile_read_is_signed_and_scoped(): void
    {
        $a = $this->company();
        $this->paid($a, 1);
        $this->getJson('/api/licences/reconcile/'.$a->uuid)->assertStatus(401);
        $this->signedGet('/api/licences/reconcile/'.$a->uuid, 'wrong-secret')->assertStatus(401);
        $this->signedGet('/api/licences/reconcile/'.$a->uuid, null, time() - 3600)->assertStatus(401);
        $this->signedGet('/api/licences/reconcile/'.(string) Str::uuid())->assertStatus(404);
        $this->signedGet('/api/licences/reconcile/not-a-uuid')->assertStatus(404);
        config(['platform_subscriptions.confirmation_secret' => null]);
        $this->signedGet('/api/licences/reconcile/'.$a->uuid, 'anything')->assertStatus(401);
    }

    // ---------------------------------------------------------------- ops report

    public function test_the_report_is_read_only_and_lists_company_states(): void
    {
        $team = $this->company('Report Co');
        $this->join($team);
        $batch = $this->paid($team, 1);
        $before = [LicenceBatch::count(), LicenceAssignment::count(), TeamLicence::count()];

        Artisan::call('licence:report', ['--json' => true]);
        $rows = json_decode(Artisan::output(), true);
        $row = collect($rows)->firstWhere('uuid', $team->uuid);
        $this->assertSame(['paid' => 1, 'capacity' => 2, 'people' => 2, 'batches_active' => 1], array_intersect_key($row, array_flip(['paid', 'capacity', 'people', 'batches_active'])));
        $this->assertFalse($row['needs_attention']);

        Artisan::call('licence:report', ['--problems' => true, '--json' => true]);
        $this->assertSame([], json_decode(Artisan::output(), true));
        $this->assertSame($before, [LicenceBatch::count(), LicenceAssignment::count(), TeamLicence::count()]);
        $this->assertNotNull($batch);
        $this->assertInstanceOf(Team::class, $team);
    }

    public function test_the_reminder_email_renders_with_the_company_the_lines_and_one_link(): void
    {
        $team = $this->company('Northwind Freight');
        $html = (new LicenceNotice('A licence payment failed', ['The licences keep working until 1 January 2027.'], $team, $team->owner))->render();
        $this->assertStringContainsString('A licence payment failed', $html);
        $this->assertStringContainsString('The licences keep working until 1 January 2027.', $html);
        $this->assertStringContainsString('Nobody is removed from Northwind Freight', $html);
        $this->assertStringContainsString('You can fix this yourself', $html);
        $this->assertStringContainsString('/teams/'.$team->id.'#seats', $html);
    }
}
