<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-63 phase 3, the customer side: confirm, book, and the person's own bookings. "Now" is 20 November 2026; Mondays are open 09:00 to 17:00 Johannesburg. */
class CustomerBookingFlowTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private AppointmentType $type;

    private User $customer;

    private const START = '2026-11-30T07:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        [$this->team] = $this->business('available');
        $this->team->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'We move freight.'])->save();
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'auto', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 0, 'horizon_days' => 365, 'cancel_cutoff_hours' => 24]);
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->type = $this->type($this->team, ['name' => 'Initial consultation', 'preparation' => 'Bring a statement.']);
        $this->customer = User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Secret', 'email' => 'zodwa@example.test']);
    }

    private function confirmUrl(?string $start = null): string
    {
        return route('business.book.confirm', [$this->team, 'type' => $this->type->id, 'start' => $start ?? self::START]);
    }

    private function form(array $over = []): array
    {
        return $over + ['type' => $this->type->id, 'start' => self::START, 'name' => 'Zodwa Secret', 'email' => 'zodwa@example.test', 'phone' => '082 000 0000', 'company' => 'Secret Traders', 'reason' => 'A private matter'];
    }

    private function dateUrl(): string
    {
        return route('business.book', [$this->team, 'type' => $this->type->id, 'date' => '2026-11-30']);
    }

    public function test_guests_are_sent_to_sign_in_for_confirm_store_and_my_bookings(): void
    {
        $this->get($this->confirmUrl())->assertRedirect(route('login'));
        $this->post(route('business.book.store', $this->team), $this->form())->assertRedirect(route('login'));
        $this->get(route('my-bookings'))->assertRedirect(route('login'));
        $this->assertSame(0, Booking::count());
    }

    public function test_the_confirm_step_summarises_the_booking_and_defaults_the_account_details(): void
    {
        $this->actingAs($this->customer)->get($this->confirmUrl())->assertOk()
            ->assertSee('Initial consultation')->assertSee('Mon 30 Nov 2026, 09:00')->assertSee('Africa/Johannesburg time')->assertSee('60 minutes')->assertDontSee('Bring a statement.')
            ->assertSee('value="Zodwa Secret"', false)->assertSee('value="zodwa@example.test"', false)->assertSee('name="start" value="'.self::START.'"', false)
            ->assertDontSee('I accept these terms');
    }

    public function test_confirm_refuses_a_bad_or_taken_time_and_a_type_of_another_business(): void
    {
        $this->actingAs($this->customer);
        $this->get($this->confirmUrl('not-a-time'))->assertNotFound();
        $this->get($this->confirmUrl('2026-11-30'))->assertNotFound();
        $other = $this->type(Team::factory()->create(['personal_team' => false]), ['name' => 'Elsewhere']);
        $this->get(route('business.book.confirm', [$this->team, 'type' => $other->id, 'start' => self::START]))->assertNotFound();

        $this->get($this->confirmUrl('2026-11-30T03:00:00Z'))->assertRedirect($this->dateUrl())->assertSessionHasErrors('booking');
        $this->type->update(['active' => false]);
        $this->get($this->confirmUrl())->assertNotFound();
    }

    public function test_an_auto_confirmed_booking_is_made_and_shown_as_confirmed(): void
    {
        $response = $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form());

        $booking = Booking::firstOrFail();
        $response->assertRedirect(route('my-bookings.show', $booking->reference));
        $this->assertSame(Booking::CONFIRMED, $booking->status);
        $this->assertSame($this->customer->id, $booking->customer_user_id);
        $this->assertSame('2026-11-30 07:00:00', $booking->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Secret Traders', $booking->customer_company);

        $this->actingAs($this->customer)->followingRedirects()->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']))
            ->assertOk()->assertSee('Your appointment is confirmed');
    }

    public function test_a_pending_booking_explains_that_the_business_must_accept(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => 'approval']);

        $this->actingAs($this->customer)->followingRedirects()->post(route('business.book.store', $this->team), $this->form())
            ->assertOk()->assertSee('has to accept it before the appointment is confirmed')->assertSee('Waiting for Dlamini Freight to confirm')->assertSee('Cancel this appointment');
        $this->assertSame(Booking::PENDING, Booking::firstOrFail()->status);
    }

    public function test_validation_errors_come_back_with_the_form(): void
    {
        $this->actingAs($this->customer);
        $back = $this->confirmUrl();

        foreach ([['name' => ''], ['email' => 'nope'], ['reason' => str_repeat('x', 1501)], ['phone' => str_repeat('1', 41)], ['company' => str_repeat('c', 161)], ['start' => 'tomorrow'], ['type' => 'abc']] as $bad) {
            $this->from($back)->post(route('business.book.store', $this->team), $this->form($bad))->assertRedirect($back)->assertSessionHasErrors(array_key_first($bad));
        }
        $this->assertSame(0, Booking::count());
    }

    public function test_the_terms_checkbox_is_required_only_when_the_business_has_terms(): void
    {
        $this->actingAs($this->customer);
        $this->post(route('business.book.store', $this->team), $this->form())->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());

        BookingSetting::where('team_id', $this->team->id)->update(['terms' => "Be on time.\nPay on the day."]);
        $this->get($this->confirmUrl('2026-11-30T09:00:00Z'))->assertSee('I accept these terms')->assertSee('Be on time.');
        $this->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']))->assertSessionHasErrors('terms_accepted');
        $this->assertSame(1, Booking::count());
        $this->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z', 'terms_accepted' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(2, Booking::count());
    }

    public function test_a_taken_slot_sends_the_person_to_that_date_with_the_message(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->post(route('business.book.store', $this->team), $this->form());

        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())
            ->assertRedirect($this->dateUrl())->assertSessionHasErrors(['booking' => 'That time has just been taken or is no longer available. Choose another time.']);
        $this->followingRedirects()->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())->assertSee('has just been taken')->assertDontSee('2026-11-30T07:00:00Z');
        $this->assertSame(1, Booking::count());
    }

    public function test_closed_business_and_limits_keep_the_person_on_the_form_with_a_plain_message(): void
    {
        $this->actingAs($this->customer);
        $back = $this->confirmUrl();
        config(['booking.limits.active_per_business' => 1]);
        $this->post(route('business.book.store', $this->team), $this->form());

        $this->from($back)->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']))->assertRedirect($back)->assertSessionHasErrors('booking');
        $this->followingRedirects()->get($back);

        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => false]);
        $this->get($this->confirmUrl('2026-11-30T09:00:00Z'))->assertOk()->assertSee('is not taking appointments')->assertDontSee('Book this appointment');
        $this->from($back)->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']))->assertRedirect($back)->assertSessionHasErrors('booking');
        $this->assertSame(1, Booking::count());
    }

    public function test_a_member_of_the_business_is_told_and_cannot_book(): void
    {
        $employee = $this->member($this->team, 'editor');

        $this->actingAs($employee)->get($this->confirmUrl())->assertOk()->assertSee('you manage its appointments instead of booking them')->assertDontSee('Book this appointment');
        $this->from($this->confirmUrl())->post(route('business.book.store', $this->team), $this->form())->assertSessionHasErrors('booking');
        $this->assertSame(0, Booking::count());
    }

    public function test_my_bookings_lists_only_the_persons_own_with_plain_status_words(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form());
        $mine = Booking::firstOrFail();
        $mine->forceFill(['internal_notes' => 'Secret internal note'])->save();
        $other = User::factory()->create(['email_verified_at' => now(), 'name' => 'Someone Else']);
        $this->actingAs($other)->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z', 'name' => 'Someone Else', 'reason' => 'Others reason']));
        $past = $this->book($this->type);   // 1 December 08:00Z, confirmed by another customer (no user)
        $past->forceFill(['reference' => 'TEST-B-000999', 'customer_user_id' => $this->customer->id, 'status' => Booking::CANCELLED_BY_BUSINESS])->save();

        $page = $this->actingAs($this->customer)->get(route('my-bookings'))->assertOk();
        $page->assertSee($mine->reference)->assertSee('Confirmed')->assertSee('Cancelled by Dlamini Freight')->assertSee('Cancel')->assertSee('Mon 30 Nov 2026, 09:00')
            ->assertDontSee('Someone Else')->assertDontSee('Others reason')->assertDontSee('Secret internal note');
    }

    public function test_a_booking_page_shows_only_to_its_owner_and_other_references_are_404(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form());
        $ref = Booking::firstOrFail()->reference;

        $this->actingAs($this->customer)->get(route('my-bookings.show', $ref))->assertOk()->assertSee($ref)->assertSee('Bring a statement.')->assertDontSee('internal');
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($stranger)->get(route('my-bookings.show', $ref))->assertNotFound();
        $this->actingAs($stranger)->post(route('my-bookings.cancel', $ref))->assertNotFound();
        $this->actingAs($this->customer)->get(route('my-bookings.show', 'NOPE-B-999999'))->assertNotFound();
        $this->assertSame(Booking::CONFIRMED, Booking::firstOrFail()->status);
    }

    public function test_cancelling_works_and_is_refused_with_the_reason_when_too_late(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form());
        $booking = Booking::firstOrFail();

        $this->post(route('my-bookings.cancel', $booking->reference))->assertRedirect(route('my-bookings.show', $booking->reference))->assertSessionHas('status', 'cancelled');
        $this->assertSame(Booking::CANCELLED_BY_CUSTOMER, $booking->fresh()->status);
        $this->followingRedirects()->get(route('my-bookings.show', $booking->reference))->assertSee('Cancelled by you')->assertDontSee('Cancel this appointment');
        $this->post(route('my-bookings.cancel', $booking->reference))->assertSessionHasErrors(['booking' => 'This appointment is already closed.']);

        // Inside the business's cut-off a confirmed appointment cannot be cancelled online: the page says why instead of offering the button.
        $this->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']));
        $soon = Booking::where('status', Booking::CONFIRMED)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-11-30 05:00:00', 'UTC'));
        $this->get(route('my-bookings.show', $soon->reference))->assertSee('too close to the appointment')->assertDontSee('Cancel this appointment');
        $this->post(route('my-bookings.cancel', $soon->reference))->assertSessionHasErrors('booking');
        $this->assertSame(Booking::CONFIRMED, $soon->fresh()->status);
    }

    public function test_the_signed_in_booking_page_links_to_my_bookings_and_a_guest_does_not_see_it(): void
    {
        $this->get(route('business.book', $this->team))->assertDontSee(route('my-bookings'), false);
        $this->actingAs($this->customer)->get(route('business.book', $this->team))->assertSee(route('my-bookings'), false);
    }
}
