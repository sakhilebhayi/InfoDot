<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R-63 phase 2: the availability engine. Dates below are local to the business unless they end in Z. Monday 30 November 2026 is the working day used
 * throughout; "now" is always passed in, so nothing depends on the day the suite runs.
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2026-11-30';

    private Team $team;

    private function team(string $zone = 'Africa/Johannesburg', array $settings = []): Team
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        BookingSetting::forceCreate($settings + ['team_id' => $team->id, 'enabled' => true, 'timezone' => $zone, 'min_notice_minutes' => 0, 'horizon_days' => 365]);

        return $this->team = $team;
    }

    /** @param  array<string, mixed>  $over */
    private function type(?Team $team = null, array $over = []): AppointmentType
    {
        return ($team ?? $this->team)->appointmentTypes()->create($over + ['name' => 'Consultation', 'duration_minutes' => 60, 'slot_interval_minutes' => 60]);
    }

    private function hours(int $weekday, string $from, string $to, ?BookingProvider $provider = null, ?Team $team = null): void
    {
        WorkingHour::forceCreate(['team_id' => ($team ?? $this->team)->id, 'booking_provider_id' => $provider?->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to]);
    }

    private function provider(string $name = 'Thandi', ?Team $team = null): BookingProvider
    {
        $team ??= $this->team;

        return $team->bookingProviders()->create(['user_id' => User::factory()->create()->id, 'public_name' => $name]);
    }

    private function closure(string $fromZ, string $toZ, ?BookingProvider $provider = null, ?Team $team = null): void
    {
        BookingClosure::forceCreate(['team_id' => ($team ?? $this->team)->id, 'booking_provider_id' => $provider?->id, 'kind' => 'block', 'starts_at' => $fromZ, 'ends_at' => $toZ]);
    }

    private function book(AppointmentType $type, string $startZ, ?BookingProvider $provider = null, string $status = Booking::CONFIRMED, ?Team $team = null): Booking
    {
        $start = CarbonImmutable::parse($startZ, 'UTC');
        $end = $start->addMinutes($type->duration_minutes);

        return Booking::create([
            'team_id' => ($team ?? $this->team)->id, 'appointment_type_id' => $type->id, 'booking_provider_id' => $provider?->id,
            'customer_name' => 'A Customer', 'customer_email' => 'c@example.test', 'starts_at' => $start, 'ends_at' => $end,
            'blocks_from' => $start->subMinutes($type->buffer_before_minutes), 'blocks_until' => $end->addMinutes($type->buffer_after_minutes), 'status' => $status,
        ]);
    }

    private function now(string $z = '2026-11-20 08:00:00'): CarbonImmutable
    {
        return CarbonImmutable::parse($z, 'UTC');
    }

    /** @return list<string> the slot starts as UTC 'H:i' */
    private function starts(AppointmentType $type, string $date = self::MONDAY, ?int $provider = null, ?CarbonImmutable $now = null): array
    {
        return app(Availability::class)->slots($type, $date, $provider, $now ?? $this->now())->map(fn ($s) => $s->start->format('H:i'))->all();
    }

    private function dayStatus(AppointmentType $type, string $date = self::MONDAY, ?CarbonImmutable $now = null): string
    {
        return app(Availability::class)->dayStatus($type, $date, null, $now ?? $this->now());
    }

    // ---------------------------------------------------------------------------------------------------------------- hours and the grid

    public function test_slots_follow_the_working_hours_in_the_business_zone(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');

        // 09:00, 10:00 and 11:00 in Johannesburg (UTC+2) are 07:00, 08:00 and 09:00 UTC.
        $this->assertSame(['07:00', '08:00', '09:00'], $this->starts($type));
    }

    public function test_start_times_are_spaced_by_the_slot_interval_and_the_whole_appointment_must_fit(): void
    {
        $type = $this->type($this->team(), ['duration_minutes' => 60, 'slot_interval_minutes' => 30]);
        $this->hours(1, '09:00', '11:00');

        $this->assertSame(['07:00', '07:30', '08:00'], $this->starts($type), 'the last start is 10:00, which ends exactly at 11:00');
    }

    public function test_a_stretch_shorter_than_the_appointment_is_not_offered(): void
    {
        $type = $this->type($this->team(), ['duration_minutes' => 90]);
        $this->hours(1, '09:00', '10:00');

        $this->assertSame([], $this->starts($type));
        $this->assertSame(Availability::NOT_OFFERED, $this->dayStatus($type));
    }

    public function test_a_gap_between_two_stretches_is_a_break(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $this->hours(1, '13:00', '15:00');

        $this->assertSame(['07:00', '08:00', '09:00', '11:00', '12:00'], $this->starts($type), 'nothing between 12:00 and 13:00');
    }

    public function test_touching_and_overlapping_stretches_are_one(): void
    {
        $type = $this->type($this->team(), ['slot_interval_minutes' => 60]);
        $this->hours(1, '09:00', '11:00');
        $this->hours(1, '11:00', '13:00');
        $this->hours(1, '10:00', '12:00');

        $this->assertSame(['07:00', '08:00', '09:00', '10:00'], $this->starts($type));
    }

    public function test_a_weekday_with_no_hours_is_not_offered(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '17:00');

        $this->assertSame(Availability::NOT_OFFERED, $this->dayStatus($type, '2026-12-06'), 'a Sunday');
        $this->assertSame(Availability::AVAILABLE, $this->dayStatus($type));
    }

    public function test_a_business_with_no_hours_at_all_offers_nothing(): void
    {
        $type = $this->type($this->team());

        $this->assertSame([], $this->starts($type));
        $this->assertSame(Availability::NOT_OFFERED, $this->dayStatus($type));
    }

    // ---------------------------------------------------------------------------------------------------------------- notice and horizon

    public function test_the_least_notice_removes_the_start_times_that_are_too_soon(): void
    {
        $type = $this->type($this->team('Africa/Johannesburg', ['min_notice_minutes' => 120]));
        $this->hours(1, '09:00', '12:00');

        // Now is 07:00 local on the Monday (05:00Z); two hours' notice means nothing earlier than 09:00 local, which is allowed.
        $this->assertSame(['07:00', '08:00', '09:00'], $this->starts($type, self::MONDAY, null, $this->now('2026-11-30 05:00:00')));
        // Now is 08:30 local: the 09:00 start is only 30 minutes away, the 10:00 start is 90 minutes away: both too soon; 11:00 is 150 minutes away.
        $this->assertSame(['09:00'], $this->starts($type, self::MONDAY, null, $this->now('2026-11-30 06:30:00')));
    }

    public function test_a_day_with_hours_whose_times_have_all_passed_is_outside_the_window(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');

        $this->assertSame(Availability::OUTSIDE_WINDOW, $this->dayStatus($type, self::MONDAY, $this->now('2026-11-30 12:00:00')), 'it is 14:00 local');
        $this->assertSame([], $this->starts($type, self::MONDAY, null, $this->now('2026-11-30 12:00:00')));
    }

    public function test_the_past_and_dates_beyond_the_furthest_ahead_are_outside_the_window(): void
    {
        $type = $this->type($this->team('Africa/Johannesburg', ['horizon_days' => 7]));
        foreach ([1, 2, 3, 4, 5] as $d) {
            $this->hours($d, '09:00', '12:00');
        }
        $now = $this->now('2026-11-23 08:00:00'); // Monday 23 November, 10:00 local

        $this->assertSame(Availability::OUTSIDE_WINDOW, $this->dayStatus($type, '2026-11-20', $now), 'a past Friday');
        $this->assertSame(Availability::AVAILABLE, $this->dayStatus($type, '2026-11-30', $now), 'today plus 7 is the last day');
        $this->assertSame(Availability::OUTSIDE_WINDOW, $this->dayStatus($type, '2026-12-01', $now), 'today plus 8');
    }

    // ---------------------------------------------------------------------------------------------------------------- closures

    public function test_a_closure_covering_the_day_makes_it_unavailable(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $this->closure('2026-11-29 22:00:00', '2026-11-30 22:00:00'); // the whole of Monday 30 November, local

        $this->assertSame(Availability::UNAVAILABLE, $this->dayStatus($type));
        $this->assertSame([], $this->starts($type));
    }

    public function test_a_partial_block_removes_only_the_start_times_it_touches(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '13:00');
        $this->closure('2026-11-30 08:30:00', '2026-11-30 09:30:00'); // 10:30 to 11:30 local

        // 09:00 (07:00Z) is clear; 10:00 would run to 11:00 and touches the block; 11:00 starts inside it; 12:00 (10:00Z) is clear.
        $this->assertSame(['07:00', '10:00'], $this->starts($type));
        $this->assertSame(Availability::AVAILABLE, $this->dayStatus($type));
    }

    public function test_a_block_ending_exactly_when_an_appointment_starts_does_not_clash(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $this->closure('2026-11-30 06:00:00', '2026-11-30 07:00:00'); // ends at 09:00 local

        $this->assertSame(['07:00', '08:00'], $this->starts($type));
    }

    // ---------------------------------------------------------------------------------------------------------------- bookings

    public function test_pending_and_confirmed_bookings_hold_their_time_and_the_others_do_not(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '13:00');

        foreach ([Booking::PENDING, Booking::CONFIRMED] as $holding) {
            Booking::query()->delete();
            $this->book($type, '2026-11-30 08:00:00', null, $holding); // 10:00 local
            $this->assertSame(['07:00', '09:00', '10:00'], $this->starts($type), $holding);
        }

        foreach ([Booking::REJECTED, Booking::CANCELLED_BY_CUSTOMER, Booking::CANCELLED_BY_BUSINESS, Booking::COMPLETED, Booking::NO_SHOW] as $free) {
            Booking::query()->delete();
            $this->book($type, '2026-11-30 08:00:00', null, $free);
            $this->assertSame(['07:00', '08:00', '09:00', '10:00'], $this->starts($type), $free);
        }
    }

    public function test_back_to_back_appointments_are_allowed_and_overlapping_ones_are_not(): void
    {
        $type = $this->type($this->team(), ['slot_interval_minutes' => 30]);
        $this->hours(1, '09:00', '12:00');
        $this->book($type, '2026-11-30 08:00:00'); // 10:00 to 11:00 local

        // 09:00 ends as the booking starts; 09:30 runs into it; 10:00 to 10:30 sit inside it; 11:00 starts as it ends.
        $this->assertSame(['07:00', '09:00'], $this->starts($type));
    }

    public function test_buffers_hold_time_between_bookings(): void
    {
        $type = $this->type($this->team(), ['buffer_before_minutes' => 15, 'buffer_after_minutes' => 15, 'slot_interval_minutes' => 30]);
        $this->hours(1, '09:00', '13:00');
        $this->book($type, '2026-11-30 08:00:00'); // 10:00 to 11:00 local; holds 09:45 to 11:15 local

        // Starting 07:30Z would end 08:30Z (+15 = 08:45Z) and run into the 07:45Z hold; 09:00Z would begin (-15) at 08:45Z, inside the hold which runs
        // to 09:15Z. The first start whose own buffer clears the hold is 09:30Z.
        $this->assertSame(['09:30', '10:00'], $this->starts($type));
    }

    public function test_a_buffer_may_extend_beyond_the_end_of_the_working_day(): void
    {
        $type = $this->type($this->team(), ['buffer_after_minutes' => 30]);
        $this->hours(1, '09:00', '10:00');

        $this->assertSame(['07:00'], $this->starts($type), 'the 30 minute buffer after 10:00 does not stop the last appointment of the day');
    }

    public function test_a_day_with_every_start_time_taken_is_full_and_another_is_available(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $this->book($type, '2026-11-30 07:00:00');
        $this->book($type, '2026-11-30 08:00:00');

        $this->assertSame(Availability::FULL, $this->dayStatus($type));
        $this->assertSame([], $this->starts($type));
    }

    // ---------------------------------------------------------------------------------------------------------------- providers

    public function test_a_type_with_providers_offers_the_union_and_names_the_provider_with_the_lowest_id(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $a = $this->provider('Thandi');
        $b = $this->provider('Sipho');
        $type->providers()->attach([$a->id, $b->id]);
        $this->book($type, '2026-11-30 08:00:00', $a); // Thandi is busy at 10:00

        $slots = app(Availability::class)->slots($type, self::MONDAY, null, $this->now());

        $this->assertSame(['07:00', '08:00', '09:00'], $slots->map(fn ($s) => $s->start->format('H:i'))->all(), 'Sipho is free at 10:00, so it is still offered');
        $this->assertSame([$a->id, $b->id, $a->id], $slots->pluck('providerId')->all());
    }

    public function test_asking_for_one_provider_shows_only_theirs(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $a = $this->provider('Thandi');
        $b = $this->provider('Sipho');
        $type->providers()->attach([$a->id, $b->id]);
        $this->book($type, '2026-11-30 08:00:00', $a);

        $this->assertSame(['07:00', '09:00'], $this->starts($type, self::MONDAY, $a->id));
        $this->assertSame(['07:00', '08:00', '09:00'], $this->starts($type, self::MONDAY, $b->id));
    }

    public function test_a_provider_with_their_own_hours_works_only_inside_the_businesss(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $a = $this->provider();
        $type->providers()->attach($a->id);
        $this->hours(1, '08:00', '10:30', $a); // starts before the business opens

        $this->assertSame(['07:00'], $this->starts($type), 'her hours are clipped to 09:00 to 10:30: only the 09:00 start fits');
    }

    public function test_a_provider_with_no_hours_of_their_own_works_the_businesss(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $type->providers()->attach($this->provider()->id);

        $this->assertSame(['07:00', '08:00'], $this->starts($type));
    }

    public function test_a_provider_cannot_work_when_the_business_has_no_hours(): void
    {
        $type = $this->type($this->team());
        $a = $this->provider();
        $type->providers()->attach($a->id);
        $this->hours(1, '09:00', '12:00', $a);

        $this->assertSame([], $this->starts($type));
    }

    public function test_an_inactive_provider_is_not_offered_and_a_type_with_only_inactive_providers_is_closed(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $a = $this->provider();
        $a->update(['active' => false]);
        $type->providers()->attach($a->id);

        $this->assertSame([], $this->starts($type), 'it does not fall back to the business');
        $this->assertSame(Availability::NOT_OFFERED, $this->dayStatus($type));
    }

    public function test_a_provider_who_does_not_offer_the_type_cannot_be_asked_for(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $offers = $this->provider('Thandi');
        $other = $this->provider('Sipho');
        $type->providers()->attach($offers->id);

        $this->assertSame([], $this->starts($type, self::MONDAY, $other->id));
        $this->assertNotSame([], $this->starts($type, self::MONDAY, $offers->id));
    }

    public function test_a_providers_block_closes_only_that_provider_but_a_business_closure_closes_everyone(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $a = $this->provider('Thandi');
        $b = $this->provider('Sipho');
        $type->providers()->attach([$a->id, $b->id]);

        $this->closure('2026-11-30 07:00:00', '2026-11-30 09:00:00', $a); // Thandi is out all morning
        $this->assertSame([], $this->starts($type, self::MONDAY, $a->id));
        $this->assertSame(['07:00', '08:00'], $this->starts($type, self::MONDAY, $b->id));

        $this->closure('2026-11-30 07:00:00', '2026-11-30 09:00:00');
        $this->assertSame([], $this->starts($type));
        $this->assertSame(Availability::UNAVAILABLE, $this->dayStatus($type));
    }

    public function test_a_type_with_no_providers_is_the_business_and_ignores_a_providers_block(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '11:00');
        $someone = $this->provider();
        $this->closure('2026-11-30 07:00:00', '2026-11-30 09:00:00', $someone);

        $this->assertSame(['07:00', '08:00'], $this->starts($type));
    }

    public function test_a_booking_held_by_one_provider_does_not_block_another(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '10:00');
        $a = $this->provider('Thandi');
        $b = $this->provider('Sipho');
        $type->providers()->attach([$a->id, $b->id]);

        $this->book($type, '2026-11-30 07:00:00', $a);
        $this->assertSame(['07:00'], $this->starts($type), 'Sipho is free');
        $this->book($type, '2026-11-30 07:00:00', $b);
        $this->assertSame([], $this->starts($type));
        $this->assertSame(Availability::FULL, $this->dayStatus($type));
    }

    // ---------------------------------------------------------------------------------------------------------------- time zones

    public function test_a_customer_in_another_zone_gets_the_businesss_day_and_the_same_instants(): void
    {
        $type = $this->type($this->team());
        $this->hours(2, '09:00', '11:00'); // Tuesday

        // 22:30Z on Monday 30 November is 00:30 on Tuesday 1 December in Johannesburg.
        $slots = app(Availability::class)->slots($type, CarbonImmutable::parse('2026-11-30 22:30:00', 'UTC'), null, $this->now());

        $this->assertSame(['2026-12-01 07:00:00', '2026-12-01 08:00:00'], $slots->map(fn ($s) => $s->start->format('Y-m-d H:i:s'))->all());
        $this->assertSame('UTC', $slots->first()->start->timezoneName);
    }

    public function test_a_zone_behind_utc_keeps_the_local_evening_on_its_own_day(): void
    {
        $type = $this->type($this->team('America/New_York'));
        $this->hours(1, '18:00', '21:00'); // Monday evening, UTC-5 in November

        // 18:00, 19:00 and 20:00 local are 23:00 Monday, 00:00 Tuesday and 01:00 Tuesday UTC, and all belong to Monday.
        $slots = app(Availability::class)->slots($type, self::MONDAY, null, $this->now());
        $this->assertSame(['2026-11-30 23:00', '2026-12-01 00:00', '2026-12-01 01:00'], $slots->map(fn ($s) => $s->start->format('Y-m-d H:i'))->all());
        $this->assertSame([], app(Availability::class)->slots($type, '2026-12-01', null, $this->now())->all(), 'Tuesday local has no hours');
    }

    public function test_the_day_clocks_go_forward_has_one_hour_fewer(): void
    {
        $type = $this->type($this->team('Europe/London'));
        $this->hours(7, '00:00', '04:00'); // Sunday 28 March 2027: at 01:00 the clocks jump to 02:00

        $slots = app(Availability::class)->slots($type, '2027-03-28', null, $this->now());

        $this->assertSame(['2027-03-28 00:00', '2027-03-28 01:00', '2027-03-28 02:00'], $slots->map(fn ($s) => $s->start->format('Y-m-d H:i'))->all(), 'three real hours between 00:00 and 04:00 local');
    }

    public function test_the_day_clocks_go_back_has_one_hour_more(): void
    {
        $type = $this->type($this->team('Europe/London'));
        $this->hours(7, '00:00', '04:00'); // Sunday 25 October 2026: at 02:00 the clocks go back to 01:00

        $slots = app(Availability::class)->slots($type, '2026-10-25', null, $this->now('2026-10-01 08:00:00'));

        $this->assertCount(5, $slots, 'five real hours between 00:00 and 04:00 local');
        $this->assertSame('2026-10-24 23:00', $slots->first()->start->format('Y-m-d H:i'));
    }

    // ---------------------------------------------------------------------------------------------------------------- find, isolation, months

    public function test_find_answers_for_one_exact_start_and_refuses_everything_that_is_not_bookable(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $this->book($type, '2026-11-30 08:00:00'); // 10:00 local
        $engine = app(Availability::class);
        $at = fn (string $z) => CarbonImmutable::parse($z, 'UTC');

        $this->assertNotNull($engine->find($type, $at('2026-11-30 07:00:00'), null, $this->now()));
        $this->assertNull($engine->find($type, $at('2026-11-30 07:30:00'), null, $this->now()), 'not on the grid');
        $this->assertNull($engine->find($type, $at('2026-11-30 08:00:00'), null, $this->now()), 'held');
        $this->assertNull($engine->find($type, $at('2026-11-30 06:00:00'), null, $this->now()), 'before opening');
        $this->assertNull($engine->find($type, $at('2026-11-30 10:00:00'), null, $this->now()), 'after closing');
        $this->assertNull($engine->find($type, $at('2026-11-30 07:00:00'), null, $this->now('2026-11-30 07:00:00')), 'already started');
    }

    public function test_find_lets_a_booking_move_into_its_own_slot(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $mine = $this->book($type, '2026-11-30 08:00:00');
        $engine = app(Availability::class);
        $slot = CarbonImmutable::parse('2026-11-30 08:00:00', 'UTC');

        $this->assertNull($engine->find($type, $slot, null, $this->now()));
        $this->assertNotNull($engine->find($type, $slot, null, $this->now(), $mine->id));
    }

    public function test_another_businesss_hours_closures_and_bookings_never_affect_this_one(): void
    {
        $mine = $this->team();
        $type = $this->type($mine);
        $this->hours(1, '09:00', '12:00', null, $mine);

        $other = $this->team();
        $otherType = $this->type($other);
        $this->hours(1, '09:00', '12:00', null, $other);
        $this->closure('2026-11-30 00:00:00', '2026-12-01 00:00:00', null, $other);
        $this->book($otherType, '2026-11-30 07:00:00', null, Booking::CONFIRMED, $other);
        $this->book($otherType, '2026-11-30 08:00:00', null, Booking::CONFIRMED, $other);

        $this->assertSame(['07:00', '08:00', '09:00'], $this->starts($type));
        $this->assertSame([], $this->starts($otherType));
    }

    public function test_a_providers_link_from_another_business_is_never_used(): void
    {
        $mine = $this->team();
        $type = $this->type($mine);
        $this->hours(1, '09:00', '11:00', null, $mine);
        $other = $this->team();
        $foreign = $this->provider('Someone else', $other);
        $type->providers()->attach($foreign->id);

        $this->assertSame([], $this->starts($type), 'a provider that does not belong to this business is not a resource');
    }

    public function test_the_month_view_gives_each_date_one_status_from_one_load(): void
    {
        $type = $this->type($this->team('Africa/Johannesburg', ['horizon_days' => 60]));
        foreach ([1, 2, 3, 4, 5] as $day) {
            $this->hours($day, '09:00', '11:00');
        }
        $this->closure('2026-12-02 00:00:00', '2026-12-02 22:00:00'); // Wednesday 2 December, all day local
        $this->book($type, '2026-12-03 07:00:00');
        $this->book($type, '2026-12-03 08:00:00');                       // Thursday 3 December is full

        $month = app(Availability::class)->monthStatuses($type, 2026, 12, null, $this->now('2026-11-25 08:00:00'));

        $this->assertCount(31, $month);
        $this->assertSame(Availability::AVAILABLE, $month['2026-12-01']);
        $this->assertSame(Availability::UNAVAILABLE, $month['2026-12-02']);
        $this->assertSame(Availability::FULL, $month['2026-12-03']);
        $this->assertSame(Availability::NOT_OFFERED, $month['2026-12-06'], 'a Sunday');
        $this->assertSame(Availability::AVAILABLE, $month['2026-12-04']);
    }

    public function test_the_month_view_marks_the_past_and_the_far_future_as_outside_the_window(): void
    {
        $type = $this->type($this->team('Africa/Johannesburg', ['horizon_days' => 10]));
        foreach ([1, 2, 3, 4, 5, 6, 7] as $day) {
            $this->hours($day, '09:00', '11:00');
        }

        $month = app(Availability::class)->monthStatuses($type, 2026, 11, null, $this->now('2026-11-20 06:00:00')); // 08:00 local

        $this->assertSame(Availability::OUTSIDE_WINDOW, $month['2026-11-19']);
        $this->assertSame(Availability::AVAILABLE, $month['2026-11-20']);
        $this->assertSame(Availability::AVAILABLE, $month['2026-11-30'], 'today plus 10 is the last day');
        $this->assertSame(Availability::OUTSIDE_WINDOW, app(Availability::class)->dayStatus($type, '2026-12-01', null, $this->now('2026-11-20 06:00:00')));
    }

    public function test_the_statuses_carry_no_customer_information(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '10:00');
        $booking = $this->book($type, '2026-11-30 07:00:00');
        $booking->update(['customer_name' => 'Zodwa Secret', 'customer_email' => 'zodwa@private.test']);

        $month = app(Availability::class)->monthStatuses($type, 2026, 11, null, $this->now());

        $this->assertStringNotContainsString('Zodwa', json_encode($month));
        $this->assertStringNotContainsString('private.test', json_encode($month));
        $this->assertSame(Availability::FULL, $month['2026-11-30']);
        $this->assertContains($month['2026-11-29'], [Availability::NOT_OFFERED, Availability::OUTSIDE_WINDOW]);
    }

    public function test_a_type_follows_the_business_approval_setting_unless_it_says_otherwise(): void
    {
        $team = $this->team('Africa/Johannesburg', ['approval_mode' => 'approval']);
        $follows = $this->type($team);
        $auto = $this->type($team, ['name' => 'Quick call', 'requires_approval' => false]);
        $setting = BookingSetting::forTeam($team);

        $this->assertTrue($follows->needsApproval($setting));
        $this->assertFalse($auto->needsApproval($setting));

        $setting->approval_mode = 'auto';
        $this->assertFalse($follows->needsApproval($setting));
        $this->assertTrue($this->type($team, ['name' => 'Assessment', 'requires_approval' => true])->needsApproval($setting));
    }

    public function test_a_month_view_reads_the_schedule_once_however_many_days_it_has(): void
    {
        $type = $this->type($this->team());
        foreach ([1, 2, 3, 4, 5] as $day) {
            $this->hours($day, '09:00', '17:00');
        }
        $type->providers()->attach([$this->provider('A')->id, $this->provider('B')->id]);
        $engine = app(Availability::class);
        $engine->monthStatuses($type, 2026, 12, null, $this->now()); // warm any lazy boot work

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $engine->monthStatuses($type, 2026, 12, null, $this->now());

        $this->assertLessThanOrEqual(12, $queries, 'one load for 31 days, not one per day');
    }

    public function test_deleting_a_business_removes_its_whole_schedule(): void
    {
        $type = $this->type($this->team());
        $this->hours(1, '09:00', '12:00');
        $provider = $this->provider();
        $type->providers()->attach($provider->id);
        $this->closure('2026-11-30 00:00:00', '2026-11-30 06:00:00');
        $this->book($type, '2026-11-30 08:00:00', $provider);

        $this->team->delete();

        $this->assertSame([0, 0, 0, 0, 0, 0], [AppointmentType::count(), BookingProvider::count(), WorkingHour::count(), BookingClosure::count(), Booking::count(), DB::table('appointment_type_provider')->count()]);
    }
}
