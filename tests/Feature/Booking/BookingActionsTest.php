<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingActions;
use App\Services\Booking\BookingRefused;
use App\Services\Booking\Bookings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-63 phase 4: what a business does with a booking. "Now" is 20 November 2026, 10:00 Johannesburg; Mondays are open 09:00 to 17:00 (07:00Z to 15:00Z).
 */
class BookingActionsTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private User $owner;

    private AppointmentType $type;

    private const MON = '2026-11-30';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        [$this->team, $this->owner] = $this->business('available');
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'approval', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 60 * 24, 'horizon_days' => 365, 'cancel_cutoff_hours' => 24]);
        foreach ([1, 2] as $weekday) {
            WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        }
        $this->type = $this->type($this->team, ['buffer_after_minutes' => 15]);
    }

    private function customer(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Secret']);
    }

    private function at(string $z): CarbonImmutable
    {
        return CarbonImmutable::parse($z, 'UTC');
    }

    private function book(string $start = '2026-11-30 08:00:00', ?User $who = null, ?AppointmentType $type = null, string $mode = 'approval'): Booking
    {
        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => $mode]);

        return app(Bookings::class)->book($who ?? $this->customer(), $this->team, $type ?? $this->type, $this->at($start), null, ['name' => 'Zodwa Secret', 'email' => 'zodwa@example.test', 'phone' => '082 000 0000', 'company' => 'Secret Traders', 'reason' => 'A private matter']);
    }

    private function actions(): BookingActions
    {
        return app(BookingActions::class);
    }

    private function refusal(callable $do): BookingRefused
    {
        try {
            $do();
        } catch (BookingRefused $e) {
            return $e;
        }

        $this->fail('expected a refusal');
    }

    private function free(string $start, ?int $provider = null, bool $ignoreNotice = true): bool
    {
        return app(Availability::class)->find($this->type, $this->at($start), $provider, now(), null, false, $ignoreNotice) !== null;
    }

    // ------------------------------------------------------------------------------------------------------------------- who may act

    public function test_owner_administrator_and_manager_may_act_and_a_plain_member_may_not(): void
    {
        foreach ([$this->owner, $this->member($this->team, 'admin'), $this->member($this->team, 'manager')] as $person) {
            $booking = $this->book('2026-11-30 0'.random_int(7, 7).':00:00', null, null);
            $this->actions()->accept($booking, $person);
            $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
            $this->actions()->cancel($booking->fresh(), $person);
        }

        $plain = $this->member($this->team, 'member');
        $booking = $this->book();
        $this->assertSame(BookingRefused::NOT_ALLOWED, $this->refusal(fn () => $this->actions()->accept($booking, $plain))->reason);
        $this->assertSame(BookingRefused::NOT_ALLOWED, $this->refusal(fn () => $this->actions()->note($booking, $plain, 'x'))->reason);
        $this->assertSame(Booking::PENDING, $booking->fresh()->status);
    }

    public function test_a_provider_acts_only_on_the_bookings_assigned_to_them_and_cannot_hand_one_over(): void
    {
        $thandi = $this->member($this->team, 'member', 'Thandi');
        $sipho = $this->member($this->team, 'member', 'Sipho');
        $a = $this->provider($this->team, $thandi, 'Thandi');
        $b = $this->provider($this->team, $sipho, 'Sipho');
        $this->type->providers()->attach([$a->id, $b->id]);
        $first = $this->book('2026-11-30 08:00:00');   // goes to the lowest provider id: Thandi
        $second = $this->book('2026-11-30 08:00:00');  // Sipho

        $this->assertSame([$a->id, $b->id], [$first->booking_provider_id, $second->booking_provider_id]);
        $this->actions()->accept($first, $thandi);
        $this->assertSame(BookingRefused::NOT_ALLOWED, $this->refusal(fn () => $this->actions()->accept($second, $thandi))->reason, "not Sipho's");
        $this->assertSame(BookingRefused::NOT_ALLOWED, $this->refusal(fn () => $this->actions()->assign($first->fresh(), $thandi, $b->id))->reason, 'only managers hand over');
        $this->actions()->note($first->fresh(), $thandi, 'Bring the file.');
        $this->assertSame('Bring the file.', $first->fresh()->internal_notes);
    }

    public function test_another_businesss_manager_and_a_stranger_cannot_act(): void
    {
        $booking = $this->book();
        [$other, $otherOwner] = $this->business('available', 'Kestrel Mining');

        foreach ([$otherOwner, $this->customer(), $this->member($other, 'admin')] as $person) {
            $this->assertSame(BookingRefused::NOT_ALLOWED, $this->refusal(fn () => $this->actions()->accept($booking, $person))->reason);
            $this->assertSame(BookingRefused::NOT_ALLOWED, $this->refusal(fn () => $this->actions()->cancel($booking, $person))->reason);
        }
        $this->assertSame(Booking::PENDING, $booking->fresh()->status);
    }

    // ------------------------------------------------------------------------------------------------------------------- accept, reject, cancel

    public function test_a_pending_request_is_accepted_once_and_only_while_it_is_still_ahead(): void
    {
        $booking = $this->book();
        $this->actions()->accept($booking, $this->owner);

        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->accept($booking->fresh(), $this->owner))->reason);

        $old = $this->book('2026-11-30 10:00:00');
        $this->travelTo($this->at('2026-11-30 10:30:00'));
        $this->assertSame(BookingRefused::TOO_LATE, $this->refusal(fn () => $this->actions()->accept($old, $this->owner))->reason);
    }

    public function test_a_rejection_keeps_the_reason_for_the_customer_and_frees_the_time(): void
    {
        $booking = $this->book();
        $this->assertFalse($this->free('2026-11-30 08:00:00'));

        $this->actions()->reject($booking, $this->owner, '  We are closed that week.  ');

        $this->assertSame([Booking::REJECTED, 'We are closed that week.'], [$booking->fresh()->status, $booking->fresh()->status_note]);
        $this->assertTrue($this->free('2026-11-30 08:00:00'));
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->reject($booking->fresh(), $this->owner))->reason);

        $confirmed = $this->book('2026-11-30 10:00:00', null, null, 'auto');
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->reject($confirmed, $this->owner))->reason, 'a confirmed one is cancelled, not rejected');
    }

    public function test_a_business_cancels_a_pending_or_confirmed_appointment_and_the_note_is_capped(): void
    {
        $pending = $this->book('2026-11-30 07:00:00');
        $confirmed = $this->book('2026-11-30 09:00:00', null, null, 'auto');

        $this->actions()->cancel($pending, $this->owner, str_repeat('x', 400));
        $this->actions()->cancel($confirmed, $this->owner);

        foreach ([$pending->fresh(), $confirmed->fresh()] as $b) {
            $this->assertSame(Booking::CANCELLED_BY_BUSINESS, $b->status);
            $this->assertNotNull($b->cancelled_at);
        }
        $this->assertSame(300, mb_strlen($pending->fresh()->status_note));
        $this->assertTrue($this->free('2026-11-30 07:00:00') && $this->free('2026-11-30 09:00:00'));
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->cancel($pending->fresh(), $this->owner))->reason);
    }

    public function test_a_started_appointment_cannot_be_cancelled_or_moved_but_can_be_marked(): void
    {
        $booking = $this->book('2026-11-30 08:00:00', null, null, 'auto');
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->complete($booking, $this->owner))->reason, 'it has not started');

        $this->travelTo($this->at('2026-11-30 08:30:00'));
        $this->assertSame(BookingRefused::TOO_LATE, $this->refusal(fn () => $this->actions()->cancel($booking, $this->owner))->reason);
        $this->assertSame(BookingRefused::TOO_LATE, $this->refusal(fn () => $this->actions()->reschedule($booking, $this->owner, $this->at('2026-12-01 08:00:00')))->reason);

        $this->actions()->complete($booking, $this->owner);
        $this->assertSame(Booking::COMPLETED, $booking->fresh()->status);
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->noShow($booking->fresh(), $this->owner))->reason, 'final');
    }

    public function test_a_missed_appointment_is_marked_a_no_show_and_a_pending_one_cannot_be_marked(): void
    {
        $confirmed = $this->book('2026-11-30 08:00:00', null, null, 'auto');
        $pending = $this->book('2026-11-30 10:00:00', null, null, 'approval');
        $this->travelTo($this->at('2026-11-30 12:00:00'));

        $this->actions()->noShow($confirmed, $this->owner);
        $this->assertSame(Booking::NO_SHOW, $confirmed->fresh()->status);
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->complete($pending, $this->owner))->reason);
    }

    // ------------------------------------------------------------------------------------------------------------------- reschedule

    public function test_a_booking_moves_to_a_free_time_keeps_its_status_and_frees_the_old_one(): void
    {
        $booking = $this->book('2026-11-30 08:00:00', null, null, 'auto');
        $this->assertFalse($this->free('2026-11-30 08:00:00'));

        $this->actions()->reschedule($booking, $this->owner, $this->at('2026-12-01 09:00:00'));

        $moved = $booking->fresh();
        $this->assertSame([Booking::CONFIRMED, '2026-12-01 09:00:00', '2026-12-01 10:00:00', '2026-12-01 10:15:00'], [$moved->status, $moved->starts_at->utc()->format('Y-m-d H:i:s'), $moved->ends_at->utc()->format('Y-m-d H:i:s'), $moved->blocks_until->utc()->format('Y-m-d H:i:s')]);
        $this->assertTrue($this->free('2026-11-30 08:00:00'));
        $this->assertFalse($this->free('2026-12-01 09:00:00'));
        $event = BookingEvent::where('type', 'booking_rescheduled')->sole();
        $this->assertSame(['2026-11-30T08:00:00+00:00', '2026-12-01T09:00:00+00:00'], [$event->details['from'], $event->details['to']]);
    }

    public function test_a_booking_may_move_into_an_overlapping_time_of_its_own(): void
    {
        $type = $this->type($this->team, ['name' => 'Long', 'duration_minutes' => 120, 'slot_interval_minutes' => 60]);
        $booking = $this->book('2026-11-30 07:00:00', null, $type, 'auto'); // 09:00 to 11:00 local

        $this->actions()->reschedule($booking, $this->owner, $this->at('2026-11-30 08:00:00')); // 10:00 to 12:00 overlaps its old self

        $this->assertSame('2026-11-30 08:00:00', $booking->fresh()->starts_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_move_is_refused_for_a_taken_blocked_or_closed_time_and_ignores_the_least_notice(): void
    {
        $booking = $this->book('2026-11-30 08:00:00', null, null, 'auto');
        $this->book('2026-12-01 08:00:00', null, null, 'auto');
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'kind' => 'block', 'starts_at' => '2026-12-01 09:00:00', 'ends_at' => '2026-12-01 10:00:00']);

        foreach (['2026-12-01 08:00:00' => 'taken', '2026-12-01 09:00:00' => 'blocked', '2026-12-01 05:00:00' => 'before opening', '2026-12-02 08:00:00' => 'a Wednesday with no hours', '2026-12-01 08:30:00' => 'off the grid'] as $start => $why) {
            $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->actions()->reschedule($booking, $this->owner, $this->at($start)))->reason, $why);
        }

        // Tomorrow-ish is inside the 24 hour notice a customer must give, but not for the business's own move.
        $this->travelTo($this->at('2026-11-30 05:00:00'));
        $this->actions()->reschedule($booking, $this->owner, $this->at('2026-12-01 10:00:00'));
        $this->assertSame('2026-12-01 10:00:00', $booking->fresh()->starts_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_a_move_keeps_the_provider_and_is_refused_when_that_provider_is_busy(): void
    {
        $a = $this->provider($this->team, $this->member($this->team, 'member', 'Thandi'), 'Thandi');
        $b = $this->provider($this->team, $this->member($this->team, 'member', 'Sipho'), 'Sipho');
        $this->type->providers()->attach([$a->id, $b->id]);
        $first = $this->book('2026-11-30 08:00:00', null, null, 'auto');   // Thandi
        $this->book('2026-12-01 08:00:00', null, null, 'auto');            // Thandi, Tuesday
        $this->book('2026-12-01 08:00:00', null, null, 'auto');            // Sipho, Tuesday

        $this->assertSame($a->id, $first->booking_provider_id);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->actions()->reschedule($first, $this->owner, $this->at('2026-12-01 08:00:00')))->reason, 'both are busy');

        // 09:00Z would start inside Thandi's 15 minute buffer after her 08:00Z booking; 10:00Z is clear.
        $this->actions()->reschedule($first, $this->owner, $this->at('2026-12-01 10:00:00'));
        $this->assertSame([$a->id, '2026-12-01 10:00:00'], [$first->fresh()->booking_provider_id, $first->fresh()->starts_at->utc()->format('Y-m-d H:i:s')]);
    }

    public function test_a_move_answers_the_customers_request_and_clears_it(): void
    {
        $customer = $this->customer();
        $booking = $this->book('2026-12-01 08:00:00', $customer, null, 'auto');

        app(Bookings::class)->requestReschedule($booking, $customer, 'Mornings are better for me.');
        $this->assertNotNull($booking->fresh()->reschedule_requested_at);

        $this->actions()->reschedule($booking, $this->owner, $this->at('2026-12-01 07:00:00'));

        $this->assertSame([null, null], [$booking->fresh()->reschedule_requested_at, $booking->fresh()->reschedule_note]);
    }

    // ------------------------------------------------------------------------------------------------------------------- assign

    public function test_a_manager_hands_a_booking_to_a_free_provider_who_offers_the_type(): void
    {
        $a = $this->provider($this->team, $this->member($this->team, 'member', 'Thandi'), 'Thandi');
        $b = $this->provider($this->team, $this->member($this->team, 'member', 'Sipho'), 'Sipho');
        $c = $this->provider($this->team, $this->member($this->team, 'member', 'Naledi'), 'Naledi');
        $this->type->providers()->attach([$a->id, $b->id]); // Naledi does not offer it
        $booking = $this->book('2026-11-30 08:00:00', null, null, 'auto');

        $this->actions()->assign($booking, $this->owner, $b->id);
        $this->assertSame($b->id, $booking->fresh()->booking_provider_id);
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->actions()->assign($booking->fresh(), $this->owner, $c->id))->reason, 'not one who offers it');

        $this->book('2026-11-30 08:00:00', null, null, 'auto'); // Thandi is now busy then
        $this->assertSame(BookingRefused::SLOT_TAKEN, $this->refusal(fn () => $this->actions()->assign($booking->fresh(), $this->owner, $a->id))->reason, 'busy then');
        $event = BookingEvent::where('type', 'booking_provider_assigned')->sole();
        $this->assertSame([$a->id, $b->id], [$event->details['from_provider'], $event->details['to_provider']]);
    }

    // ------------------------------------------------------------------------------------------------------------------- notes and the audit trail

    public function test_notes_are_private_capped_and_allowed_in_any_status_and_never_written_to_the_trail(): void
    {
        $booking = $this->book();
        $this->actions()->reject($booking, $this->owner);

        $this->actions()->note($booking->fresh(), $this->owner, str_repeat('n', 2500).' SECRETNOTE');
        $this->assertSame(2000, mb_strlen($booking->fresh()->internal_notes));
        $this->actions()->note($booking->fresh(), $this->owner, '   ');
        $this->assertNull($booking->fresh()->internal_notes);

        $this->actions()->note($booking->fresh(), $this->owner, 'Customer is difficult');
        $dump = json_encode(BookingEvent::all()->toArray());
        foreach (['difficult', 'Zodwa', 'zodwa@example.test', 'Secret Traders', 'private matter', '082 000'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
        $this->assertSame(3, BookingEvent::where('type', 'booking_note_updated')->count());
    }

    public function test_every_action_writes_one_audit_entry_naming_the_actor_and_the_booking(): void
    {
        $booking = $this->book();
        $this->actions()->accept($booking, $this->owner);
        $this->actions()->cancel($booking->fresh(), $this->owner);

        $events = BookingEvent::whereIn('type', ['booking_accepted', 'booking_cancelled_by_business'])->orderBy('id')->get();
        $this->assertSame(['booking_accepted', 'booking_cancelled_by_business'], $events->pluck('type')->all());
        $this->assertSame([$this->owner->id, $this->owner->id], $events->pluck('actor_id')->all());
        $this->assertSame([$booking->id, $booking->id], $events->map(fn ($e) => $e->details['booking_id'])->all());
    }

    // ------------------------------------------------------------------------------------------------------------------- reschedule requests

    public function test_a_customer_asks_to_move_and_nothing_moves(): void
    {
        $customer = $this->customer();
        $booking = $this->book('2026-12-01 08:00:00', $customer, null, 'auto');

        app(Bookings::class)->requestReschedule($booking, $customer, "  Can we do the morning?\n  ");

        $fresh = $booking->fresh();
        $this->assertSame(['2026-12-01 08:00:00', Booking::CONFIRMED, 'Can we do the morning?'], [$fresh->starts_at->utc()->format('Y-m-d H:i:s'), $fresh->status, $fresh->reschedule_note]);
        $this->assertFalse($this->free('2026-12-01 08:00:00'), 'it still holds its time');
        $this->assertSame(BookingEvent::where('type', 'booking_reschedule_requested')->sole()->actor_id, $customer->id);
    }

    public function test_the_customer_can_ask_only_once_only_for_their_own_and_inside_the_cut_off_rule(): void
    {
        $customer = $this->customer();
        $booking = $this->book('2026-12-01 08:00:00', $customer, null, 'auto');

        $this->assertSame(BookingRefused::NOT_YOURS, $this->refusal(fn () => app(Bookings::class)->requestReschedule($booking, $this->customer(), 'x'))->reason);
        app(Bookings::class)->requestReschedule($booking, $customer, 'Earlier please');
        $this->assertStringContainsString('already asked', $this->refusal(fn () => app(Bookings::class)->requestReschedule($booking->fresh(), $customer, 'again'))->getMessage());

        // A confirmed appointment inside the 24 hour cut-off cannot be moved by asking; a pending one can.
        $soon = $this->book('2026-12-01 10:00:00', $customer, null, 'auto');
        $pending = $this->book('2026-12-01 12:00:00', $customer, null, 'approval');
        $this->travelTo($this->at('2026-11-30 20:00:00'));
        $this->assertSame(BookingRefused::TOO_LATE, $this->refusal(fn () => app(Bookings::class)->requestReschedule($soon, $customer, 'x'))->reason);
        $this->assertStringContainsString('move it here', $this->refusal(fn () => app(Bookings::class)->requestReschedule($soon, $customer, 'x'))->getMessage());
        app(Bookings::class)->requestReschedule($pending, $customer, 'Later please');
        $this->assertNotNull($pending->fresh()->reschedule_requested_at);
    }

    public function test_declining_a_request_leaves_the_appointment_and_tells_the_customer(): void
    {
        $customer = $this->customer();
        $booking = $this->book('2026-12-01 08:00:00', $customer, null, 'auto');
        $this->assertSame(BookingRefused::STATE, $this->refusal(fn () => $this->actions()->declineReschedule($booking, $this->owner))->reason, 'nothing was asked');

        app(Bookings::class)->requestReschedule($booking, $customer, 'Earlier please');
        $this->actions()->declineReschedule($booking->fresh(), $this->owner, 'That day is full.');

        $fresh = $booking->fresh();
        $this->assertSame([null, null, 'That day is full.', Booking::CONFIRMED], [$fresh->reschedule_requested_at, $fresh->reschedule_note, $fresh->status_note, $fresh->status]);
        $this->assertSame('2026-12-01 08:00:00', $fresh->starts_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_cancelling_or_rejecting_clears_a_pending_request_to_move(): void
    {
        $customer = $this->customer();
        $booking = $this->book('2026-12-01 08:00:00', $customer, null, 'approval');
        app(Bookings::class)->requestReschedule($booking, $customer, 'Earlier please');

        $this->actions()->reject($booking->fresh(), $this->owner);

        $this->assertNull($booking->fresh()->reschedule_requested_at);
    }
}
