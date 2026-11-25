<?php

namespace Tests\Feature\Booking;

use App\Actions\Jetstream\DeleteUser;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-63 phase 3: the fixes that came out of the independent security review. "Now" is 20 November 2026; Mondays are open 09:00 to 17:00 Johannesburg. */
class BookingReviewFixesTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private AppointmentType $online;

    private User $customer;

    private const START = '2026-11-30T07:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        [$this->team] = $this->business('available');
        $this->team->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'We move freight.'])->save();
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'auto', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 0, 'horizon_days' => 365, 'terms' => 'No refunds.']);
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->online = $this->type($this->team, ['name' => 'Video call', 'meeting_format' => 'online', 'location_note' => 'https://meet.example/abc passcode 4821', 'preparation' => 'Door code is 9911.']);
        $this->customer = User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Secret', 'email' => 'zodwa@example.test']);
    }

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + ['type' => $this->online->id, 'start' => self::START, 'name' => 'Zodwa Secret', 'email' => 'zodwa@example.test', 'reason' => 'A private matter', 'terms_accepted' => '1'];
    }

    // ------------------------------------------------------------------------------------------------------------------- M1: meeting details

    public function test_how_to_join_and_preparation_are_not_shown_before_someone_has_booked(): void
    {
        $page = route('business.book', $this->team);
        $confirm = route('business.book.confirm', [$this->team, 'type' => $this->online->id, 'start' => self::START]);

        foreach ([$this->get($page), $this->actingAs($this->customer)->get($page), $this->actingAs($this->customer)->get($confirm)] as $response) {
            $response->assertOk()->assertDontSee('meet.example')->assertDontSee('passcode 4821')->assertDontSee('Door code');
        }
    }

    public function test_an_in_person_address_is_still_shown_but_its_preparation_is_not(): void
    {
        $this->type($this->team, ['name' => 'Site visit', 'meeting_format' => 'in_person', 'location_note' => '12 Dock Road, Durban', 'preparation' => 'Gate code 5566']);

        $this->get(route('business.book', $this->team))->assertOk()->assertSee('12 Dock Road, Durban')->assertDontSee('Gate code 5566');
    }

    public function test_the_booker_sees_how_to_join_and_what_to_prepare_on_their_own_booking(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())->assertRedirect();
        $booking = Booking::sole();

        $this->actingAs($this->customer)->get(route('my-bookings.show', $booking->reference))->assertOk()->assertSee('https://meet.example/abc passcode 4821')->assertSee('Door code is 9911.');
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get(route('my-bookings.show', $booking->reference))->assertNotFound();
    }

    // ------------------------------------------------------------------------------------------------------------------- L1, L7: inputs

    public function test_array_and_junk_query_values_never_give_a_server_error(): void
    {
        $this->actingAs($this->customer);

        $this->get(route('business.book.confirm', $this->team).'?type=1&start[]=x')->assertNotFound();
        $this->assertLessThan(500, $this->get(route('business.book.confirm', $this->team).'?type[]=1&start='.self::START)->getStatusCode(), 'an array where a number belongs is read as a number, never an error');
        $this->get(route('business.book', $this->team).'?type[]=1&month[]=x&date[]=y')->assertOk();
    }

    public function test_a_provider_cannot_be_picked_or_probed_by_id(): void
    {
        $provider = $this->team->bookingProviders()->create(['user_id' => User::factory()->create()->id, 'public_name' => 'Thandi']);
        $this->online->providers()->attach($provider->id);
        $this->actingAs($this->customer);

        $base = route('business.book.confirm', [$this->team, 'type' => $this->online->id, 'start' => self::START]);
        $this->assertSame($this->get($base)->getStatusCode(), $this->get($base.'&provider=999')->getStatusCode());
        $this->get($base)->assertDontSee('name="provider"', false);

        // A posted provider id is ignored: the engine chooses.
        $this->post(route('business.book.store', $this->team), $this->form(['provider' => 999]))->assertRedirect();
        $this->assertSame($provider->id, Booking::sole()->booking_provider_id);

        $this->get(route('business.book', [$this->team, 'type' => $this->online->id, 'date' => '2026-11-30']))->assertOk()->assertDontSee('provider=', false);
    }

    // ------------------------------------------------------------------------------------------------------------------- terms, references, the lock

    public function test_the_acceptance_of_the_terms_is_recorded_with_the_booking(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())->assertRedirect();

        $this->assertNotNull(Booking::sole()->terms_accepted_at);
        $this->assertEquals(now()->toDateTimeString(), Booking::sole()->terms_accepted_at->toDateTimeString());
    }

    public function test_a_business_without_terms_records_none(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['terms' => null]);
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form(['terms_accepted' => null]))->assertRedirect();

        $this->assertNull(Booking::sole()->terms_accepted_at);
    }

    public function test_references_are_random_and_do_not_reveal_how_many_bookings_exist(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())->assertRedirect();
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']))->assertRedirect();

        [$a, $b] = Booking::orderBy('id')->pluck('reference')->all();
        $this->assertNotSame($a, $b);
        foreach ([$a, $b] as $reference) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-B-[A-HJ-NP-Z2-9]{10}$/', $reference);
        }
    }

    public function test_the_engine_gives_the_same_answer_when_asked_for_a_locking_read(): void
    {
        $engine = app(Availability::class);
        $start = CarbonImmutable::parse('2026-11-30 07:00:00', 'UTC');

        $this->assertNotNull($engine->find($this->online, $start, null, now()));
        $this->assertNotNull($engine->find($this->online, $start, null, now(), null, true));

        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())->assertRedirect();

        $this->assertNull($engine->find($this->online, $start, null, now()));
        $this->assertNull($engine->find($this->online, $start, null, now(), null, true));
    }

    // ------------------------------------------------------------------------------------------------------------------- M2: account deletion

    public function test_deleting_an_account_cancels_future_bookings_and_blanks_the_personal_details(): void
    {
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form())->assertRedirect();
        $this->actingAs($this->customer)->post(route('business.book.store', $this->team), $this->form(['start' => '2026-11-30T09:00:00Z']))->assertRedirect();
        $past = Booking::query()->orderBy('id')->first();
        $past->forceFill(['starts_at' => '2026-10-05 07:00:00', 'ends_at' => '2026-10-05 08:00:00', 'blocks_from' => '2026-10-05 07:00:00', 'blocks_until' => '2026-10-05 08:00:00', 'status' => Booking::COMPLETED])->save();
        $future = Booking::query()->orderByDesc('id')->first();

        app(DeleteUser::class)->delete($this->customer);

        $this->assertNull(User::find($this->customer->id));
        $this->assertSame(Booking::CANCELLED_BY_CUSTOMER, $future->fresh()->status, 'an upcoming booking no longer holds the time');
        $this->assertSame(Booking::COMPLETED, $past->fresh()->status, 'a past appointment stays a record of the business');

        foreach ([$past->fresh(), $future->fresh()] as $booking) {
            $this->assertSame(['[deleted account]', 'deleted@invalid', null, null, null, null], [$booking->customer_name, $booking->customer_email, $booking->customer_phone, $booking->customer_company, $booking->reason, $booking->customer_user_id]);
        }

        $this->assertDatabaseHas('booking_events', ['type' => 'booking_cancelled_account_deleted', 'team_id' => $this->team->id]);
        $this->assertStringNotContainsString('Zodwa', json_encode(BookingEvent::all()->toArray()));
        $this->assertNotNull(app(Availability::class)->find($this->online, CarbonImmutable::parse('2026-11-30 09:00:00', 'UTC'), null, now()), 'the time is free again');
    }
}
