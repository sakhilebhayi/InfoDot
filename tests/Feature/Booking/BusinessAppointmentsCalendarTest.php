<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingClosure;

/** R-63 phase 4: the calendar, in the business's own zone (Johannesburg, UTC+2). */
class BusinessAppointmentsCalendarTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    private function cal(string $query)
    {
        return $this->actingAs($this->owner)->get($this->url('/calendar?'.$query));
    }

    public function test_the_default_is_the_week_of_today_in_the_business_zone_monday_first(): void
    {
        $this->mk('2026-11-23 08:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Next Week Cust']);
        $this->mk('2026-11-21 08:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Saturday Cust']);
        $this->mk('2026-11-16 08:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Last Week Cust']);

        // Friday 20 Nov: the week is Monday 16 to Sunday 22 November.
        $this->cal('')->assertOk()->assertSee('16 Nov to 22 Nov 2026')->assertSeeInOrder(['Monday 16 Nov', 'Friday 20 Nov', 'Sunday 22 Nov'])
            ->assertSee('Saturday Cust')->assertSee('Last Week Cust')->assertDontSee('Next Week Cust');
        $this->cal('view=week&date=2026-11-23')->assertSee('Next Week Cust')->assertDontSee('Saturday Cust');
    }

    public function test_a_booking_near_midnight_utc_falls_on_the_local_day(): void
    {
        // 22:30Z on Sunday 29 November is 00:30 on Monday 30 November in Johannesburg.
        $this->mk('2026-11-29 22:30:00', Booking::CONFIRMED, null, ['customer_name' => 'Midnight Cust']);

        $this->cal('view=day&date=2026-11-30')->assertSee('Midnight Cust')->assertSee('00:30 to 01:30');
        $this->cal('view=day&date=2026-11-29')->assertDontSee('Midnight Cust');
        $this->cal('view=week&date=2026-11-30')->assertSee('Midnight Cust');
    }

    public function test_the_week_boundary_follows_local_midnight(): void
    {
        $this->mk('2026-11-29 22:30:00', Booking::CONFIRMED, null, ['customer_name' => 'Midnight Cust']);

        $this->cal('view=week&date=2026-11-25')->assertDontSee('Midnight Cust'); // week of 23 to 29 November
        $this->cal('view=week&date=2026-12-02')->assertSee('Midnight Cust');      // week of 30 November
    }

    public function test_the_month_shows_counts_per_local_day_and_links_to_the_day(): void
    {
        $this->mk('2026-11-29 22:30:00', Booking::CONFIRMED);   // 30 Nov local
        $this->mk('2026-11-30 08:00:00', Booking::PENDING);     // 30 Nov local
        $this->mk('2026-11-29 20:00:00', Booking::CONFIRMED);   // 29 Nov 22:00 local
        $this->mk('2026-12-01 08:00:00', Booking::CONFIRMED);   // December

        $r = $this->cal('view=month&date=2026-11-10')->assertOk()->assertSee('November 2026')->assertSee('2 appointments')->assertSee('1 appointment');
        $r->assertSee('view=day&amp;date=2026-11-30', false);
        $r->assertSeeInOrder(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
        $this->assertSame(1, substr_count($r->getContent(), '2 appointments'));
        $this->cal('view=month&date=2026-12-10')->assertSee('December 2026')->assertSee('1 appointment')->assertDontSee('2 appointments');
    }

    public function test_only_holding_appointments_show_unless_closed_ones_are_asked_for(): void
    {
        $this->mk('2026-11-23 08:00:00', Booking::PENDING, null, ['customer_name' => 'Waiting Cust']);
        $this->mk('2026-11-23 09:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Sure Cust']);
        $this->mk('2026-11-23 10:00:00', Booking::CANCELLED_BY_CUSTOMER, null, ['customer_name' => 'Gone Cust']);
        $this->mk('2026-11-23 11:00:00', Booking::REJECTED, null, ['customer_name' => 'Refused Cust']);

        $this->cal('view=day&date=2026-11-23')->assertSee('Waiting Cust')->assertSee('Sure Cust')->assertDontSee('Gone Cust')->assertDontSee('Refused Cust')->assertSee('Show closed appointments too');
        $this->cal('view=day&date=2026-11-23&closed=1')->assertSeeInOrder(['Waiting Cust', 'Sure Cust', 'Gone Cust', 'Refused Cust'])->assertSee('Hide closed appointments');
    }

    public function test_closures_are_marked_with_their_reason_for_the_business(): void
    {
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'booking_provider_id' => null, 'kind' => 'holiday', 'starts_at' => '2026-11-22 22:00:00', 'ends_at' => '2026-11-24 22:00:00', 'reason' => 'Staff retreat']);
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'booking_provider_id' => $this->a->id, 'kind' => 'block', 'starts_at' => '2026-11-25 07:00:00', 'ends_at' => '2026-11-25 09:00:00', 'reason' => 'Dentist']);

        $r = $this->cal('view=week&date=2026-11-23')->assertSee('Closed (whole business): Staff retreat')->assertSee('Blocked 09:00 to 11:00 (Thandi): Dentist');
        $this->assertSame(2, substr_count($r->getContent(), 'Staff retreat'));
        $this->cal('view=day&date=2026-11-25')->assertSee('Dentist')->assertDontSee('Staff retreat');
        $this->cal('view=month&date=2026-11-10')->assertSee('Closed');
        $this->actingAs($this->sipho)->get($this->url('/calendar?view=week&date=2026-11-23'))->assertSee('Staff retreat')->assertDontSee('Dentist');
        $this->actingAs($this->thandi)->get($this->url('/calendar?view=week&date=2026-11-23'))->assertSee('Dentist');
    }

    public function test_bad_views_and_dates_fall_back_and_far_dates_are_clamped(): void
    {
        $this->cal('view=year&date=nonsense')->assertOk()->assertSee('16 Nov to 22 Nov 2026');
        $this->cal('view=day&date=2026-02-30')->assertOk()->assertSee('Friday 20 November 2026');
        $this->cal('view=day&date[]=x')->assertOk()->assertSee('Friday 20 November 2026');
        $this->cal('view=day&date=1999-01-05')->assertOk()->assertSee('Wednesday 20 November 2024');
        $this->cal('view=day&date=2099-01-05')->assertOk()->assertSee('Monday 20 November 2028');
    }

    public function test_previous_next_and_today_links_stay_in_range_and_keep_the_filter(): void
    {
        $this->cal('view=week&date=2026-11-20&provider='.$this->a->id)->assertSee('date=2026-11-13', false)->assertSee('date=2026-11-27', false)->assertSee('provider='.$this->a->id, false);
        $this->cal('view=month&date=2026-11-20')->assertSee('date=2026-10-01', false)->assertSee('date=2026-12-01', false);
        $this->cal('view=day&date=2028-11-20')->assertDontSee('rel="next"', false)->assertSee('rel="prev"', false);
        $this->cal('view=day&date=2024-11-20')->assertDontSee('rel="prev"', false);
    }

    public function test_a_manager_can_filter_the_calendar_to_a_provider(): void
    {
        $this->mk('2026-11-23 08:00:00', Booking::CONFIRMED, $this->a, ['customer_name' => 'Alpha Cust']);
        $this->mk('2026-11-23 09:00:00', Booking::CONFIRMED, $this->b, ['customer_name' => 'Beta Cust']);

        $this->cal('view=day&date=2026-11-23&provider='.$this->b->id)->assertSee('Beta Cust')->assertDontSee('Alpha Cust');
    }
}
