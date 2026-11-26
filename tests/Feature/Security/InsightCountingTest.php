<?php

namespace Tests\Feature\Security;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R-72 (F-02): a business's contact counts (calls, emails, website, map) are what it judges its page by. Opening a fresh session for every
 * request must not let one machine inflate them: each network address counts once a day per business and action.
 */
class InsightCountingTest extends TestCase
{
    use RefreshDatabase;

    private function calls(Team $team): int
    {
        return (int) DB::table('business_stats_daily')->where('team_id', $team->id)->where('metric', 'call')->sum('count');
    }

    public function test_a_fresh_session_per_request_does_not_inflate_the_count(): void
    {
        $owner = User::factory()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $team->profileOrNew()->forceFill(['visibility' => 'public', 'description' => 'We drill boreholes.'])->save();
        $agent = ['User-Agent' => 'Mozilla/5.0 (Macintosh) Safari/605.1'];

        for ($i = 0; $i < 4; $i++) {
            $this->flushSession();
            $this->withHeaders($agent)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->post('/business/'.$team->uuid.'/track/call')->assertNoContent();
        }
        $this->assertSame(1, $this->calls($team), 'one address, four sessions: counted once');

        $this->flushSession();
        $this->withHeaders($agent)->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->post('/business/'.$team->uuid.'/track/call')->assertNoContent();
        $this->assertSame(2, $this->calls($team), 'another visitor counts');
    }
}
