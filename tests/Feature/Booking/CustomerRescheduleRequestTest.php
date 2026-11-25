<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\User;
use App\Services\Booking\BookingActions;

/** R-63 phase 4: the customer asks to move an appointment and reads the business's answer. */
class CustomerRescheduleRequestTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->stranger();
    }

    private function mine(string $start = '2026-11-30 08:00:00', string $status = Booking::CONFIRMED, array $over = []): Booking
    {
        return $this->mk($start, $status, $this->a, ['customer_user_id' => $this->customer->id] + $over);
    }

    private function ask(Booking $b, string $note = 'Could we do Tuesday instead?', ?User $who = null)
    {
        return $this->actingAs($who ?? $this->customer)->post('/my-bookings/'.$b->reference.'/reschedule', ['note' => $note]);
    }

    public function test_the_form_is_offered_and_the_request_is_recorded_once(): void
    {
        $b = $this->mine();

        $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertOk()->assertSee('Ask to move this appointment')->assertSee('Send the request');

        $this->ask($b)->assertRedirect('/my-bookings/'.$b->reference)->assertSessionHas('status', 'reschedule_requested');
        $fresh = $b->fresh();
        $this->assertNotNull($fresh->reschedule_requested_at);
        $this->assertSame('Could we do Tuesday instead?', $fresh->reschedule_note);
        $this->assertSame('2026-11-30 08:00:00', $fresh->starts_at->utc()->format('Y-m-d H:i:s'), 'nothing moves');
        $this->assertSame(1, BookingEvent::where('type', 'booking_reschedule_requested')->count());

        $page = $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertSee('You asked to move this appointment: Could we do Tuesday instead?. Dlamini Freight will answer here.')->assertDontSee('Send the request');
        $this->ask($b, 'Again please')->assertSessionHasErrors('booking');
        $this->assertSame('Could we do Tuesday instead?', $b->fresh()->reschedule_note);
        $this->assertNotNull($page);
    }

    public function test_the_request_also_works_for_a_pending_request(): void
    {
        $b = $this->mine('2026-11-30 08:00:00', Booking::PENDING);

        $this->ask($b)->assertSessionHas('status', 'reschedule_requested');
        $this->actingAs($this->owner)->get($this->url('/'.$b->id))->assertSee('The customer asked to move this appointment')->assertSee('Could we do Tuesday instead?');
        $this->actingAs($this->owner)->get($this->url())->assertSee('Asked to move');
    }

    public function test_it_is_the_customers_own_booking_or_a_404(): void
    {
        $b = $this->mine();
        $someone = $this->stranger();

        $this->ask($b, 'Hello there', $someone)->assertNotFound();
        $this->actingAs($this->owner)->post('/my-bookings/'.$b->reference.'/reschedule', ['note' => 'Hello there'])->assertNotFound();
        $this->actingAs($someone)->get('/my-bookings/'.$b->reference)->assertNotFound();
        $this->actingAs($this->customer)->post('/my-bookings/NOPE-1/reschedule', ['note' => 'Hello there'])->assertNotFound();
        $this->assertNull($b->fresh()->reschedule_requested_at);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $b = $this->mine();

        $this->post('/my-bookings/'.$b->reference.'/reschedule', ['note' => 'Hello there'])->assertRedirect('/login');
    }

    public function test_the_note_is_validated(): void
    {
        $b = $this->mine();

        foreach (['', '  ', 'ab', str_repeat('x', 301)] as $bad) {
            $this->ask($b, $bad)->assertSessionHasErrors('note');
        }
        $this->assertNull($b->fresh()->reschedule_requested_at);
        $this->ask($b, str_repeat('x', 300))->assertSessionHas('status');
    }

    public function test_the_request_is_a_404_until_the_feature_is_available(): void
    {
        $b = $this->mine();
        $this->feature('disabled');

        $this->ask($b)->assertNotFound();
    }

    public function test_near_the_start_the_customer_is_told_to_contact_the_business_and_cannot_ask(): void
    {
        $b = $this->mine('2026-11-21 08:00:00'); // 24h cut-off: tomorrow 10:00 local is within it

        $page = $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertOk()->assertSee('It is too close to the appointment to move it here. Please contact Dlamini Freight.')->assertDontSee('Send the request');
        $this->assertNotNull($page);
        $this->ask($b)->assertSessionHasErrors('booking');
        $this->assertNull($b->fresh()->reschedule_requested_at);
    }

    public function test_a_started_or_closed_appointment_cannot_be_asked_about(): void
    {
        $started = $this->mine('2026-11-19 08:00:00');
        $closed = $this->mine('2026-11-30 08:00:00', Booking::CANCELLED_BY_BUSINESS);

        foreach ([$started, $closed] as $b) {
            $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertDontSee('Ask to move this appointment');
            $this->ask($b)->assertSessionHasErrors('booking');
        }
    }

    public function test_the_business_message_is_shown_and_its_internal_notes_never_are(): void
    {
        $b = $this->mine('2026-11-30 08:00:00', Booking::PENDING, ['internal_notes' => 'SECRET: difficult client']);
        $this->actingAs($this->owner)->post($this->url('/'.$b->id.'/reject'), ['note' => 'We are closed that week <b>sorry</b>'])->assertSessionHas('status');

        $page = $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertOk();
        $page->assertSee('A message from Dlamini Freight: We are closed that week &lt;b&gt;sorry&lt;/b&gt;', false)->assertDontSee('SECRET')->assertDontSee('difficult client');
        $this->actingAs($this->customer)->get('/my-bookings')->assertDontSee('SECRET');
    }

    public function test_declining_the_move_shows_the_reason_and_lets_the_customer_ask_again(): void
    {
        $b = $this->mine('2026-11-30 08:00:00', Booking::CONFIRMED, ['internal_notes' => 'SECRET note']);
        $this->ask($b)->assertSessionHas('status');

        $this->actingAs($this->owner)->post($this->url('/'.$b->id.'/decline-reschedule'), ['note' => 'We are full on Tuesday'])->assertSessionHas('status');

        $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertSee('A message from Dlamini Freight: We are full on Tuesday')->assertDontSee('You asked to move')->assertDontSee('SECRET')->assertSee('Send the request');
        $this->ask($b, 'Then Wednesday?')->assertSessionHas('status');
        $this->assertTrue(BookingActions::canManage($this->owner, $b->fresh()));
    }

    public function test_moving_the_appointment_answers_the_request_on_the_customers_page(): void
    {
        $b = $this->mine();
        $this->ask($b)->assertSessionHas('status');

        $this->actingAs($this->owner)->post($this->url('/'.$b->id.'/reschedule'), ['start' => '2026-12-01T08:00:00Z'])->assertSessionHas('status');

        $this->actingAs($this->customer)->get('/my-bookings/'.$b->reference)->assertSee('Tue 1 Dec 2026, 10:00')->assertDontSee('You asked to move');
    }
}
