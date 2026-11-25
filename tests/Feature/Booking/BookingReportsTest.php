<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingEvent;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingBoard;
use App\Services\Booking\BookingCsv;
use App\Services\Booking\BookingReport;
use Carbon\CarbonImmutable;

/** R-63 phase 6: the booking reports and the CSV export. "Now" is Friday 20 November 2026; the week of Monday 23rd is the working week of these tests. */
class BookingReportsTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    private function reports(string $query = 'from=2026-11-23&to=2026-11-24', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->get($this->url('/reports?'.$query));
    }

    private function export(string $query = 'from=2026-11-23&to=2026-11-24', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->get($this->url('/reports/export?'.$query));
    }

    private function report(?User $as = null, string $filter = '', string $from = '2026-11-23', string $to = '2026-11-24', ?int $type = null): array
    {
        $board = BookingBoard::for($as ?? $this->owner, $this->team, $filter === '' ? null : $filter);

        return app(BookingReport::class)->build($board, $this->team, $from, $to, $type);
    }

    public function test_the_counts_follow_the_recorded_statuses_and_only_the_range(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 08:00:00', Booking::COMPLETED, $this->a);
        $this->mk('2026-11-24 07:00:00', Booking::NO_SHOW, $this->b);
        $this->mk('2026-11-24 09:00:00', Booking::REJECTED, $this->b);
        $this->mk('2026-11-30 07:00:00', Booking::CONFIRMED, $this->b); // outside the range

        $r = $this->report();

        $this->assertSame(4, $r['total']);
        $this->assertSame(1, $r['counts'][Booking::CONFIRMED]);
        $this->assertSame(1, $r['counts'][Booking::COMPLETED]);
        $this->assertSame(1, $r['counts'][Booking::NO_SHOW]);
        $this->assertSame(1, $r['counts'][Booking::REJECTED]);
        $this->assertSame(2, $r['days']);
    }

    public function test_the_range_is_worked_in_the_business_zone_not_utc(): void
    {
        // 22:30Z on Monday is 00:30 on Tuesday in Johannesburg: it counts on the 24th, not the 23rd.
        $this->mk('2026-11-23 22:30:00', Booking::CONFIRMED, $this->a);

        $this->assertSame(0, $this->report(from: '2026-11-23', to: '2026-11-23')['total']);
        $r = $this->report(from: '2026-11-24', to: '2026-11-24');
        $this->assertSame(1, $r['total']);
        $this->assertSame(1, collect($r['trend']['rows'])->firstWhere('label', '2026-11-24')['held']);
    }

    public function test_popular_types_are_counted_and_the_type_filter_narrows_everything(): void
    {
        $other = $this->type($this->team, ['name' => 'Follow-up']);
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 09:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-24 07:00:00', Booking::CONFIRMED, $this->a, ['appointment_type_id' => $other->id]);

        $r = $this->report();
        $this->assertSame([['name' => 'Consultation', 'count' => 2], ['name' => 'Follow-up', 'count' => 1]], $r['types']);

        $only = $this->report(type: $other->id);
        $this->assertSame(1, $only['total']);
        $this->assertNull($only['utilisation'], 'offered time is shared between types, so no share is shown for one type');
    }

    public function test_the_trend_counts_requests_made_and_appointments_held_separately(): void
    {
        $b = $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $b->forceFill(['created_at' => '2026-11-20 08:00:00'])->save();
        $c = $this->mk('2026-11-24 07:00:00', Booking::CANCELLED_BY_CUSTOMER, $this->a);
        $c->forceFill(['created_at' => '2026-11-23 08:00:00'])->save();

        $rows = collect($this->report()['trend']['rows'])->keyBy('label');

        $this->assertSame(['day', 1, 0], [$this->report()['trend']['unit'], $rows['2026-11-23']['held'], $rows['2026-11-23']['made'] - 1]);
        $this->assertSame(1, $rows['2026-11-23']['made'], 'the cancelled one was still requested on the 23rd');
        $this->assertSame(0, $rows['2026-11-24']['held'], 'a cancelled appointment does not take time');
    }

    public function test_a_long_range_is_grouped_by_week_starting_monday(): void
    {
        $this->mk('2026-11-25 07:00:00', Booking::CONFIRMED, $this->a); // Wednesday
        $r = $this->report(from: '2026-08-01', to: '2026-12-31');

        $this->assertSame('week', $r['trend']['unit']);
        $this->assertSame(1, collect($r['trend']['rows'])->firstWhere('label', '2026-11-23')['held']);
    }

    public function test_utilisation_is_booked_time_over_offered_time_and_closures_reduce_what_was_offered(): void
    {
        // Two providers, Monday and Tuesday, 8 hours each: 32 hours = 1920 minutes.
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 08:00:00', Booking::COMPLETED, $this->b);
        $this->mk('2026-11-23 09:00:00', Booking::CANCELLED_BY_BUSINESS, $this->b); // takes no time

        $u = $this->report()['utilisation'];
        $this->assertSame(['percent' => 6, 'booked' => 120, 'offered' => 1920], $u);

        // Thandi is away Monday morning, 09:00 to 13:00 local: 240 minutes fewer on offer.
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'booking_provider_id' => $this->a->id, 'starts_at' => '2026-11-23 07:00:00', 'ends_at' => '2026-11-23 11:00:00', 'kind' => 'closure']);
        $this->assertSame(1680, $this->report()['utilisation']['offered']);

        // A closure of the whole business removes both providers' time.
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'booking_provider_id' => null, 'starts_at' => '2026-11-24 00:00:00', 'ends_at' => '2026-11-24 23:59:00', 'kind' => 'holiday']);
        $this->assertSame(1680 - 960, $this->report()['utilisation']['offered']);
    }

    public function test_utilisation_is_absent_rather_than_invented(): void
    {
        $this->assertNull($this->report(from: '2026-11-28', to: '2026-11-29')['utilisation'], 'a weekend has no hours on offer');
        $this->assertNull($this->report(filter: BookingBoard::UNASSIGNED)['utilisation']);
    }

    public function test_capacity_is_right_across_a_clock_change(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        BookingSetting::forceCreate(['team_id' => $team->id, 'enabled' => true, 'timezone' => 'Europe/London', 'min_notice_minutes' => 0, 'horizon_days' => 365]);
        WorkingHour::forceCreate(['team_id' => $team->id, 'weekday' => 7, 'starts_at' => '00:00', 'ends_at' => '03:00']);
        $type = AppointmentType::forceCreate(['team_id' => $team->id, 'name' => 'Session', 'duration_minutes' => 30, 'slot_interval_minutes' => 30]);
        $type->providers()->attach($team->bookingProviders()->create(['user_id' => $owner->id, 'public_name' => 'Owner'])->id);

        // Sunday 25 October 2026: the clocks go back at 02:00, so midnight to 03:00 local is four real hours.
        $this->assertSame(240, app(Availability::class)->capacityMinutes($team, '2026-10-25', '2026-10-25'));
        $this->assertSame(180, app(Availability::class)->capacityMinutes($team, '2026-11-01', '2026-11-01'));
    }

    public function test_the_no_show_rate_needs_marked_appointments(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->assertNull($this->report()['no_show_rate']);

        $this->mk('2026-11-23 08:00:00', Booking::COMPLETED, $this->a);
        $this->mk('2026-11-23 09:00:00', Booking::COMPLETED, $this->a);
        $this->mk('2026-11-23 10:00:00', Booking::COMPLETED, $this->b);
        $this->mk('2026-11-24 07:00:00', Booking::NO_SHOW, $this->b);

        $r = $this->report();
        $this->assertSame(25, $r['no_show_rate']);
        $this->assertSame(4, $r['tracked']);
    }

    public function test_a_provider_sees_only_their_own_numbers_and_cannot_widen_them(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 08:00:00', Booking::CONFIRMED, $this->b);
        $this->mk('2026-11-23 09:00:00', Booking::CONFIRMED, null);

        $this->assertSame(1, $this->report($this->thandi)['total']);
        $this->assertSame(1, $this->report($this->thandi, (string) $this->b->id)['total'], 'a provider filter is ignored for someone who cannot manage');
        $this->assertSame(['percent' => 6, 'booked' => 60, 'offered' => 960], $this->report($this->thandi)['utilisation']);
        $this->assertSame(3, $this->report()['total']);

        $this->reports(as: $this->thandi)->assertOk()->assertDontSee('Download as CSV');
    }

    public function test_a_manager_can_narrow_the_report_to_one_provider(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 08:00:00', Booking::CONFIRMED, $this->b);

        $this->assertSame(1, $this->report(filter: (string) $this->a->id)['total']);
        $this->assertSame(960, $this->report(filter: (string) $this->a->id)['utilisation']['offered']);
    }

    public function test_the_page_shows_real_numbers_or_an_honest_empty_state(): void
    {
        $this->reports()->assertOk()->assertSee('No appointments start in this range yet');

        $this->mk('2026-11-23 07:00:00', Booking::COMPLETED, $this->a);
        $this->mk('2026-11-24 07:00:00', Booking::NO_SHOW, $this->b);
        $this->reports()->assertOk()->assertSee('2 appointments')->assertSee('50%')->assertSee('Download as CSV')->assertSee('Most booked');
    }

    public function test_dates_are_validated_and_clamped(): void
    {
        $this->reports('from=nonsense&to=2026-02-31&type=abc&provider[]=1')->assertOk();
        $this->reports('from=2026-11-24&to=2026-11-23')->assertOk()->assertSee('2026-11-23');
        $this->reports('from=2010-01-01&to=2026-11-24')->assertOk()->assertSee('The dates were adjusted');
        $this->reports('from=2025-01-01&to=2026-11-24')->assertOk()->assertSee('at most 366 days');
        $this->reports('type=999999')->assertOk();
    }

    public function test_another_businesss_bookings_never_appear_and_its_type_filter_is_ignored(): void
    {
        [$otherTeam] = $this->business('available', 'Other Co');
        $otherType = $this->type($otherTeam);
        $otherStart = '2026-11-23 07:00:00';
        Booking::forceCreate(['team_id' => $otherTeam->id, 'appointment_type_id' => $otherType->id, 'customer_name' => 'Other Person', 'customer_email' => 'o@example.test', 'starts_at' => $otherStart, 'ends_at' => '2026-11-23 08:00:00', 'blocks_from' => $otherStart, 'blocks_until' => '2026-11-23 08:00:00', 'status' => 'confirmed']);
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);

        $this->assertSame(1, $this->report()['total']);
        $this->reports('from=2026-11-23&to=2026-11-24&type='.$otherType->id)->assertOk()->assertSee('1 appointment');
        $this->actingAs($this->owner)->get('/business/'.$otherTeam->uuid.'/appointments/reports')->assertForbidden();
    }

    public function test_the_export_is_for_managers_only_and_carries_no_private_notes(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a, ['internal_notes' => 'Never share this', 'status_note' => 'Hidden message']);

        $this->export(as: $this->thandi)->assertForbidden();
        $this->export(as: $this->stranger())->assertForbidden();

        $response = $this->export()->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $body = $response->streamedContent();

        $this->assertStringContainsString('Reference,Status,Appointment,Provider,Starts,Ends,Minutes,Requested,Customer,Email,Phone,Company', $body);
        $this->assertStringContainsString('"Zodwa Secret",zodwa@example.test', $body);
        $this->assertStringContainsString('"2026-11-23 09:00","2026-11-23 10:00",60', $body);
        $this->assertStringNotContainsString('A private matter', $body);
        $this->assertStringNotContainsString('Never share this', $body);
        $this->assertStringNotContainsString('Hidden message', $body);
    }

    public function test_spreadsheet_formulas_in_customer_text_are_neutralised(): void
    {
        foreach (['=HYPERLINK("http://evil.test","x")', '+1+1', '-2+3', '@SUM(A1)', "\tcmd", "\rcmd"] as $evil) {
            $this->assertStringStartsWith("'", BookingCsv::cell($evil), $evil);
        }
        $this->assertSame('Zodwa', BookingCsv::cell('Zodwa'));
        $this->assertSame('', BookingCsv::cell(''));
        $this->assertSame('60', BookingCsv::cell(60));
        $this->assertSame('+27 82 000 0000', BookingCsv::cell('+27 82 000 0000'), 'a real phone number stays as it is');
        $this->assertStringStartsWith("'", BookingCsv::cell("\ncmd"));

        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a, ['customer_name' => '=cmd|calc', 'customer_company' => '@evil']);
        $body = $this->export()->streamedContent();
        $this->assertStringContainsString("'=cmd|calc", $body);
        $this->assertStringContainsString("'@evil", $body);
    }

    public function test_an_export_is_recorded_without_any_customer_data(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->export()->streamedContent();

        $event = BookingEvent::where('type', 'bookings_exported')->sole();
        $this->assertSame($this->owner->id, $event->actor_id);
        $this->assertSame(['from' => '2026-11-23', 'to' => '2026-11-24', 'count' => 1, 'provider' => null, 'type' => null], $event->details);
        $this->assertStringNotContainsString('Zodwa', json_encode($event->details));
    }

    public function test_the_export_respects_the_provider_filter_and_the_row_limit(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a, ['customer_name' => 'For Thandi']);
        $this->mk('2026-11-23 08:00:00', Booking::CONFIRMED, $this->b, ['customer_name' => 'For Sipho']);

        $body = $this->export('from=2026-11-23&to=2026-11-24&provider='.$this->a->id)->streamedContent();
        $this->assertStringContainsString('For Thandi', $body);
        $this->assertStringNotContainsString('For Sipho', $body);
        $this->assertSame(5000, BookingReport::EXPORT_LIMIT);
    }

    public function test_a_large_export_keeps_start_order_across_groups_and_loses_nothing(): void
    {
        // 620 bookings (more than one group), created in the opposite order to their start times.
        $rows = [];
        for ($i = 0; $i < 620; $i++) {
            $start = CarbonImmutable::parse('2026-11-23 05:00:00', 'UTC')->addMinutes((619 - $i) * 1);
            $rows[] = ['reference' => 'R'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'team_id' => $this->team->id, 'appointment_type_id' => $this->type->id, 'booking_provider_id' => $this->a->id,
                'customer_name' => 'C'.$i, 'customer_email' => 'c@example.test', 'starts_at' => $start, 'ends_at' => $start->addMinutes(1), 'blocks_from' => $start, 'blocks_until' => $start->addMinutes(1),
                'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now()];
        }
        Booking::query()->insert($rows);

        $out = iterator_to_array(app(BookingReport::class)->rows(BookingBoard::for($this->owner, $this->team), $this->team, '2026-11-23', '2026-11-23'), false);

        $this->assertCount(620, $out);
        $this->assertSame('R0619', $out[0]['reference'], 'earliest start first');
        $this->assertSame('R0000', $out[619]['reference']);
    }

    public function test_every_report_screen_is_closed_until_the_feature_is_open(): void
    {
        $this->feature('development');

        $this->reports()->assertNotFound();
        $this->export()->assertNotFound();
    }

    public function test_guests_and_non_members_are_turned_away_and_the_routes_are_throttled(): void
    {
        $this->get($this->url('/reports'))->assertRedirect('/login');
        $this->reports(as: $this->stranger())->assertForbidden();

        $names = collect(app('router')->getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'business.booking.reports'))
            ->map(fn ($r) => collect($r->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')))->sort()->values()->all();
        $this->assertSame(['throttle:6,1,business.booking.export', 'throttle:60,1,business.booking.reports'], $names);
    }

    public function test_a_removed_provider_loses_the_reports_too(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->a->update(['active' => false]);

        $this->reports(as: $this->thandi)->assertForbidden();
        $this->assertInstanceOf(BookingProvider::class, $this->a);
    }
}
