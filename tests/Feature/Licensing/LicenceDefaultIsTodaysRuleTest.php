<?php

namespace Tests\Feature\Licensing;

use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\Grandfathering;
use App\Services\Licensing\SeatLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Merging R-53 must change nothing: the code default is today's three, and one free licence is a deliberate host setting. */
class LicenceDefaultIsTodaysRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shipped_defaults_keep_todays_behaviour_and_leave_payments_off(): void
    {
        $config = (function () {
            $keep = [];
            foreach (['LICENCE_INCLUDED_SEATS', 'LICENCE_FINANCE_CONNECTED', 'LICENCE_FINANCE_URL', 'FINANCE_ENTITLEMENT_SECRET'] as $k) {
                $keep[$k] = $_SERVER[$k] ?? null;
                unset($_SERVER[$k], $_ENV[$k]);
                putenv($k);
            }
            try {
                return require base_path('config/licensing.php');
            } finally {
                foreach ($keep as $k => $v) {
                    if ($v !== null) {
                        $_SERVER[$k] = $v;
                    }
                }
            }
        })();

        $this->assertSame(3, $config['included_seats'], 'production default: three free, exactly as before R-53');
        $this->assertSame(1, $config['target_included_seats']);
        $this->assertFalse($config['finance_connected']);
        $this->assertEmpty($config['finance']['url']);
        $this->assertEmpty($config['finance']['secret']);
        $this->assertEmpty($config['price']['amount_minor_units']);
    }

    public function test_the_setting_is_the_cutover_three_then_one(): void
    {
        $team = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);

        config(['licensing.included_seats' => 3]);
        $this->assertSame(3, app(SeatLedger::class)->summary($team)['capacity']);

        config(['licensing.included_seats' => 1]);
        $s = app(SeatLedger::class)->summary($team);
        $this->assertSame([1, 0], [$s['capacity'], $s['available']], 'one free licence: the owner');
    }

    public function test_the_freeze_works_while_the_live_setting_is_still_three(): void
    {
        config(['licensing.included_seats' => 3]);
        $team = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        $team->users()->attach(User::factory()->create(), ['role' => 'member']);   // two people: within three, above one

        $rows = app(Grandfathering::class)->apply([$team->id]);
        $this->assertSame('freeze', $rows[0]['action']);

        config(['licensing.included_seats' => 1]);   // the cutover
        $s = app(SeatLedger::class)->summary($team->fresh());
        $this->assertSame([2, 0, true], [$s['capacity'], $s['available'], $s['grandfathered']], 'nobody is stranded: the two people stay, nobody more can join');
    }
}
