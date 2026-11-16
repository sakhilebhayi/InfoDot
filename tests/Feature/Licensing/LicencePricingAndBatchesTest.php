<?php

namespace Tests\Feature\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Services\Licensing\LicenceBatches;
use App\Services\Licensing\LicencePricing;
use App\Services\Licensing\LicenceRefused;
use App\Services\Licensing\SeatLedger;
use Illuminate\Auth\Access\AuthorizationException;

/** R-53 D1 to D4, D12: priced on the server from configuration, bought in batches, never counted until paid. */
class LicencePricingAndBatchesTest extends LicenceTestCase
{
    public function test_marginal_volume_bands_match_the_approved_model(): void
    {
        $team = $this->company();
        $q = fn (int $n, string $interval = 'month') => app(LicencePricing::class)->quote($team, $n, $interval)['total_minor'];

        $this->assertSame(9900, $q(1));
        $this->assertSame(49500, $q(5), 'users 1 to 5 at full price');
        $this->assertSame(49500 + 8910, $q(6), 'the sixth extra licence is at 90%');
        $this->assertSame(214830, $q(24), 'a team of 25 pays R2,148.30 a month');
        $this->assertSame(808830, $q(99), 'a team of 100 pays R8,088.30 a month');
        $this->assertSame(2148300, $q(24, 'year'), 'the annual price uses the same bands on the annual unit price');
    }

    public function test_a_second_purchase_continues_the_bands_so_splitting_never_changes_the_total(): void
    {
        $team = $this->company();
        $this->paid($team, 4);   // 39,600

        $second = app(LicencePricing::class)->quote($team, 3, 'month');

        $this->assertSame(27720, $second['total_minor'], 'licences 5 to 7 are priced as 5th at full and 6th, 7th at 90%');
        $this->assertSame(4, $second['snapshot']['already_held']);
        $this->assertSame(['base_unit_minor' => 9900, 'interval' => 'month', 'currency' => 'ZAR', 'vat' => 'excluded'], array_intersect_key($second['snapshot'], array_flip(['base_unit_minor', 'interval', 'currency', 'vat'])));
    }

    public function test_nothing_can_be_priced_or_bought_without_configured_prices(): void
    {
        $team = $this->company();
        config(['licensing.price.amount_minor_units' => null, 'licensing.price.currency' => null]);

        $this->expectException(LicenceRefused::class);
        $this->expectExceptionMessage('no price has been set');
        $this->pending($team);
    }

    public function test_the_annual_price_must_be_configured_for_a_yearly_purchase(): void
    {
        $team = $this->company();
        config(['licensing.price.annual_amount_minor_units' => null]);

        $this->assertSame(1, $this->pending($team, 1, 'month')->quantity);
        $this->expectException(LicenceRefused::class);
        $this->pending($team, 1, 'year');
    }

    public function test_bad_quantities_intervals_and_the_online_limit_are_refused(): void
    {
        $team = $this->company();
        $pricing = app(LicencePricing::class);

        foreach ([[0, 'month'], [-3, 'month'], [1, 'week']] as [$qty, $interval]) {
            try {
                $pricing->quote($team, $qty, $interval);
                $this->fail("quote({$qty}, {$interval}) should be refused");
            } catch (LicenceRefused) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectExceptionMessage('custom agreement');
        $pricing->quote($team, 100, 'month');   // 100 extra + the owner is 101 people: above the online limit
    }

    public function test_a_purchase_starts_pending_prices_itself_and_counts_for_nothing(): void
    {
        $team = $this->company();
        $before = app(SeatLedger::class)->summary($team);

        $batch = $this->pending($team, 3);

        $this->assertSame(['pending', 3, 'month', 29700, 'ZAR'], [$batch->status, $batch->quantity, $batch->interval, $batch->total_minor, $batch->currency]);
        $this->assertSame(9900, $batch->unit_price_minor);
        $this->assertNotEmpty($batch->uuid);
        $this->assertSame(9900, $batch->price_snapshot['base_unit_minor']);
        $this->assertSame($before['capacity'], app(SeatLedger::class)->summary($team)['capacity'], 'a pending batch is not capacity');
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'batch_created', 'subject' => $batch->uuid]);
    }

    public function test_only_the_owner_can_start_a_purchase_and_only_for_a_company(): void
    {
        $team = $this->company();
        $admin = $this->memberOf($team, 'admin');
        $member = $this->memberOf($team);
        $other = $this->company('Other Co');

        foreach ([$admin, $member, $other->owner] as $person) {
            try {
                app(LicenceBatches::class)->createPending($team, $person, 1, 'month');
                $this->fail('only the owner may buy');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $personal = Team::where('user_id', $team->user_id)->where('personal_team', true)->firstOrFail();
        $this->expectException(LicenceRefused::class);   // a personal workspace is not a company: nothing to buy
        app(LicenceBatches::class)->createPending($personal, $team->owner, 1, 'month');
    }

    public function test_unpaid_purchases_are_limited_and_closed_after_a_week_without_holding_anything(): void
    {
        $team = $this->company();
        foreach (range(1, 5) as $i) {
            $this->pending($team, 1);
        }

        try {
            $this->pending($team, 1);
            $this->fail('a sixth open purchase is refused');
        } catch (LicenceRefused $e) {
            $this->assertStringContainsString('unpaid purchases', $e->getMessage());
        }

        $this->travel(8)->days();
        $this->artisan('licence:sweep')->expectsOutputToContain('Closed 5 unpaid purchase(s)')->assertSuccessful();

        $this->assertSame(0, LicenceBatch::where('team_id', $team->id)->where('status', 'pending')->count());
        $this->assertSame(5, LicenceBatch::where('team_id', $team->id)->where('status', 'ended')->count());
        $this->assertSame(1, app(SeatLedger::class)->summary($team)['capacity']);
    }

    public function test_another_companys_batches_are_never_counted(): void
    {
        $a = $this->company('A Co');
        $b = $this->company('B Co');
        $this->paid($a, 3);

        $this->assertSame(4, app(SeatLedger::class)->summary($a)['capacity']);
        $this->assertSame(1, app(SeatLedger::class)->summary($b)['capacity']);
        $this->assertSame(0, app(LicencePricing::class)->heldBy(Team::findOrFail($b->id)));
    }

    public function test_the_owner_can_schedule_a_reduction_and_a_cancellation_but_nothing_changes_today(): void
    {
        $team = $this->company();
        $batch = $this->paid($team, 5);
        $batches = app(LicenceBatches::class);

        $batches->reduceAtPeriodEnd($batch, $team->owner, 3);
        $this->assertSame([5, 3], [$batch->fresh()->quantity, $batch->fresh()->quantity_next]);
        $this->assertSame(6, app(SeatLedger::class)->summary($team)['capacity'], 'capacity is unchanged until the period ends');

        foreach ([5, 7, -1] as $bad) {
            try {
                $batches->reduceAtPeriodEnd($batch, $team->owner, $bad);
                $this->fail('a reduction must be smaller');
            } catch (LicenceRefused) {
                $this->addToAssertionCount(1);
            }
        }

        $batches->cancelAtPeriodEnd($batch, $team->owner);
        $fresh = $batch->fresh();
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertNull($fresh->quantity_next);
        $this->assertSame('active', $fresh->status);
        $this->assertDatabaseHas('team_licence_events', ['team_id' => $team->id, 'type' => 'batch_cancel_requested']);

        $this->expectException(AuthorizationException::class);
        $batches->cancelAtPeriodEnd($batch, $this->memberOf($team, 'admin'));
    }

    public function test_only_paid_licences_can_be_reduced_or_cancelled(): void
    {
        $team = $this->company();
        $batch = $this->pending($team, 3);

        $this->expectException(LicenceRefused::class);
        app(LicenceBatches::class)->reduceAtPeriodEnd($batch, $team->owner, 1);
    }
}
