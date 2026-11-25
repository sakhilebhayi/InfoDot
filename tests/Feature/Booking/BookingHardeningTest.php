<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\TeamProfile;
use App\Services\Booking\BookingBoard;
use App\Services\Booking\BookingReport;
use App\Services\Booking\BookingRetention;
use App\Services\Privacy\BookingDataEraser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/** R-63 phase 7: cost limits on the public calendar, the indexed history, the retention rule, the personal data export and the report fixes. */
class BookingHardeningTest extends AppointmentsTestCase
{
    use BuildsBookingBusinesses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'We move freight.'])->save();
    }

    private function calendar(string $query = '')
    {
        return $this->get('/business/'.$this->team->uuid.'/book?type='.$this->type->id.$query);
    }

    public function test_the_public_calendar_is_kept_for_a_few_seconds_and_a_booking_is_still_checked_fresh(): void
    {
        config(['booking.public.cache_seconds' => 20]);
        Cache::flush();
        $day = '2026-11-23';

        $first = $this->calendar('&date='.$day)->assertOk()->getContent();
        $this->assertStringContainsString('09:00', $first);

        // Someone books 09:00 and 09:00 for both providers; the calendar page may be a few seconds old...
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->b);
        $this->assertStringContainsString('09:00', $this->calendar('&date='.$day)->getContent());

        // ...but the confirm step asks the engine afresh and refuses the time that has gone.
        $this->actingAs($this->stranger())->get('/business/'.$this->team->uuid.'/book/confirm?type='.$this->type->id.'&start=2026-11-23T07:00:00Z')
            ->assertRedirect();

        $this->travel(30)->seconds();
        $this->assertStringNotContainsString('>09:00<', preg_replace('/\s+/', '', $this->calendar('&date='.$day)->getContent()) ?? '');
    }

    public function test_one_businesss_calendar_works_out_a_fixed_number_of_fresh_answers_a_minute(): void
    {
        config(['booking.public.team_views_per_minute' => 2, 'booking.public.cache_seconds' => 0]);
        RateLimiter::clear('book-view:'.$this->team->id);

        $this->calendar('&month=2026-11')->assertOk();
        $this->calendar('&month=2026-12')->assertOk();
        $this->calendar('&month=2027-01')->assertStatus(429);
        $this->get('/business/'.$this->team->uuid.'/book')->assertOk(); // a page with nothing to work out is never refused
    }

    public function test_answers_served_from_the_kept_copy_do_not_use_up_the_allowance(): void
    {
        config(['booking.public.team_views_per_minute' => 1, 'booking.public.cache_seconds' => 20]);
        Cache::flush();
        RateLimiter::clear('book-view:'.$this->team->id);

        foreach (range(1, 5) as $i) {
            $this->calendar('&month=2026-11')->assertOk();
        }
        $this->calendar('&month=2026-12')->assertStatus(429);
    }

    public function test_an_appointments_history_is_found_by_its_own_indexed_column(): void
    {
        $booking = $this->mk('2026-11-23 07:00:00', Booking::PENDING, $this->a);
        $other = $this->mk('2026-11-23 09:00:00', Booking::PENDING, $this->b);
        BookingEvent::create(['team_id' => $this->team->id, 'actor_id' => null, 'type' => 'booking_rejected', 'details' => ['booking_id' => $booking->id]]);
        BookingEvent::create(['team_id' => $this->team->id, 'actor_id' => null, 'type' => 'booking_expired', 'details' => ['booking_id' => $other->id]]);

        $this->assertSame([$booking->id, $other->id], BookingEvent::orderBy('id')->pluck('booking_id')->all());

        $this->actingAs($this->owner)->get($this->url('/'.$booking->id))->assertOk()->assertSee('Rejected');
        $this->assertSame(1, BookingEvent::where('team_id', $this->team->id)->where('booking_id', $booking->id)->count());
    }

    public function test_the_migration_fills_the_column_for_events_that_already_exist(): void
    {
        $booking = $this->mk('2026-11-23 07:00:00', Booking::PENDING, $this->a);
        $id = DB::table('booking_events')->insertGetId(['team_id' => $this->team->id, 'type' => 'booking_expired', 'details' => json_encode(['booking_id' => $booking->id]), 'created_at' => now()]);
        $this->assertNull(DB::table('booking_events')->find($id)->booking_id);

        (require base_path('database/migrations/2026_11_25_160000_add_booking_id_to_booking_events.php'))->up();

        $this->assertSame($booking->id, (int) DB::table('booking_events')->find($id)->booking_id);
    }

    public function test_the_retention_rule_removes_personal_details_after_the_period_and_keeps_the_appointment(): void
    {
        $old = $this->mk('2024-10-01 07:00:00', Booking::COMPLETED, $this->a, ['customer_user_id' => $this->stranger()->id, 'internal_notes' => 'Prefers mornings', 'status_note' => 'See you', 'answers' => ['x' => 'y']]);
        $recent = $this->mk('2026-09-01 07:00:00', Booking::COMPLETED, $this->a);
        $edge = $this->mk('2024-11-20 09:00:00', Booking::COMPLETED, $this->a); // ends 10:00Z, just inside the 24 months

        $this->assertSame(1, app(BookingRetention::class)->run());

        $old->refresh();
        $this->assertSame(BookingDataEraser::NAME, $old->customer_name);
        $this->assertSame(BookingDataEraser::EMAIL, $old->customer_email);
        $this->assertNull($old->customer_phone);
        $this->assertNull($old->reason);
        $this->assertNull($old->internal_notes);
        $this->assertNull($old->status_note);
        $this->assertNull($old->customer_user_id, 'no link to the account is kept');
        $this->assertSame(Booking::COMPLETED, $old->status);
        $this->assertSame($this->a->id, $old->booking_provider_id);
        $this->assertSame('Zodwa Secret', $recent->fresh()->customer_name);
        $this->assertNotSame(BookingDataEraser::NAME, $edge->fresh()->customer_name === BookingDataEraser::NAME ? 'x' : $edge->fresh()->customer_name);

        $this->assertSame(0, app(BookingRetention::class)->run(), 'a second run finds nothing more');
        $this->artisan('booking:retention')->expectsOutput('Cleaned: 0')->assertSuccessful();
    }

    public function test_an_account_deletion_still_keeps_the_business_notes(): void
    {
        $booking = $this->mk('2026-09-01 07:00:00', Booking::COMPLETED, $this->a, ['internal_notes' => 'A note for us']);
        app(BookingDataEraser::class)->scrub($booking);

        $this->assertSame('A note for us', $booking->fresh()->internal_notes);
    }

    public function test_a_person_can_download_what_booking_holds_about_them_and_nobody_elses(): void
    {
        $customer = $this->stranger();
        $mine = $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a, ['customer_user_id' => $customer->id, 'customer_name' => 'Mine Person', 'internal_notes' => 'Business only', 'status_note' => 'See you then', 'answers' => ['Parking' => 'Yes']]);
        $this->mk('2026-11-23 09:00:00', Booking::CONFIRMED, $this->b, ['customer_user_id' => $this->stranger()->id, 'customer_name' => 'Someone Else']);

        $response = $this->actingAs($customer)->get('/my-bookings/export')->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $data = json_decode($body, true);

        $this->assertCount(1, $data['appointments_booked']);
        $this->assertSame($mine->reference, $data['appointments_booked'][0]['reference']);
        $this->assertSame('Mine Person', $data['appointments_booked'][0]['name_given']);
        $this->assertSame('See you then', $data['appointments_booked'][0]['message_from_business']);
        $this->assertSame(['Parking' => 'Yes'], $data['appointments_booked'][0]['answers_given']);
        $this->assertStringNotContainsString('Business only', $body);
        $this->assertStringNotContainsString('Someone Else', $body);

        $provider = $this->actingAs($this->thandi)->get('/my-bookings/export')->assertOk()->streamedContent();
        $this->assertSame('Thandi', json_decode($provider, true)['provider_records'][0]['public_name']);
    }

    public function test_the_export_needs_a_signed_in_person_and_works_even_when_the_feature_is_off(): void
    {
        $this->get('/my-bookings/export')->assertRedirect('/login');
        $this->feature('development');
        $this->actingAs($this->stranger())->get('/my-bookings/export')->assertOk();
    }

    public function test_utilisation_ignores_time_booked_on_a_provider_who_is_switched_off(): void
    {
        $this->mk('2026-11-23 07:00:00', Booking::CONFIRMED, $this->a);
        $this->mk('2026-11-23 08:00:00', Booking::CONFIRMED, $this->b);
        $this->b->update(['active' => false]);

        $u = app(BookingReport::class)->build(BookingBoard::for($this->owner, $this->team), $this->team, '2026-11-23', '2026-11-24')['utilisation'];

        $this->assertSame(60, $u['booked']);
        $this->assertSame(960, $u['offered']);
    }
}
