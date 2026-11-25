<?php

namespace Tests\Feature\Booking;

use App\Actions\Jetstream\RemoveTeamMember;
use App\Models\Booking;
use App\Models\BookingProvider;
use App\Models\Team;
use App\Models\User;
use App\Services\Booking\BookingActions;
use App\Services\Booking\BookingRefused;
use App\Services\Booking\Bookings;

/** R-63 phase 4: the fixes that came out of the independent security review of the dashboard. */
class BookingDashboardReviewFixesTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    private function assigned(): Booking
    {
        return $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a);
    }

    public function test_a_provider_reaches_their_booking_while_they_belong_to_the_business_and_are_active(): void
    {
        $booking = $this->assigned();

        $this->actingAs($this->thandi)->get($this->url('/'.$booking->id))->assertOk()->assertSee('Zodwa Secret');
        $this->actingAs($this->thandi)->get($this->url())->assertOk();
    }

    public function test_a_provider_who_is_removed_from_the_business_loses_every_door_at_once(): void
    {
        $booking = $this->assigned();

        app(RemoveTeamMember::class)->remove($this->owner, $this->team, $this->thandi);

        $this->actingAs($this->thandi)->get($this->url('/'.$booking->id))->assertForbidden();
        $this->actingAs($this->thandi)->get($this->url())->assertForbidden();
        $this->actingAs($this->thandi)->get($this->url('/calendar'))->assertForbidden();
        $this->actingAs($this->thandi)->post($this->url('/'.$booking->id.'/accept'))->assertForbidden();
        $this->actingAs($this->thandi)->post($this->url('/'.$booking->id.'/note'), ['notes' => 'x'])->assertForbidden();
        $this->assertSame(Booking::PENDING, $booking->fresh()->status);
        $this->assertFalse(BookingProvider::find($this->a->id)->active, 'the removal also switches the provider off');
        $this->assertSame($this->a->id, $booking->fresh()->booking_provider_id, 'the booking stays assigned until the business reassigns it');
    }

    public function test_leaving_the_business_without_the_event_is_still_enough_to_lose_access(): void
    {
        $booking = $this->assigned();

        $this->team->removeUser($this->thandi); // no listener ran: the membership check alone must stop them

        $this->actingAs($this->thandi->fresh())->get($this->url('/'.$booking->id))->assertForbidden();
        $this->assertFalse(BookingActions::canManage($this->thandi->fresh(), $booking->fresh()));
    }

    public function test_a_provider_who_is_switched_off_loses_access_to_their_bookings_and_hours(): void
    {
        $booking = $this->assigned();
        $this->a->update(['active' => false]);

        $this->actingAs($this->thandi)->get($this->url('/'.$booking->id))->assertForbidden();
        $this->actingAs($this->thandi)->get($this->url())->assertForbidden();
        $this->actingAs($this->thandi)->get(route('business.booking.providers.hours', [$this->team, $this->a]))->assertForbidden();
        $this->actingAs($this->owner)->get($this->url('/'.$booking->id))->assertOk();
    }

    public function test_removing_a_member_switches_off_only_their_provider_record_in_that_business(): void
    {
        $other = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => false]);
        $elsewhere = $other->bookingProviders()->create(['user_id' => $this->thandi->id, 'public_name' => 'Thandi']);

        app(RemoveTeamMember::class)->remove($this->owner, $this->team, $this->thandi);

        $this->assertFalse(BookingProvider::find($this->a->id)->active);
        $this->assertTrue($elsewhere->fresh()->active);
        $this->assertTrue(BookingProvider::find($this->b->id)->active);
    }

    public function test_the_customers_email_cannot_inject_mailto_parameters(): void
    {
        $booking = $this->mk('2026-11-30 08:00:00', Booking::PENDING, null, ['customer_email' => 'x?bcc=evil@example.com']);

        $this->actingAs($this->owner)->get($this->url('/'.$booking->id))->assertOk()
            ->assertDontSee('mailto:x?bcc', false)->assertSee('mailto:x%3Fbcc%3Devil%40example.com', false);
    }

    public function test_declining_a_move_request_needs_a_booking_that_is_still_open_and_keeps_an_earlier_message(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $booking = $this->mk('2026-12-01 08:00:00', Booking::CONFIRMED, null, ['customer_user_id' => $customer->id, 'status_note' => 'See you then.']);
        app(Bookings::class)->requestReschedule($booking, $customer, 'Earlier please');

        app(BookingActions::class)->declineReschedule($booking->fresh(), $this->owner);
        $this->assertSame('See you then.', $booking->fresh()->status_note, 'no new text leaves the earlier message alone');

        app(Bookings::class)->requestReschedule($booking->fresh(), $customer, 'Again please');
        app(Bookings::class)->cancelByCustomer($booking->fresh(), $customer);

        $closed = $booking->fresh();
        $this->assertSame([Booking::CANCELLED_BY_CUSTOMER, null, null], [$closed->status, $closed->reschedule_requested_at, $closed->reschedule_note], 'cancelling clears the open request');

        try {
            app(BookingActions::class)->declineReschedule($closed, $this->owner, 'hello customer');
            $this->fail('a closed booking cannot be answered');
        } catch (BookingRefused $e) {
            $this->assertSame(BookingRefused::STATE, $e->reason);
        }
        $this->assertSame('See you then.', $closed->fresh()->status_note);
    }

    public function test_the_read_screens_have_their_own_throttle_bucket(): void
    {
        $names = collect(app('router')->getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'business.appointments') && in_array('GET', $r->methods(), true))
            ->map(fn ($r) => collect($r->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')));

        $this->assertCount(5, $names);
        $this->assertSame(['throttle:60,1,business.appointments.read'], $names->unique()->values()->all());
    }
}
