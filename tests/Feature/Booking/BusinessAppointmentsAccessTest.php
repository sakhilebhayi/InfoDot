<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

/** R-63 phase 4: who reaches the appointments screens, what each person sees, and the lists. */
class BusinessAppointmentsAccessTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    /** @return list<array{0: string, 1: string}> every route of the area as [method, path] for a booking */
    private function routes(Booking $b): array
    {
        $id = '/'.$b->id;

        return [
            ['get', ''], ['get', '/upcoming'], ['get', '/past'], ['get', '/calendar'], ['get', $id],
            ['post', $id.'/accept'], ['post', $id.'/reject'], ['post', $id.'/cancel'], ['post', $id.'/complete'], ['post', $id.'/no-show'],
            ['post', $id.'/reschedule'], ['post', $id.'/assign'], ['post', $id.'/note'], ['post', $id.'/decline-reschedule'],
        ];
    }

    private function go(string $method, string $path, $who = null)
    {
        $c = $who ? $this->actingAs($who) : $this;

        return $c->{$method}($this->url($path));
    }

    public function test_every_route_is_a_404_until_the_feature_is_available(): void
    {
        $b = $this->mk('2026-11-30 08:00:00');

        foreach (['disabled', 'development'] as $state) {
            $this->feature($state);
            foreach ($this->routes($b) as [$method, $path]) {
                $this->go($method, $path, $this->owner)->assertNotFound();
            }
        }
    }

    public function test_a_guest_is_sent_to_sign_in_on_every_route(): void
    {
        $b = $this->mk('2026-11-30 08:00:00');

        foreach ($this->routes($b) as [$method, $path]) {
            $this->go($method, $path)->assertRedirect('/login');
        }
    }

    public function test_the_people_who_run_the_business_reach_everything(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a);

        foreach ([$this->owner, $this->member($this->team, 'admin'), $this->member($this->team, 'manager')] as $person) {
            foreach (['', '/upcoming', '/past', '/calendar', '/'.$b->id] as $path) {
                $this->go('get', $path, $person)->assertOk();
            }
        }
    }

    public function test_a_plain_member_and_a_stranger_are_refused_and_see_no_customer_details(): void
    {
        $b = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a);
        $plain = $this->member($this->team, 'member', 'Plain Pat');

        foreach ([$plain, $this->stranger()] as $person) {
            foreach (['', '/upcoming', '/past', '/calendar', '/'.$b->id] as $path) {
                $this->go('get', $path, $person)->assertForbidden()->assertDontSee('zodwa@example.test')->assertDontSee('Zodwa Secret');
            }
            foreach (['accept', 'reject', 'cancel', 'complete', 'no-show', 'note', 'decline-reschedule'] as $do) {
                $this->go('post', '/'.$b->id.'/'.$do, $person)->assertForbidden();
            }
        }
        $this->assertSame(Booking::PENDING, $b->fresh()->status);
    }

    public function test_another_businesss_manager_gets_a_404_for_this_businesss_booking(): void
    {
        $b = $this->mk('2026-11-30 08:00:00');
        [$other, $otherOwner] = $this->business('available', 'Kestrel Mining');

        // Through their own business's URL the booking does not exist; through ours they have no right.
        $this->actingAs($otherOwner)->get('/business/'.$other->uuid.'/appointments/'.$b->id)->assertNotFound();
        $this->actingAs($otherOwner)->post('/business/'.$other->uuid.'/appointments/'.$b->id.'/accept')->assertNotFound();
        $this->actingAs($otherOwner)->get($this->url('/'.$b->id))->assertForbidden();
        $this->actingAs($otherOwner)->post($this->url('/'.$b->id.'/accept'))->assertForbidden();
        $this->assertSame(Booking::PENDING, $b->fresh()->status);
    }

    public function test_a_booking_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->owner)->get($this->url('/99999'))->assertNotFound();
        $this->actingAs($this->owner)->get($this->url('/abc'))->assertNotFound();
    }

    // ------------------------------------------------------------------------------------------------------------------- provider scoping

    public function test_a_provider_sees_only_their_own_bookings_everywhere(): void
    {
        $mine = $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a, ['customer_name' => 'Mine Customer']);
        $theirs = $this->mk('2026-11-30 10:00:00', Booking::PENDING, $this->b, ['customer_name' => 'Sipho Customer']);
        $unassigned = $this->mk('2026-12-01 10:00:00', Booking::PENDING, null, ['customer_name' => 'Nobody Customer']);
        $this->mk('2026-12-02 08:00:00', Booking::CONFIRMED, $this->a, ['customer_name' => 'Mine Confirmed']);
        $this->mk('2026-12-02 10:00:00', Booking::CONFIRMED, $this->b, ['customer_name' => 'Sipho Confirmed']);

        $c = $this->actingAs($this->thandi);
        $c->get($this->url())->assertOk()->assertSee('Mine Customer')->assertDontSee('Sipho Customer')->assertDontSee('Nobody Customer')->assertDontSee('name="provider"', false);
        $c->get($this->url('/upcoming'))->assertSee('Mine Confirmed')->assertDontSee('Sipho Confirmed');
        $c->get($this->url('/calendar?view=month&date=2026-11-30'))->assertSee('1 appointment')->assertDontSee('2 appointments');
        $c->get($this->url('/calendar?view=day&date=2026-11-30'))->assertSee('Mine Customer')->assertDontSee('Sipho Customer');
        // A provider filter in the URL changes nothing for a provider.
        $c->get($this->url('/?provider='.$this->b->id))->assertSee('Mine Customer')->assertDontSee('Sipho Customer');
        $c->get($this->url('/?provider=unassigned'))->assertSee('Mine Customer')->assertDontSee('Nobody Customer');

        $c->get($this->url('/'.$mine->id))->assertOk();
        $c->get($this->url('/'.$theirs->id))->assertForbidden()->assertDontSee('Sipho Customer');
        $c->get($this->url('/'.$unassigned->id))->assertForbidden();
    }

    public function test_the_pending_count_in_the_navigation_counts_only_what_the_person_may_act_on(): void
    {
        $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a);
        $this->mk('2026-12-01 08:00:00', Booking::PENDING, $this->a);
        $this->mk('2026-11-30 10:00:00', Booking::PENDING, $this->b);
        $this->mk('2026-11-30 12:00:00', Booking::PENDING, null);
        $this->mk('2026-11-19 12:00:00', Booking::PENDING, $this->a); // already started: not a request that can still be answered
        $this->mk('2026-12-03 12:00:00', Booking::CONFIRMED, $this->a);

        $this->actingAs($this->thandi)->get($this->url('/upcoming'))->assertSee('Appointments (2)')->assertSee('Requests (2)');
        $this->actingAs($this->sipho)->get($this->url('/upcoming'))->assertSee('Appointments (1)');
        $this->actingAs($this->owner)->get($this->url('/upcoming'))->assertSee('Appointments (4)');
        $this->actingAs($this->owner)->get($this->url('/?provider='.$this->a->id))->assertSee('Requests (4)', false); // the count ignores the filter
    }

    public function test_a_providers_navigation_is_trimmed_and_a_managers_has_the_whole_setup(): void
    {
        $this->actingAs($this->thandi)->get($this->url())->assertSee('My hours')->assertSee('Closures')->assertDontSee('Appointment types')->assertDontSee('>Providers<', false);
        $this->actingAs($this->owner)->get($this->url())->assertSee('Appointment types')->assertSee('Providers');
    }

    public function test_a_manager_filters_by_provider_including_unassigned(): void
    {
        $this->mk('2026-11-30 08:00:00', Booking::PENDING, $this->a, ['customer_name' => 'Alpha Cust']);
        $this->mk('2026-11-30 10:00:00', Booking::PENDING, $this->b, ['customer_name' => 'Beta Cust']);
        $this->mk('2026-11-30 12:00:00', Booking::PENDING, null, ['customer_name' => 'Gamma Cust']);
        $c = $this->actingAs($this->owner);

        $c->get($this->url())->assertSee('Alpha Cust')->assertSee('Beta Cust')->assertSee('Gamma Cust')->assertSee('Not assigned to anyone');
        $c->get($this->url('?provider='.$this->a->id))->assertSee('Alpha Cust')->assertDontSee('Beta Cust')->assertDontSee('Gamma Cust');
        $c->get($this->url('?provider=unassigned'))->assertSee('Gamma Cust')->assertDontSee('Alpha Cust')->assertDontSee('Beta Cust');
        $c->get($this->url('?provider=999999'))->assertSee('Alpha Cust')->assertSee('Gamma Cust'); // not a provider of this business: ignored
        $c->get($this->url('/calendar?view=day&date=2026-11-30&provider=unassigned'))->assertSee('Gamma Cust')->assertDontSee('Alpha Cust');
    }

    // ------------------------------------------------------------------------------------------------------------------- the lists

    public function test_each_tab_holds_the_right_bookings_in_the_right_order(): void
    {
        $this->mk('2026-12-02 08:00:00', Booking::PENDING, null, ['customer_name' => 'Pend Later']);
        $this->mk('2026-11-30 08:00:00', Booking::PENDING, null, ['customer_name' => 'Pend Sooner']);
        $this->mk('2026-12-04 08:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Conf Later']);
        $this->mk('2026-12-03 08:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Conf Sooner']);
        $this->mk('2026-11-18 08:00:00', Booking::PENDING, null, ['customer_name' => 'Pend Missed']);
        $this->mk('2026-11-17 08:00:00', Booking::CONFIRMED, null, ['customer_name' => 'Conf Unmarked']);
        $this->mk('2026-11-16 08:00:00', Booking::COMPLETED, null, ['customer_name' => 'Done Cust']);
        $this->mk('2026-11-15 08:00:00', Booking::NO_SHOW, null, ['customer_name' => 'Absent Cust']);
        $this->mk('2026-12-07 08:00:00', Booking::REJECTED, null, ['customer_name' => 'Rejected Cust']);
        $this->mk('2026-12-08 08:00:00', Booking::CANCELLED_BY_CUSTOMER, null, ['customer_name' => 'Cancelled Cust']);
        $this->mk('2026-12-09 08:00:00', Booking::CANCELLED_BY_BUSINESS, null, ['customer_name' => 'Called Off Cust']);
        $c = $this->actingAs($this->owner);

        $c->get($this->url())->assertSeeInOrder(['Pend Sooner', 'Pend Later'])->assertDontSee('Conf Later')->assertDontSee('Pend Missed')->assertDontSee('Rejected Cust')->assertSee('Accept')->assertSee('Reject');
        $c->get($this->url('/upcoming'))->assertSeeInOrder(['Conf Sooner', 'Conf Later'])->assertDontSee('Pend Sooner')->assertDontSee('Conf Unmarked')->assertDontSee('Done Cust');
        $past = $c->get($this->url('/past'))->assertSeeInOrder(['Rejected Cust', 'Pend Missed', 'Conf Unmarked', 'Done Cust', 'Absent Cust'])
            ->assertSee('Cancelled Cust')->assertSee('Called Off Cust')->assertSee('Mark done or no-show')->assertSee('Cancelled by the customer')->assertSee('Cancelled by the business')->assertSee('No-show')
            ->assertDontSee('Pend Sooner')->assertDontSee('Conf Sooner');
        $past->assertDontSee('>Accept<', false);
    }

    public function test_the_lists_show_the_reference_the_business_time_the_provider_and_a_reschedule_flag(): void
    {
        $b = $this->mk('2026-11-30 22:30:00', Booking::CONFIRMED, $this->a, ['reschedule_requested_at' => now(), 'reschedule_note' => 'Sooner please']);

        $this->actingAs($this->owner)->get($this->url('/upcoming'))->assertSee($b->reference)->assertSee('Tue 1 Dec 2026, 00:30')->assertSee('Thandi')->assertSee('Asked to move')->assertSee('Zodwa Secret')->assertSee('Consultation');
    }

    public function test_the_long_lists_are_paged_by_twenty_five(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->mk(CarbonImmutable::parse('2026-12-01 05:00:00', 'UTC')->addMinutes($i * 5)->format('Y-m-d H:i:s'), Booking::CONFIRMED, null, ['customer_name' => 'Cust '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'x']);
        }
        $c = $this->actingAs($this->owner);

        $c->get($this->url('/upcoming'))->assertOk()->assertSee('Cust 00x')->assertSee('Cust 24x')->assertDontSee('Cust 25x')->assertSee('Page 1 of 2');
        $c->get($this->url('/upcoming?page=2'))->assertSee('Cust 25x')->assertSee('Cust 29x')->assertDontSee('Cust 24x');
    }
}
