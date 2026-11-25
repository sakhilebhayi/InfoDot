<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

/** R-63 phase 4: the appointment page and what is done from it, every action through its route. */
class BusinessAppointmentActionsTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    private function act(string $path, array $data = [], $who = null)
    {
        return $this->actingAs($who ?? $this->owner)->post($this->url($path), $data);
    }

    private function show(Booking $b, string $query = '', $who = null)
    {
        return $this->actingAs($who ?? $this->owner)->get($this->url('/'.$b->id.$query));
    }

    // ------------------------------------------------------------------------------------------------------------------- the page

    public function test_the_page_shows_everything_to_the_people_who_may_act_on_it(): void
    {
        $b = $this->mk('2026-11-30 22:30:00', Booking::CONFIRMED, $this->a, ['internal_notes' => 'Bring the file', 'status_note' => 'See you then', 'reschedule_requested_at' => now(), 'reschedule_note' => 'Can we do Tuesday?']);

        $this->show($b)->assertOk()->assertSee($b->reference)->assertSee('Confirmed')->assertSee('Consultation')->assertSee('Tue 1 Dec 2026, 00:30 to 01:30')->assertSee('Africa/Johannesburg')
            ->assertSee('No fee')->assertSee('Thandi')->assertSee('Zodwa Secret')->assertSee('zodwa@example.test')->assertSee('082 000 0000')->assertSee('Secret Traders')->assertSee('A private matter')
            ->assertSee('Can we do Tuesday?')->assertSee('See you then')->assertSee('Bring the file')->assertSee('Decline the request to move');
        $this->show($b, '', $this->thandi)->assertOk()->assertSee('zodwa@example.test');
    }

    public function test_the_history_lists_what_happened_in_plain_words_with_no_customer_data(): void
    {
        $customer = $this->stranger();
        $customer->forceFill(['name' => 'Zodwa Secret'])->save();
        $b = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a, ['customer_user_id' => $customer->id]);
        BookingEvent::create(['team_id' => $this->team->id, 'actor_id' => $customer->id, 'type' => 'booking_created', 'details' => ['booking_id' => $b->id]]);
        $this->act('/'.$b->id.'/accept')->assertRedirect();
        $this->act('/'.$b->id.'/note', ['internal_notes' => 'x'])->assertRedirect();
        BookingEvent::create(['team_id' => $this->team->id, 'actor_id' => $this->sipho->id, 'type' => 'booking_created', 'details' => ['booking_id' => $b->id + 1000]]);

        $html = $this->show($b)->assertOk()->getContent();
        $history = substr($html, strpos($html, 'id="history-h"'));
        $history = substr($history, 0, strpos($history, '</section>'));

        $this->assertStringContainsString('Booked by the customer, by the customer', $history);
        $this->assertStringContainsString('Accepted, by '.e($this->owner->name), $history, 'the name is escaped in the page, so a name with an apostrophe must be compared escaped');
        $this->assertStringContainsString('Internal note changed', $history);
        $this->assertStringNotContainsString('Zodwa', $history);
        $this->assertStringNotContainsString('zodwa@example.test', $history);
        $this->assertStringNotContainsString('Sipho', $history, 'an event of another booking is not listed');
        $this->assertStringNotContainsString('082 000 0000', $history);
    }

    public function test_the_actions_offered_follow_the_state_of_the_appointment(): void
    {
        $pending = $this->mk('2026-11-30 08:00:00');
        $this->show($pending)->assertSee('Accept this request')->assertSee('Reject this request')->assertSee('Cancel this appointment')->assertSee('Move to another time')->assertSee('Hand over')->assertDontSee('Mark as done');

        $confirmed = $this->mk('2026-11-30 10:00:00', Booking::CONFIRMED);
        $this->show($confirmed)->assertDontSee('Accept this request')->assertDontSee('Reject this request')->assertSee('Cancel this appointment')->assertDontSee('Mark as done');

        $started = $this->mk('2026-11-19 08:00:00', Booking::CONFIRMED);
        $this->show($started)->assertSee('Mark as done')->assertSee('Mark as a no-show')->assertDontSee('Cancel this appointment')->assertDontSee('Move to another time');

        $closed = $this->mk('2026-11-30 12:00:00', Booking::REJECTED);
        $this->show($closed)->assertDontSee('What you can do')->assertDontSee('Move to another time')->assertSee('Internal notes');

        $this->show($pending, '', $this->thandi)->assertForbidden();
        $own = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a);
        $this->show($own, '', $this->thandi)->assertOk()->assertSee('Accept this request')->assertDontSee('Hand over');
    }

    // ------------------------------------------------------------------------------------------------------------------- accept, reject, cancel

    public function test_accept_reject_and_cancel_work_and_show_a_status(): void
    {
        $a = $this->mk('2026-11-30 08:00:00');
        $this->act('/'.$a->id.'/accept')->assertRedirect($this->url('/'.$a->id))->assertSessionHas('status');
        $this->assertSame(Booking::CONFIRMED, $a->fresh()->status);
        $this->show($a)->assertSee('Accepted.');

        $r = $this->mk('2026-11-30 10:00:00');
        $this->act('/'.$r->id.'/reject', ['note' => 'Closed that week'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame([Booking::REJECTED, 'Closed that week'], [$r->fresh()->status, $r->fresh()->status_note]);

        $c = $this->mk('2026-11-30 12:00:00', Booking::CONFIRMED);
        $this->act('/'.$c->id.'/cancel', ['note' => 'Sorry'])->assertRedirect();
        $this->assertSame([Booking::CANCELLED_BY_BUSINESS, 'Sorry'], [$c->fresh()->status, $c->fresh()->status_note]);
        $this->act('/'.$c->id.'/cancel', ['note' => str_repeat('x', 301)])->assertSessionHasErrors('note');
    }

    public function test_a_refused_action_shows_the_reason_as_an_error_and_changes_nothing(): void
    {
        $done = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED);
        $this->act('/'.$done->id.'/accept')->assertRedirect($this->url('/'.$done->id))->assertSessionHasErrors('action');
        $this->show($done)->assertSee('This appointment is confirmed, so that cannot be done now.');
        $this->assertSame(Booking::CONFIRMED, $done->fresh()->status);

        $started = $this->mk('2026-11-19 08:00:00', Booking::PENDING);
        $this->act('/'.$started->id.'/accept')->assertSessionHasErrors('action');
        $this->act('/'.$started->id.'/cancel')->assertSessionHasErrors('action');
        $this->assertSame(Booking::PENDING, $started->fresh()->status);
    }

    public function test_complete_and_no_show_only_once_it_has_started(): void
    {
        $future = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED);
        $this->act('/'.$future->id.'/complete')->assertSessionHasErrors('action');
        $this->act('/'.$future->id.'/no-show')->assertSessionHasErrors('action');

        $a = $this->mk('2026-11-19 08:00:00', Booking::CONFIRMED);
        $b = $this->mk('2026-11-19 10:00:00', Booking::CONFIRMED);
        $this->act('/'.$a->id.'/complete')->assertSessionHas('status');
        $this->act('/'.$b->id.'/no-show')->assertSessionHas('status');
        $this->assertSame([Booking::COMPLETED, Booking::NO_SHOW], [$a->fresh()->status, $b->fresh()->status]);
        $this->act('/'.$a->id.'/no-show')->assertSessionHasErrors('action');
    }

    // ------------------------------------------------------------------------------------------------------------------- move

    public function test_the_move_list_excludes_other_bookings_but_not_the_appointments_own_time_and_ignores_least_notice(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['min_notice_minutes' => 60 * 24 * 30]);
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, $this->a);          // 10:00 local, provider A
        $this->mk('2026-11-30 09:00:00', Booking::CONFIRMED, $this->a);               // 11:00 local, also A: blocks A at 11:00
        $this->mk('2026-11-30 10:00:00', Booking::CANCELLED_BY_CUSTOMER, $this->a);   // free again

        $r = $this->show($b, '?move=2026-11-30')->assertOk()->assertSee('Free times on Mon 30 Nov 2026');
        $times = [];
        preg_match_all('/name="start" value="(2026-11-30T[0-9:]+Z)"/', $r->getContent(), $m);
        $times = $m[1];

        $this->assertContains('2026-11-30T08:00:00Z', $times, 'its own time is free for itself');
        $this->assertNotContains('2026-11-30T09:00:00Z', $times, 'another booking of the same provider');
        $this->assertContains('2026-11-30T10:00:00Z', $times);
        $this->assertContains('2026-11-30T07:00:00Z', $times, 'inside the 30 day notice, which the business ignores for its own moves');
        $this->assertNotContains('2026-11-30T06:00:00Z', $times, 'outside working hours');

    }

    public function test_a_day_with_no_hours_says_so_and_a_bad_date_shows_no_list(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED);

        $this->show($b, '?move=2026-11-28')->assertSee('Nothing is free on Sat 28 Nov 2026');
        $this->show($b, '?move=2026-02-30')->assertOk()->assertDontSee('Free times on')->assertDontSee('Nothing is free');
        $this->show($b, '?move[]=x')->assertOk();
    }

    public function test_moving_works_answers_the_request_and_refuses_a_taken_or_malformed_time(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, $this->a, ['reschedule_requested_at' => now(), 'reschedule_note' => 'Earlier please']);
        $other = $this->mk('2026-11-30 09:00:00', Booking::CONFIRMED, $this->a);
        $this->actingAs($this->owner);

        $this->act('/'.$b->id.'/reschedule', ['start' => '2026-11-30T09:00:00Z'])->assertRedirect()->assertSessionHasErrors('action');
        $this->assertSame('2026-11-30 08:00:00', $b->fresh()->starts_at->utc()->format('Y-m-d H:i:s'));

        foreach (['2026-11-30 07:00', '2026-11-30T07:00:00', '2026-11-30T07:00:00+02:00', '', 'tomorrow'] as $bad) {
            $this->act('/'.$b->id.'/reschedule', ['start' => $bad])->assertSessionHasErrors('start');
        }
        $this->act('/'.$b->id.'/reschedule', ['start' => '2026-13-45T99:00:00Z'])->assertNotFound();

        $this->act('/'.$b->id.'/reschedule', ['start' => '2026-11-30T07:00:00Z'])->assertRedirect($this->url('/'.$b->id))->assertSessionHas('status');
        $fresh = $b->fresh();
        $this->assertSame('2026-11-30 07:00:00', $fresh->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($fresh->reschedule_requested_at);
        $this->assertSame(Booking::CONFIRMED, $fresh->status);
        $this->assertSame(1, BookingEvent::where('type', 'booking_rescheduled')->count());
        $this->assertNotNull($other);

        $started = $this->mk('2026-11-19 08:00:00', Booking::CONFIRMED);
        $this->act('/'.$started->id.'/reschedule', ['start' => '2026-11-30T12:00:00Z'])->assertSessionHasErrors('action');
    }

    // ------------------------------------------------------------------------------------------------------------------- hand over

    public function test_a_manager_hands_over_to_another_provider_or_to_whoever_is_free(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, $this->a);

        $this->act('/'.$b->id.'/assign', ['provider' => (string) $this->b->id])->assertSessionHas('status');
        $this->assertSame($this->b->id, $b->fresh()->booking_provider_id);

        $this->act('/'.$b->id.'/assign', ['provider' => 'any'])->assertSessionHas('status');
        $this->assertSame($this->a->id, $b->fresh()->booking_provider_id, 'the lowest free');

        $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, $this->b);
        $this->act('/'.$b->id.'/assign', ['provider' => (string) $this->b->id])->assertSessionHasErrors('action');
        $this->act('/'.$b->id.'/assign', ['provider' => '999999'])->assertStatus(422);
        $this->act('/'.$b->id.'/assign', ['provider' => 'abc'])->assertStatus(422);
        $this->act('/'.$b->id.'/assign')->assertSessionHasErrors('provider');
    }

    public function test_a_provider_cannot_hand_over_even_with_a_forged_post(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, $this->a);

        $this->act('/'.$b->id.'/assign', ['provider' => (string) $this->b->id], $this->thandi)->assertForbidden();
        $this->assertSame($this->a->id, $b->fresh()->booking_provider_id);
    }

    public function test_forged_posts_for_other_peoples_bookings_or_impossible_actions_are_refused(): void
    {
        $theirs = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->b);

        foreach (['accept', 'reject', 'cancel', 'complete', 'no-show', 'note', 'decline-reschedule'] as $do) {
            $this->act('/'.$theirs->id.'/'.$do, [], $this->thandi)->assertForbidden();
        }
        $this->act('/'.$theirs->id.'/reschedule', ['start' => '2026-11-30T09:00:00Z'], $this->thandi)->assertForbidden();
        $this->assertSame(Booking::PENDING, $theirs->fresh()->status);

        // A provider's own booking, but a move that is not possible: refused by the engine, not by the screen.
        $mine = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a);
        $this->act('/'.$mine->id.'/complete', [], $this->thandi)->assertSessionHasErrors('action');
        $this->act('/'.$mine->id.'/decline-reschedule', [], $this->thandi)->assertSessionHasErrors('action');
    }

    // ------------------------------------------------------------------------------------------------------------------- notes and the customer's request

    public function test_internal_notes_are_saved_and_cleared(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, $this->a);

        $this->act('/'.$b->id.'/note', ['internal_notes' => '  Bring the file  '], $this->thandi)->assertSessionHas('status');
        $this->assertSame('Bring the file', $b->fresh()->internal_notes);
        $this->show($b)->assertSee('Bring the file');
        $this->act('/'.$b->id.'/note', ['internal_notes' => ''])->assertSessionHas('status');
        $this->assertNull($b->fresh()->internal_notes);
        $this->act('/'.$b->id.'/note', ['internal_notes' => str_repeat('n', 2001)])->assertSessionHasErrors('internal_notes');
    }

    public function test_declining_a_request_to_move_keeps_the_time_and_leaves_the_reason(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::CONFIRMED, null, ['reschedule_requested_at' => now(), 'reschedule_note' => 'Earlier']);

        $this->act('/'.$b->id.'/decline-reschedule', ['note' => 'We are full that day'])->assertSessionHas('status');
        $fresh = $b->fresh();
        $this->assertNull($fresh->reschedule_requested_at);
        $this->assertSame('We are full that day', $fresh->status_note);
        $this->assertSame('2026-11-30 08:00:00', $fresh->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->act('/'.$b->id.'/decline-reschedule')->assertSessionHasErrors('action');
    }

    public function test_every_post_has_its_own_throttle_bucket(): void
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->getName() !== null && str_starts_with($route->getName(), 'business.appointments.') && in_array('POST', $route->methods(), true)) {
                $throttle = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
                $this->assertNotNull($throttle, $route->getName());
                $this->assertSame($route->getName(), substr($throttle, strrpos($throttle, ',') + 1));
            }
        }
        $this->assertNotNull(CarbonImmutable::now());
    }
}
