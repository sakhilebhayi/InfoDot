<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-63 phase 3, the public calendar. "Now" is Friday 20 November 2026, 10:00 in Johannesburg. Mondays are open 09:00 to 17:00 local, so Monday 30 November
 * has start times 07:00Z, 08:00Z ... 14:00Z (09:00 to 16:00 local), hourly.
 */
class PublicBookingPageTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        [$this->team] = $this->business('available');
        $this->team->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'We move freight.'])->save();
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'auto', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 0, 'horizon_days' => 365, 'instructions' => "Bring ID.\nArrive early.", 'terms' => null]);
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->type = $this->type($this->team, ['name' => 'Initial consultation', 'description' => 'A first talk.', 'fee_cents' => 25050, 'currency' => 'ZAR', 'location_note' => 'Room 4', 'preparation' => 'Bring a statement.']);
    }

    private function url(array $q = []): string
    {
        return route('business.book', [$this->team] + $q);
    }

    public function test_every_new_route_is_a_404_until_the_feature_is_available(): void
    {
        $this->feature('development');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->get($this->url())->assertNotFound();
        $this->actingAs($user)->get(route('business.book.confirm', [$this->team, 'type' => $this->type->id, 'start' => '2026-11-30T07:00:00Z']))->assertNotFound();
        $this->actingAs($user)->post(route('business.book.store', $this->team), [])->assertNotFound();
        $this->actingAs($user)->get(route('my-bookings'))->assertNotFound();
        $this->actingAs($user)->get(route('my-bookings.show', 'X-B-000001'))->assertNotFound();
        $this->actingAs($user)->post(route('my-bookings.cancel', 'X-B-000001'))->assertNotFound();
    }

    public function test_a_hidden_business_is_a_404_to_outsiders_and_members_only_needs_a_sign_in(): void
    {
        $this->team->profile->forceFill(['visibility' => TeamProfile::MEMBERS])->save();
        $this->get($this->url())->assertNotFound();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($this->url())->assertOk();

        $this->team->profile->forceFill(['visibility' => TeamProfile::PRIVATE])->save();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($this->url())->assertNotFound();
    }

    public function test_a_guest_sees_the_types_with_their_details_and_no_calendar_until_one_is_chosen(): void
    {
        $this->type($this->team, ['name' => 'Switched off', 'active' => false]);

        $this->get($this->url())->assertOk()
            ->assertSee('Dlamini Freight')->assertSee('Initial consultation')->assertSee('A first talk.')->assertSee('60 minutes')
            ->assertSee('ZAR 250.50')->assertSee('Room 4')->assertSee('Bring a statement.')->assertSee('Bring ID.')->assertSee('Arrive early.', false)
            ->assertSee('Africa/Johannesburg time')->assertSee('Choose')->assertDontSee('Switched off')->assertDontSee('Legend')->assertDontSee('Earlier month');
    }

    public function test_the_calendar_shows_each_of_the_five_statuses_with_text(): void
    {
        // Monday 7 December: fully booked. Monday 14 December: a holiday closure. Monday 30 November: open. Tuesdays: not offered. Past dates: outside the window.
        $start = CarbonImmutable::parse('2026-12-07 07:00:00', 'UTC');
        foreach (range(0, 7) as $h) {
            Booking::create(['team_id' => $this->team->id, 'appointment_type_id' => $this->type->id, 'customer_name' => 'X', 'customer_email' => 'x@example.test', 'starts_at' => $start->addHours($h), 'ends_at' => $start->addHours($h + 1), 'blocks_from' => $start->addHours($h), 'blocks_until' => $start->addHours($h + 1), 'status' => Booking::CONFIRMED]);
        }
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'starts_at' => '2026-12-13 22:00:00', 'ends_at' => '2026-12-14 22:00:00', 'reason' => 'Holiday']);

        $november = $this->get($this->url(['type' => $this->type->id, 'month' => '2026-11']))->assertOk();
        $november->assertSee('November 2026')->assertSee('Open')->assertSee('Not offered')->assertSee('Mon')->assertSee('Sun');
        $november->assertSee('href="'.e($this->url(['type' => $this->type->id, 'date' => '2026-11-30'])).'"', false);
        $november->assertDontSee('date=2026-11-16', false);   // a past Monday is not a link

        $december = $this->get($this->url(['type' => $this->type->id, 'month' => '2026-12']))->assertOk();
        $december->assertSee('Full')->assertSee('Closed')->assertDontSee('date=2026-12-07', false)->assertDontSee('date=2026-12-14', false)->assertSee('date=2026-12-21', false);
    }

    public function test_month_and_date_are_bounded(): void
    {
        $this->get($this->url(['type' => $this->type->id, 'month' => '2020-01']))->assertOk()->assertSee('November 2026')->assertDontSee('Earlier month');
        $this->get($this->url(['type' => $this->type->id, 'month' => '2099-01']))->assertOk()->assertSee('January 2028')->assertDontSee('Later month');
        $this->get($this->url(['type' => $this->type->id, 'month' => 'garbage']))->assertOk()->assertSee('November 2026');
        $this->get($this->url(['type' => $this->type->id, 'date' => '2026-02-31']))->assertOk()->assertDontSee('Book 09:00');
        $this->get($this->url(['type' => $this->type->id, 'date' => '2019-11-04']))->assertOk()->assertSee('November 2026')->assertDontSee('Book 09:00');
        $this->get($this->url(['type' => 'abc']))->assertOk()->assertSee('Choose');
    }

    public function test_a_date_lists_its_slots_in_the_business_zone_with_the_instant_beside_them(): void
    {
        $page = $this->get($this->url(['type' => $this->type->id, 'date' => '2026-11-30']))->assertOk();

        $page->assertSee('Monday 30 November 2026')->assertSee('09:00')->assertSee('16:00')->assertDontSee('17:00');
        $page->assertSee('<time datetime="2026-11-30T07:00:00Z" data-show="time">09:00</time>', false);
        $page->assertSee('x-data="{ biz: \'Africa/Johannesburg\' }"', false);
        $page->assertSee(e(route('business.book.confirm', [$this->team, 'type' => $this->type->id, 'start' => '2026-11-30T07:00:00Z'])), false);
    }

    public function test_a_closed_or_full_date_says_so_instead_of_listing_slots(): void
    {
        BookingClosure::forceCreate(['team_id' => $this->team->id, 'starts_at' => '2026-12-13 22:00:00', 'ends_at' => '2026-12-14 22:00:00']);
        $this->get($this->url(['type' => $this->type->id, 'date' => '2026-12-14']))->assertOk()->assertSee('is closed on this date')->assertDontSee('task_alt');
        $this->get($this->url(['type' => $this->type->id, 'date' => '2026-12-01']))->assertOk()->assertSee('not offered on this weekday');
    }

    public function test_a_business_not_taking_bookings_shows_the_message_and_no_calendar(): void
    {
        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => false]);
        $this->get($this->url(['type' => $this->type->id]))->assertOk()->assertSee('is not taking appointments through InfoDot right now')->assertDontSee('Initial consultation')->assertDontSee('November 2026');

        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => true, 'calendar_published' => false]);
        $this->get($this->url(['type' => $this->type->id, 'date' => '2026-11-30']))->assertOk()->assertSee('is not taking appointments')->assertDontSee('Book 09:00')->assertDontSee('2026-11-30T07');
    }

    public function test_no_private_data_or_counts_appear_for_a_guest_a_stranger_or_the_booker(): void
    {
        $booker = User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Quillfeather', 'email' => 'zodwa.quillfeather@example.test']);
        $start = CarbonImmutable::parse('2026-11-30 08:00:00', 'UTC');
        Booking::create(['team_id' => $this->team->id, 'appointment_type_id' => $this->type->id, 'customer_user_id' => $booker->id, 'customer_name' => 'Zodwa Quillfeather', 'customer_email' => 'zodwa.quillfeather@example.test', 'customer_phone' => '0829990001',
            'customer_company' => 'Quillfeather Traders', 'reason' => 'Distinctive private reason', 'starts_at' => $start, 'ends_at' => $start->addHour(), 'blocks_from' => $start, 'blocks_until' => $start->addHour(), 'status' => Booking::CONFIRMED]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        foreach ([null, $stranger, $booker] as $who) {
            foreach ([['type' => $this->type->id, 'date' => '2026-11-30'], ['type' => $this->type->id, 'month' => '2026-11'], []] as $query) {
                $html = ($who ? $this->actingAs($who) : $this)->get($this->url($query))->assertOk()->getContent();
                // The booker's own name is in the signed-in account menu, which is not the page; the page itself is what must stay clean.
                preg_match('~<main.*?</main>~s', $html, $main);
                $html = $main[0];
                foreach (['Quillfeather', 'zodwa', '0829990001', 'Distinctive private reason', 'data-booking', 'data-customer'] as $needle) {
                    $this->assertStringNotContainsStringIgnoringCase($needle, $html, $needle.' leaked');
                }
                $this->assertSame(0, preg_match('/\b\d+\s+(bookings?|booked|people|customers?|appointments? (taken|made))\b/i', strip_tags($html)), 'a count leaked');
            }
        }

        // The booked 10:00 is simply not offered.
        $this->get($this->url(['type' => $this->type->id, 'date' => '2026-11-30']))->assertDontSee('2026-11-30T08:00:00Z')->assertSee('2026-11-30T07:00:00Z');
    }

    public function test_the_public_page_has_its_own_throttle_bucket_and_the_post_routes_theirs(): void
    {
        foreach (['business.book' => 'throttle:60,1,business.book.view', 'business.book.store' => 'throttle:10,1,business.book.store', 'my-bookings.cancel' => 'throttle:10,1,my-bookings.cancel'] as $name => $middleware) {
            $this->assertContains($middleware, app('router')->getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_the_book_button_shows_only_when_a_customer_could_really_book(): void
    {
        $show = fn () => route('business.show', $this->team);
        $this->get($show())->assertOk()->assertSee('Book an appointment')->assertSee(route('business.book', $this->team), false);

        // A signed-in stranger sees it; a member does not; the preview shows it disabled.
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($show())->assertSee('Book an appointment');
        $employee = $this->member($this->team, 'editor');
        $this->actingAs($employee)->get($show())->assertDontSee('Book an appointment');
        $owner = User::find($this->team->user_id);
        $this->actingAs($owner)->get($show())->assertDontSee('Book an appointment');
        $this->actingAs($owner)->get(route('business.show', [$this->team, 'as' => 'visitor']))->assertSee('Book an appointment')->assertDontSee(route('business.book', $this->team), false);

        // No active type.
        $this->type->update(['active' => false]);
        $this->get($show())->assertDontSee('Book an appointment');
        $this->actingAs($owner)->get(route('business.show', [$this->team, 'as' => 'visitor']))->assertDontSee('Book an appointment');
        $this->type->update(['active' => true]);

        // Bookings off, calendar hidden, feature off.
        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => false]);
        $this->get($show())->assertDontSee('Book an appointment');
        BookingSetting::where('team_id', $this->team->id)->update(['enabled' => true, 'calendar_published' => false]);
        $this->get($show())->assertDontSee('Book an appointment');
        BookingSetting::where('team_id', $this->team->id)->update(['calendar_published' => true]);
        $this->feature('development');
        $this->get($show())->assertDontSee('Book an appointment');
    }

    public function test_no_stylesheet_or_build_file_was_touched_by_this_phase(): void
    {
        $changed = trim((string) shell_exec('cd '.escapeshellarg(base_path()).' && git diff --name-only ef5b759 -- resources/css public/build 2>/dev/null'));
        $this->assertSame('', $changed);
    }
}
