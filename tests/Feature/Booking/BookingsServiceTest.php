<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\PlatformFeature;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingRefused;
use App\Services\Booking\Bookings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-63 phase 3: making and withdrawing a booking. "Now" is 20 November 2026, 10:00 in Johannesburg; the working day used is Monday 30 November,
 * 09:00 to 17:00 local (07:00Z to 15:00Z). The row lock itself cannot be shown on SQLite; what is shown is that every decision is taken again
 * inside the transaction, so a time taken after the page was drawn is refused.
 */
class BookingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private AppointmentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        PlatformFeature::where('platform_key', 'infodot')->where('key', 'bookings')->update(['state' => 'available']);

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $this->team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Dlamini Freight']);
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'auto', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 0, 'horizon_days' => 365]);
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->type = $this->team->appointmentTypes()->create(['name' => 'Consultation', 'duration_minutes' => 60, 'slot_interval_minutes' => 60, 'buffer_after_minutes' => 15]);
    }

    private function customer(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Secret']);
    }

    private function at(string $z): CarbonImmutable
    {
        return CarbonImmutable::parse($z, 'UTC');
    }

    /** @return array{name: string, email: string, phone: string, company: string, reason: string} */
    private function details(array $over = []): array
    {
        return $over + ['name' => 'Zodwa Secret', 'email' => 'zodwa@example.test', 'phone' => '082 000 0000', 'company' => 'Secret Traders', 'reason' => 'A private matter'];
    }

    private function book(?User $who = null, string $start = '2026-11-30 08:00:00', ?int $provider = null, ?AppointmentType $type = null): Booking
    {
        return app(Bookings::class)->book($who ?? $this->customer(), $this->team, $type ?? $this->type, $this->at($start), $provider, $this->details());
    }

    private function refusal(callable $do): BookingRefused
    {
        try {
            $do();
        } catch (BookingRefused $e) {
            return $e;
        }

        $this->fail('expected the booking to be refused');
    }

    // ------------------------------------------------------------------------------------------------------------------- creating

    public function test_a_free_time_is_booked_confirmed_with_the_buffer_held_and_a_reference(): void
    {
        $booking = $this->book();

        $this->assertSame(Booking::CONFIRMED, $booking->status);
        $this->assertSame('2026-11-30 08:00:00', $booking->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-30 09:00:00', $booking->ends_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-30 09:15:00', $booking->blocks_until->utc()->format('Y-m-d H:i:s'), 'the 15 minute buffer after is held');
        $this->assertSame(sprintf('%s-B-%06d', $this->team->code, $booking->id), $booking->reference);
        $this->assertNull($booking->booking_provider_id);
        $this->assertSame('Secret Traders', $booking->customer_company);
    }

    public function test_a_booking_waits_for_approval_when_the_business_or_the_type_says_so(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => 'approval']);
        $this->assertSame(Booking::PENDING, $this->book()->status);

        $auto = $this->team->appointmentTypes()->create(['name' => 'Quick call', 'duration_minutes' => 30, 'slot_interval_minutes' => 30, 'requires_approval' => false]);
        $this->assertSame(Booking::CONFIRMED, $this->book(null, '2026-11-30 12:00:00', null, $auto)->status);

        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => 'auto']);
        $strict = $this->team->appointmentTypes()->create(['name' => 'Assessment', 'duration_minutes' => 30, 'slot_interval_minutes' => 30, 'requires_approval' => true]);
        $this->assertSame(Booking::PENDING, $this->book(null, '2026-11-30 13:00:00', null, $strict)->status);
    }

    public function test_the_second_person_for_the_same_time_is_refused_and_nothing_is_written(): void
    {
        $this->book();
        $before = [Booking::count(), BookingEvent::count()];

        $e = $this->refusal(fn () => $this->book());

        $this->assertSame(BookingRefused::SLOT_TAKEN, $e->reason);
        $this->assertSame($before, [Booking::count(), BookingEvent::count()]);
    }

    public function test_a_pending_booking_holds_the_time_and_the_buffer_blocks_the_next_start(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => 'approval']);
        $this->book(null, '2026-11-30 08:00:00'); // 10:00 to 11:00 local, holding to 11:15

        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 08:00:00'))->reason);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 09:00:00'))->reason, '11:00 local starts inside the buffer');
        $this->assertSame(Booking::PENDING, $this->book(null, '2026-11-30 10:00:00')->status);
    }

    public function test_a_provider_pool_hands_out_the_lowest_provider_first_and_then_the_next(): void
    {
        $a = $this->team->bookingProviders()->create(['user_id' => User::factory()->create()->id, 'public_name' => 'Thandi']);
        $b = $this->team->bookingProviders()->create(['user_id' => User::factory()->create()->id, 'public_name' => 'Sipho']);
        $this->type->providers()->attach([$a->id, $b->id]);

        $first = $this->book();
        $second = $this->book();

        $this->assertSame([$a->id, $b->id], [$first->booking_provider_id, $second->booking_provider_id]);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book())->reason, 'both are busy now');
    }

    public function test_a_chosen_provider_who_is_busy_is_refused_even_when_another_is_free(): void
    {
        $a = $this->team->bookingProviders()->create(['user_id' => User::factory()->create()->id, 'public_name' => 'Thandi']);
        $b = $this->team->bookingProviders()->create(['user_id' => User::factory()->create()->id, 'public_name' => 'Sipho']);
        $this->type->providers()->attach([$a->id, $b->id]);
        $this->book(null, '2026-11-30 08:00:00', $a->id);

        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 08:00:00', $a->id))->reason);
        $this->assertSame($b->id, $this->book(null, '2026-11-30 08:00:00', $b->id)->booking_provider_id);
    }

    public function test_times_the_business_does_not_offer_are_refused(): void
    {
        foreach ([
            '2026-11-30 06:00:00' => 'before opening',
            '2026-11-30 15:00:00' => 'after closing',
            '2026-11-30 08:30:00' => 'not on the slot grid',
            '2026-12-01 08:00:00' => 'a Tuesday with no hours',
            '2026-11-16 08:00:00' => 'in the past',
        ] as $start => $why) {
            $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, $start))->reason, $why);
        }
        $this->assertSame(0, Booking::count());
    }

    public function test_a_closure_the_notice_and_the_horizon_are_enforced_inside_the_booking(): void
    {
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'kind' => 'block', 'starts_at' => '2026-11-30 07:00:00', 'ends_at' => '2026-11-30 09:00:00']);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 08:00:00'))->reason, 'blocked');
        $this->assertNotNull($this->book(null, '2026-11-30 09:00:00'));

        BookingSetting::where('team_id', $this->team->id)->update(['min_notice_minutes' => 60 * 24 * 12]); // twelve days: 2 December is the first possible Monday... 30 Nov is 10 days out
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 10:00:00'))->reason, 'too soon');

        BookingSetting::where('team_id', $this->team->id)->update(['min_notice_minutes' => 0, 'horizon_days' => 5]);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 10:00:00'))->reason, 'beyond the furthest-ahead date');
    }

    public function test_a_type_of_another_business_or_a_switched_off_type_cannot_be_booked(): void
    {
        $other = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        $foreign = $other->appointmentTypes()->create(['name' => 'Theirs', 'duration_minutes' => 60, 'slot_interval_minutes' => 60]);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book(null, '2026-11-30 08:00:00', null, $foreign))->reason);

        $this->type->update(['active' => false]);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->book())->reason);
        $this->assertSame(0, Booking::count());
    }

    // ------------------------------------------------------------------------------------------------------------------- who and when

    public function test_nothing_can_be_booked_while_the_feature_is_closed_or_bookings_are_off_or_hidden(): void
    {
        PlatformFeature::where('key', 'bookings')->update(['state' => 'disabled']);
        $this->assertSame(BookingRefused::CLOSED, $this->refusal(fn () => $this->book())->reason);
        PlatformFeature::where('key', 'bookings')->update(['state' => 'available']);

        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => false]);
        $this->assertSame(BookingRefused::CLOSED, $this->refusal(fn () => $this->book())->reason);

        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => true, 'calendar_published' => false]);
        $this->assertSame(BookingRefused::CLOSED, $this->refusal(fn () => $this->book())->reason);
        $this->assertSame(0, Booking::count());
    }

    public function test_a_business_with_no_settings_row_takes_no_bookings(): void
    {
        BookingSetting::where('team_id', $this->team->id)->delete();

        $this->assertSame(BookingRefused::CLOSED, $this->refusal(fn () => $this->book())->reason);
    }

    public function test_a_member_of_the_business_cannot_book_it_and_a_personal_workspace_takes_no_bookings(): void
    {
        $member = $this->customer();
        $this->team->users()->attach($member, ['role' => 'member']);
        $this->assertSame(BookingRefused::CLOSED, $this->refusal(fn () => $this->book($member->fresh()))->reason);
        $this->assertSame(BookingRefused::CLOSED, $this->refusal(fn () => $this->book($this->team->owner))->reason);

        $personal = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => true]);
        $this->assertNotNull(app(Bookings::class)->whyNot($this->customer(), $personal));
    }

    public function test_why_not_is_null_for_a_stranger_and_a_guest_may_look(): void
    {
        $this->assertNull(app(Bookings::class)->whyNot($this->customer(), $this->team));
        $this->assertNull(app(Bookings::class)->whyNot(null, $this->team));
    }

    // ------------------------------------------------------------------------------------------------------------------- limits

    public function test_one_person_cannot_hold_more_than_the_cap_with_one_business(): void
    {
        config(['booking.limits.active_per_business' => 2, 'booking.limits.new_per_business_day' => 10]);
        $who = $this->customer();
        $this->book($who, '2026-11-30 07:00:00');
        $this->book($who, '2026-11-30 09:00:00');

        $this->assertSame(BookingRefused::LIMIT, $this->refusal(fn () => $this->book($who, '2026-11-30 11:00:00'))->reason);
        $this->assertSame(2, Booking::count());
        $this->assertNotNull($this->book(null, '2026-11-30 11:00:00'), 'someone else is not limited by it');
    }

    public function test_the_same_person_cannot_book_the_same_time_twice(): void
    {
        $who = $this->customer();
        $this->book($who);

        // The slot is taken, so it is refused either way; the duplicate rule is what answers first when it is theirs.
        $this->assertSame(BookingRefused::LIMIT, $this->refusal(fn () => $this->book($who))->reason);
    }

    public function test_there_is_a_daily_cap_with_one_business_and_overall(): void
    {
        config(['booking.limits.active_per_business' => 20, 'booking.limits.new_per_business_day' => 2, 'booking.limits.new_per_day' => 3]);
        $who = $this->customer();
        $this->book($who, '2026-11-30 07:00:00');
        $this->book($who, '2026-11-30 09:00:00');
        $this->assertSame(BookingRefused::LIMIT, $this->refusal(fn () => $this->book($who, '2026-11-30 11:00:00'))->reason, 'two a day with one business');

        $other = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        BookingSetting::forceCreate(['team_id' => $other->id, 'enabled' => true, 'timezone' => 'UTC', 'min_notice_minutes' => 0, 'horizon_days' => 365]);
        WorkingHour::forceCreate(['team_id' => $other->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $otherType = $other->appointmentTypes()->create(['name' => 'Chat', 'duration_minutes' => 60, 'slot_interval_minutes' => 60]);
        app(Bookings::class)->book($who, $other, $otherType, $this->at('2026-11-30 10:00:00'), null, $this->details());

        $this->assertSame(BookingRefused::LIMIT, $this->refusal(fn () => app(Bookings::class)->book($who, $other, $otherType, $this->at('2026-11-30 11:00:00'), null, $this->details()))->reason, 'three a day overall');
    }

    // ------------------------------------------------------------------------------------------------------------------- audit and privacy

    public function test_the_audit_entry_names_the_booking_and_holds_no_customer_details(): void
    {
        $booking = $this->book();

        $event = BookingEvent::where('type', 'booking_created')->sole();
        $this->assertSame($booking->id, $event->details['booking_id']);
        $this->assertSame($booking->customer_user_id, $event->actor_id);

        $dump = json_encode(BookingEvent::all()->toArray());
        foreach (['Zodwa', 'zodwa@example.test', 'Secret Traders', 'private matter', '082 000'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    // ------------------------------------------------------------------------------------------------------------------- cancelling

    public function test_the_customer_can_cancel_and_the_time_is_free_again(): void
    {
        $who = $this->customer();
        $booking = $this->book($who);

        app(Bookings::class)->cancelByCustomer($booking, $who);

        $this->assertSame(Booking::CANCELLED_BY_CUSTOMER, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->cancelled_at);
        $this->assertNotNull(app(Availability::class)->find($this->type, $this->at('2026-11-30 08:00:00'), null, now()));
        $this->assertSame(['booking_created', 'booking_cancelled_by_customer'], BookingEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_a_confirmed_appointment_cannot_be_cancelled_inside_the_cut_off_but_a_pending_one_can(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['cancel_cutoff_hours' => 24]);
        $who = $this->customer();
        $confirmed = $this->book($who, '2026-11-30 08:00:00');
        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => 'approval']);
        $pending = $this->book($who, '2026-11-30 10:00:00');

        $this->travelTo(CarbonImmutable::parse('2026-11-29 12:00:00', 'UTC')); // 20 hours before the first appointment

        $this->assertSame(BookingRefused::TOO_LATE, $this->refusal(fn () => app(Bookings::class)->cancelByCustomer($confirmed, $who))->reason);
        $this->assertSame(Booking::CONFIRMED, $confirmed->fresh()->status);
        app(Bookings::class)->cancelByCustomer($pending, $who);
        $this->assertSame(Booking::CANCELLED_BY_CUSTOMER, $pending->fresh()->status);
    }

    public function test_only_the_customer_can_cancel_and_only_once_and_never_after_it_started(): void
    {
        $who = $this->customer();
        $booking = $this->book($who);

        $this->assertSame(BookingRefused::NOT_YOURS, $this->refusal(fn () => app(Bookings::class)->cancelByCustomer($booking, $this->customer()))->reason);
        $this->assertSame(BookingRefused::NOT_YOURS, $this->refusal(fn () => app(Bookings::class)->cancelByCustomer($booking, $this->team->owner))->reason);
        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);

        app(Bookings::class)->cancelByCustomer($booking, $who);
        $this->assertNotNull($this->refusal(fn () => app(Bookings::class)->cancelByCustomer($booking, $who)));

        $again = $this->book($who, '2026-11-30 10:00:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-30 10:30:00', 'UTC'));
        $this->assertNotNull($this->refusal(fn () => app(Bookings::class)->cancelByCustomer($again, $who)));
    }

    public function test_a_booking_with_no_customer_account_cannot_be_cancelled_by_anyone_through_this_door(): void
    {
        $booking = $this->book();
        $booking->forceFill(['customer_user_id' => null])->save();

        $this->assertSame(BookingRefused::NOT_YOURS, $this->refusal(fn () => app(Bookings::class)->cancelByCustomer($booking, $this->customer()))->reason);
    }
}
