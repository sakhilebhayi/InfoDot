<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Enquiry;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\Privacy\BookingDataEraser;
use App\Services\Privacy\BookingDataExporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-66 phase 2: a booking or a message from a company member says which of their companies it is for, checked against their own companies. */
class BookingCompanyTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private AppointmentType $online;

    private User $customer;

    private Team $theirs;

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

    /** The customer's own company, which they work in. */
    private function ownCompany(string $name = 'Mokoena Logistics'): Team
    {
        $company = Team::factory()->create(['user_id' => $this->customer->id, 'personal_team' => false, 'name' => $name]);
        $company->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'Trucks.'])->save();
        $this->customer->forceFill(['current_team_id' => $company->id])->save();

        return $company;
    }

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + ['type' => $this->online->id, 'start' => self::START, 'name' => 'Zodwa Mokoena', 'email' => 'zodwa@example.test', 'terms_accepted' => '1'];
    }

    public function test_the_confirm_step_offers_the_persons_own_companies_with_the_current_one_chosen(): void
    {
        $company = $this->ownCompany();
        $confirm = route('business.book.confirm', [$this->team, 'type' => $this->online->id, 'start' => self::START]);

        $html = $this->actingAs($this->customer->fresh())->get($confirm)->assertOk()->getContent();

        $this->assertStringContainsString('Booking for', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$company->id.'"\s+selected/', $html);
        $this->assertStringContainsString('Just me', $html);
        // With companies to choose from, the free-text business field is not offered.
        $this->assertStringNotContainsString('name="company"', $html);
    }

    public function test_someone_without_a_company_keeps_the_free_text_field_and_no_choice(): void
    {
        $confirm = route('business.book.confirm', [$this->team, 'type' => $this->online->id, 'start' => self::START]);

        $this->actingAs($this->customer)->get($confirm)->assertOk()->assertDontSee('Booking for')->assertSee('name="company"', false);
    }

    public function test_a_booking_for_a_company_is_linked_to_it_and_shown_to_both_sides(): void
    {
        $company = $this->ownCompany();

        $this->actingAs($this->customer->fresh())->post(route('business.book.store', $this->team), $this->form(['for_company' => (string) $company->id]))->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame($company->id, $booking->customer_team_id);
        $this->assertSame('Mokoena Logistics', $booking->customer_company);

        $this->actingAs($this->customer->fresh())->get(route('my-bookings.show', $booking->reference))->assertOk()
            ->assertSee('Booked for')->assertSee(route('business.show', $company), false);

        $owner = $this->team->owner;
        $this->actingAs($owner)->get(route('business.appointments.show', [$this->team, $booking]))->assertOk()
            ->assertSee('Mokoena Logistics')->assertSee(route('business.show', $company), false);
    }

    public function test_just_me_books_without_a_company(): void
    {
        $this->ownCompany();

        $this->actingAs($this->customer->fresh())->post(route('business.book.store', $this->team), $this->form(['for_company' => 'me']))->assertRedirect();

        $this->assertNull(Booking::sole()->customer_team_id);
        $this->assertNull(Booking::sole()->customer_company);
    }

    public function test_a_company_the_person_does_not_belong_to_is_refused(): void
    {
        $this->ownCompany();
        $stranger = Team::factory()->create(['personal_team' => false, 'name' => 'Not Theirs']);

        $this->actingAs($this->customer->fresh())->from(route('business.book', $this->team))
            ->post(route('business.book.store', $this->team), $this->form(['for_company' => (string) $stranger->id]))
            ->assertSessionHasErrors('for_company');

        $this->assertSame(0, Booking::count());
    }

    public function test_the_business_being_booked_cannot_be_named_as_the_customer_company(): void
    {
        $this->ownCompany();

        $this->actingAs($this->customer->fresh())->post(route('business.book.store', $this->team), $this->form(['for_company' => (string) $this->team->id]))
            ->assertSessionHasErrors('for_company');

        $this->assertSame(0, Booking::count());
    }

    public function test_a_message_for_a_company_is_linked_and_the_business_inbox_shows_it(): void
    {
        $company = $this->ownCompany();

        $this->actingAs($this->customer->fresh())->get(route('business.message', $this->team))->assertOk()->assertSee('Sending for');

        $this->actingAs($this->customer->fresh())->post(route('business.message.store', $this->team), [
            'category' => 'general', 'subject' => 'Weekly loads', 'body' => 'We would like to talk about moving weekly loads to Durban.', 'for_company' => (string) $company->id,
        ])->assertRedirect();

        $enquiry = Enquiry::sole();
        $this->assertSame($company->id, $enquiry->customer_team_id);

        $owner = $this->team->owner;
        $this->actingAs($owner)->get(route('business.inbox.show', [$this->team, $enquiry]))->assertOk()
            ->assertSee('for', false)->assertSee(route('business.show', $company), false);
        $this->actingAs($owner)->get(route('business.inbox', $this->team))->assertOk()->assertSee('(Mokoena Logistics)', false);
    }

    public function test_a_message_cannot_claim_a_company_the_person_does_not_belong_to(): void
    {
        $this->ownCompany();
        $stranger = Team::factory()->create(['personal_team' => false]);

        $this->actingAs($this->customer->fresh())->post(route('business.message.store', $this->team), [
            'category' => 'general', 'subject' => 'Weekly loads', 'body' => 'We would like to talk about moving weekly loads to Durban.', 'for_company' => (string) $stranger->id,
        ])->assertSessionHasErrors('for_company');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_the_export_names_the_company_and_erasure_removes_the_link(): void
    {
        $company = $this->ownCompany();
        $this->actingAs($this->customer->fresh())->post(route('business.book.store', $this->team), $this->form(['for_company' => (string) $company->id]))->assertRedirect();

        $export = app(BookingDataExporter::class)->export($this->customer->fresh());
        $this->assertSame('Mokoena Logistics', $export['appointments_booked'][0]['booked_for_company']);

        app(BookingDataEraser::class)->scrub(Booking::sole());
        $this->assertNull(Booking::sole()->customer_team_id);
        $this->assertNull(Booking::sole()->customer_company);
    }
}
