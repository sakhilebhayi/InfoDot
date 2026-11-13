<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\User;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * R-49. A seminar's own page puts the person's place (registered, join, cancel) before the rarely used report
 * disclosures, and the disclosures are the shared `ix-disclose` (a 44px target on touch).
 */
class SeminarPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_persons_place_comes_before_the_report_disclosures(): void
    {
        $host = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $attendee = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $seminar = Seminar::create(['host_id' => $host->id, 'title' => 'Pricing your first product', 'summary' => 'An hour.', 'starts_at' => now()->addDays(3), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room', 'status' => 'published']);
        app(SeminarService::class)->register($seminar, $attendee);

        $html = (string) $this->actingAs($attendee)->get('/seminars/'.$seminar->id)->assertOk()->getContent();

        $place = strpos($html, 'You are registered');
        $report = strpos($html, 'Report this seminar');
        $this->assertNotFalse($place);
        $this->assertNotFalse($report);
        $this->assertLessThan($report, $place, 'the registration panel comes first');
        $this->assertSame(2, substr_count($html, '<details class="ix-disclose"'), 'report the seminar, and report someone in it');

        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        $this->assertMatchesRegularExpression('/@media \(pointer: coarse\), \(max-width: 768px\)\s*\{\s*\.ix-disclose > summary\s*\{\s*padding: 12px 0;/', $css);
    }
}
