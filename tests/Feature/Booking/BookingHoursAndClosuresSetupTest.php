<?php

namespace Tests\Feature\Booking;

use App\Models\BookingClosure;
use App\Models\BookingEvent;
use App\Models\WorkingHour;
use App\Services\Booking\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** R-63 phase 2b: weekly hours and closures, who may set them, how times are stored, and that the availability engine sees what was saved. */
class BookingHoursAndClosuresSetupTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-11-25 10:00:00');
        Carbon::setTestNow('2026-11-25 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- gate, permissions, isolation ---------------------------------------------------------------------------------------

    public function test_every_screen_is_a_404_until_the_feature_is_open(): void
    {
        [$team, $owner] = $this->business('development');
        $provider = $this->provider($team, $this->member($team, 'member'));
        $closure = BookingClosure::forceCreate(['team_id' => $team->id, 'kind' => 'block', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $this->actingAs($owner);

        foreach ([
            ['get', route('business.booking.hours', $team)], ['post', route('business.booking.hours.update', $team)],
            ['get', route('business.booking.providers.hours', [$team, $provider])], ['post', route('business.booking.providers.hours.update', [$team, $provider])],
            ['get', route('business.booking.closures', $team)], ['post', route('business.booking.closures.store', $team)], ['post', route('business.booking.closures.delete', [$team, $closure])],
        ] as [$method, $url]) {
            $this->{$method}($url)->assertNotFound();
        }

        $this->assertSame(0, WorkingHour::count());
        $this->assertNotNull($closure->fresh());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        [$team] = $this->business();

        $this->get(route('business.booking.hours', $team))->assertRedirect(route('login'));
        $this->post(route('business.booking.closures.store', $team))->assertRedirect(route('login'));
    }

    public function test_business_hours_are_for_managers_only_and_a_provider_member_is_refused(): void
    {
        [$team, $owner] = $this->business();
        $admin = $this->member($team, 'admin', 'Ann Admin');
        $manager = $this->member($team, 'manager', 'Mo Manager');
        $thandi = $this->member($team, 'member');
        $this->provider($team, $thandi);
        $plain = $this->member($team, 'member', 'Pat Plain');
        [, $outsider] = $this->business('available', 'Other Co');

        foreach ([$owner, $admin, $manager] as $person) {
            $this->actingAs($person)->get(route('business.booking.hours', $team))->assertOk();
        }

        foreach ([$thandi, $plain, $outsider] as $person) {
            $this->actingAs($person)->get(route('business.booking.hours', $team))->assertForbidden();
            $this->actingAs($person)->post(route('business.booking.hours.update', $team), $this->week())->assertForbidden();
        }

        $this->assertSame(0, WorkingHour::count());
    }

    public function test_a_provider_manages_only_their_own_hours(): void
    {
        [$team, $owner] = $this->business();
        $manager = $this->member($team, 'manager', 'Mo Manager');
        $thandi = $this->member($team, 'member');
        $own = $this->provider($team, $thandi);
        $otherPerson = $this->member($team, 'member', 'Sipho Dube');
        $theirs = $this->provider($team, $otherPerson, 'Sipho');
        $plain = $this->member($team, 'member', 'Pat Plain');
        [, $outsider] = $this->business('available', 'Other Co');

        $this->actingAs($thandi)->get(route('business.booking.providers.hours', [$team, $own]))->assertOk();
        $this->actingAs($thandi)->post(route('business.booking.providers.hours.update', [$team, $own]), $this->week())->assertRedirect();
        $this->assertSame(2, WorkingHour::where('booking_provider_id', $own->id)->count());

        $this->actingAs($thandi)->get(route('business.booking.providers.hours', [$team, $theirs]))->assertForbidden();
        $this->actingAs($thandi)->post(route('business.booking.providers.hours.update', [$team, $theirs]), $this->week())->assertForbidden();
        $this->actingAs($plain)->get(route('business.booking.providers.hours', [$team, $own]))->assertForbidden();
        $this->actingAs($outsider)->get(route('business.booking.providers.hours', [$team, $own]))->assertForbidden();
        $this->assertSame(0, WorkingHour::where('booking_provider_id', $theirs->id)->count());

        foreach ([$owner, $manager] as $person) {
            $this->actingAs($person)->post(route('business.booking.providers.hours.update', [$team, $theirs]), $this->week())->assertRedirect();
        }
        $this->assertSame(2, WorkingHour::where('booking_provider_id', $theirs->id)->count());
    }

    public function test_a_provider_screen_shows_only_their_tabs_and_the_business_page_links_to_it(): void
    {
        [$team] = $this->business();
        $thandi = $this->member($team, 'member');
        $own = $this->provider($team, $thandi);

        $page = $this->actingAs($thandi)->get(route('business.booking.providers.hours', [$team, $own]))->assertOk();
        $page->assertSee('My hours')->assertDontSee(route('business.booking.types', $team), false)->assertDontSee(route('business.booking.hours', $team), false);
        $this->actingAs($thandi)->get(route('business.show', $team))->assertSee(route('business.booking.providers.hours', [$team, $own]), false);
    }

    public function test_a_record_from_another_business_is_a_404_on_every_route(): void
    {
        [$team, $owner] = $this->business();
        [$other] = $this->business('available', 'Other Co');
        $foreignProvider = $this->provider($other, $this->member($other, 'member'));
        $foreignClosure = BookingClosure::forceCreate(['team_id' => $other->id, 'kind' => 'block', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $this->actingAs($owner);

        $this->get(route('business.booking.providers.hours', [$team, $foreignProvider]))->assertNotFound();
        $this->post(route('business.booking.providers.hours.update', [$team, $foreignProvider]), $this->week())->assertNotFound();
        $this->post(route('business.booking.closures.delete', [$team, $foreignClosure]))->assertNotFound();
        $this->post(route('business.booking.closures.store', $team), ['kind' => 'holiday', 'scope' => (string) $foreignProvider->id, 'starts_on' => '2026-12-25'])->assertSessionHasErrors('scope');

        $this->assertSame(0, WorkingHour::count());
        $this->assertNotNull($foreignClosure->fresh());
        $this->assertSame(1, BookingClosure::count());
    }

    // ---- hours --------------------------------------------------------------------------------------------------------------

    public function test_hours_are_saved_as_stretches_with_breaks_and_shown_again(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner);

        $this->get(route('business.booking.hours', $team))->assertOk()->assertSee('customers cannot book until you set them');
        $this->post(route('business.booking.hours.update', $team), ['hours' => [
            1 => [['from' => '13:00', 'to' => '17:00'], ['from' => '08:00', 'to' => '12:00'], ['from' => '', 'to' => '']],
            3 => [['from' => '09:30', 'to' => '10:00']],
        ]])->assertRedirect(route('business.booking.hours', $team))->assertSessionDoesntHaveErrors();

        $rows = WorkingHour::orderBy('weekday')->orderBy('starts_at')->get();
        $this->assertSame([[1, '08:00', '12:00'], [1, '13:00', '17:00'], [3, '09:30', '10:00']], $rows->map(fn ($r) => [$r->weekday, substr((string) $r->starts_at, 0, 5), substr((string) $r->ends_at, 0, 5)])->all());
        $this->assertSame([$team->id], $rows->pluck('team_id')->unique()->values()->all());
        $this->assertSame([null], $rows->pluck('booking_provider_id')->unique()->values()->all());

        $this->get(route('business.booking.hours', $team))->assertOk()->assertSee('value="08:00"', false)->assertSee('value="13:00"', false)->assertDontSee('customers cannot book until you set them');
    }

    public function test_saving_replaces_that_scope_only_and_an_empty_week_clears_it(): void
    {
        [$team, $owner] = $this->business();
        $provider = $this->provider($team, $this->member($team, 'member'));
        $this->actingAs($owner);

        $this->post(route('business.booking.hours.update', $team), $this->week());
        $this->post(route('business.booking.providers.hours.update', [$team, $provider]), $this->week(['4' => [['from' => '10:00', 'to' => '11:00']]]));
        $this->post(route('business.booking.hours.update', $team), ['hours' => [5 => [['from' => '07:00', 'to' => '15:00']]]]);

        $this->assertSame([5], WorkingHour::whereNull('booking_provider_id')->pluck('weekday')->all(), 'the business week was replaced');
        $this->assertSame(3, WorkingHour::where('booking_provider_id', $provider->id)->count(), "the provider's week was left alone");

        $this->post(route('business.booking.providers.hours.update', [$team, $provider]), []);
        $this->assertSame(0, WorkingHour::where('booking_provider_id', $provider->id)->count());
        $this->assertSame(1, WorkingHour::whereNull('booking_provider_id')->count());

        $this->get(route('business.booking.providers.hours', [$team, $provider]))->assertSee('works the business hours')->assertSee('count only where they fall inside', false);
    }

    public function test_bad_hours_are_refused_and_nothing_is_changed(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner);
        $this->post(route('business.booking.hours.update', $team), $this->week());

        foreach ([
            'overlap' => [1 => [['from' => '08:00', 'to' => '12:00'], ['from' => '11:00', 'to' => '15:00']]],
            'to before from' => [1 => [['from' => '12:00', 'to' => '08:00']]],
            'to equals from' => [1 => [['from' => '12:00', 'to' => '12:00']]],
            'past midnight' => [1 => [['from' => '20:00', 'to' => '24:00']]],
            'past midnight again' => [1 => [['from' => '20:00', 'to' => '02:00']]],
            'one blank' => [1 => [['from' => '08:00', 'to' => '']]],
            'not a time' => [1 => [['from' => 'morning', 'to' => '12:00']]],
            'seven stretches' => [1 => array_map(fn ($i) => ['from' => sprintf('%02d:00', $i * 3), 'to' => sprintf('%02d:30', $i * 3)], range(0, 6))],
        ] as $label => $week) {
            $this->post(route('business.booking.hours.update', $team), ['hours' => $week])->assertSessionHasErrors('hours');
            $this->assertSame(2, WorkingHour::count(), $label);
        }

        // Touching stretches are fine; six stretches are the most.
        $this->post(route('business.booking.hours.update', $team), ['hours' => [1 => [['from' => '08:00', 'to' => '12:00'], ['from' => '12:00', 'to' => '15:00']]]])->assertSessionDoesntHaveErrors();
        $this->post(route('business.booking.hours.update', $team), ['hours' => [1 => array_map(fn ($i) => ['from' => sprintf('%02d:00', $i * 3), 'to' => sprintf('%02d:30', $i * 3)], range(0, 5))]])->assertSessionDoesntHaveErrors();
        $this->assertSame(6, WorkingHour::count());
    }

    public function test_saved_hours_decide_what_the_availability_engine_offers_and_one_event_is_written(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team);
        $type = $this->type($team);
        $engine = app(Availability::class);
        $now = CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC');

        $this->assertCount(0, $engine->slots($type, '2026-11-30', null, $now));

        $this->actingAs($owner)->post(route('business.booking.hours.update', $team), ['hours' => [1 => [['from' => '09:00', 'to' => '12:00']]]]);

        $this->assertSame(['07:00', '08:00', '09:00'], $engine->slots($type, '2026-11-30', null, $now)->map(fn ($s) => $s->start->format('H:i'))->all());
        $event = BookingEvent::where('type', 'working_hours_replaced')->firstOrFail();
        $this->assertSame(['scope' => 'business', 'provider_id' => null, 'stretches' => 1], $event->details);
        $this->assertSame(1, BookingEvent::where('type', 'working_hours_replaced')->count());
    }

    public function test_a_providers_saved_hours_count_only_inside_the_business_hours(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team);
        $provider = $this->provider($team, $this->member($team, 'member'));
        $type = $this->type($team);
        $type->providers()->sync([$provider->id]);
        $now = CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC');
        $this->actingAs($owner);

        $this->post(route('business.booking.hours.update', $team), ['hours' => [1 => [['from' => '09:00', 'to' => '12:00']]]]);
        $this->assertCount(3, app(Availability::class)->slots($type, '2026-11-30', null, $now), 'no hours of their own: the business hours');

        $this->post(route('business.booking.providers.hours.update', [$team, $provider]), ['hours' => [1 => [['from' => '11:00', 'to' => '15:00']]]]);
        $this->assertSame(['09:00'], app(Availability::class)->slots($type, '2026-11-30', null, $now)->map(fn ($s) => $s->start->format('H:i'))->all());

        $event = BookingEvent::where('type', 'working_hours_replaced')->orderByDesc('id')->firstOrFail();
        $this->assertSame(['scope' => 'provider', 'provider_id' => $provider->id, 'stretches' => 1], $event->details);
    }

    // ---- closures -----------------------------------------------------------------------------------------------------------

    public function test_the_closures_page_lists_only_upcoming_ones_in_the_business_zone(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team);
        BookingClosure::forceCreate(['team_id' => $team->id, 'kind' => 'holiday', 'starts_at' => '2026-11-01 22:00:00', 'ends_at' => '2026-11-02 22:00:00', 'reason' => 'Long gone']);
        BookingClosure::forceCreate(['team_id' => $team->id, 'kind' => 'holiday', 'starts_at' => '2026-12-24 22:00:00', 'ends_at' => '2026-12-25 22:00:00', 'reason' => 'Christmas shutdown']);
        BookingClosure::forceCreate(['team_id' => $team->id, 'kind' => 'block', 'starts_at' => '2026-12-02 06:00:00', 'ends_at' => '2026-12-02 08:30:00']);

        $this->actingAs($owner)->get(route('business.booking.closures', $team))->assertOk()
            ->assertSee('Christmas shutdown')->assertDontSee('Long gone')
            ->assertSee('Fri 25 Dec 2026')->assertSee('(whole day)')->assertSee('Wed 2 Dec 2026 08:00')->assertSee('10:30')
            ->assertSee('customers cannot book anything');
    }

    public function test_a_holiday_becomes_whole_days_in_the_business_zone_with_the_end_excluded(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team, 'Africa/Johannesburg');
        $this->actingAs($owner)->post(route('business.booking.closures.store', $team), ['kind' => 'holiday', 'scope' => 'business', 'starts_on' => '2026-12-25', 'reason' => 'Christmas'])->assertSessionDoesntHaveErrors();

        $closure = BookingClosure::firstOrFail();
        $this->assertSame(['2026-12-24 22:00:00', '2026-12-25 22:00:00', 'holiday', null], [$closure->starts_at->utc()->format('Y-m-d H:i:s'), $closure->ends_at->utc()->format('Y-m-d H:i:s'), $closure->kind, $closure->booking_provider_id]);

        $this->post(route('business.booking.closures.store', $team), ['kind' => 'holiday', 'scope' => 'business', 'starts_on' => '2026-12-26', 'ends_on' => '2026-12-28', 'from' => '09:00', 'to' => '10:00']);
        $range = BookingClosure::latest('id')->first();
        $this->assertSame(['2026-12-25 22:00:00', '2026-12-28 22:00:00'], [$range->starts_at->utc()->format('Y-m-d H:i:s'), $range->ends_at->utc()->format('Y-m-d H:i:s')], 'times are ignored for a holiday; the last day is included');
    }

    public function test_a_holiday_follows_the_clocks_in_a_daylight_saving_zone(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team, 'America/New_York');
        $this->actingAs($owner)->post(route('business.booking.closures.store', $team), ['kind' => 'holiday', 'scope' => 'business', 'starts_on' => '2027-03-14']);

        $closure = BookingClosure::firstOrFail();
        // 14 March 2027 is the day New York's clocks go forward: it starts at 05:00Z (UTC-5) and the next day starts at 04:00Z (UTC-4), 23 hours later.
        $this->assertSame(['2027-03-14 05:00:00', '2027-03-15 04:00:00'], [$closure->starts_at->utc()->format('Y-m-d H:i:s'), $closure->ends_at->utc()->format('Y-m-d H:i:s')]);
    }

    public function test_blocked_time_is_stored_as_utc_and_may_span_days(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team, 'Africa/Johannesburg');
        $this->actingAs($owner);

        $this->post(route('business.booking.closures.store', $team), ['kind' => 'block', 'scope' => 'business', 'starts_on' => '2026-12-02', 'from' => '09:30', 'to' => '11:00'])->assertSessionDoesntHaveErrors();
        $this->post(route('business.booking.closures.store', $team), ['kind' => 'block', 'scope' => 'business', 'starts_on' => '2026-12-02', 'ends_on' => '2026-12-03', 'from' => '16:00', 'to' => '08:00'])->assertSessionDoesntHaveErrors();

        $same = BookingClosure::orderBy('id')->first();
        $span = BookingClosure::orderBy('id')->skip(1)->first();
        $this->assertSame(['2026-12-02 07:30:00', '2026-12-02 09:00:00'], [$same->starts_at->utc()->format('Y-m-d H:i:s'), $same->ends_at->utc()->format('Y-m-d H:i:s')]);
        $this->assertSame(['2026-12-02 14:00:00', '2026-12-03 06:00:00'], [$span->starts_at->utc()->format('Y-m-d H:i:s'), $span->ends_at->utc()->format('Y-m-d H:i:s')]);
    }

    public function test_closure_validation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$team, $owner] = $this->business();
        $this->settings($team);
        $this->actingAs($owner);
        $base = ['kind' => 'holiday', 'scope' => 'business', 'starts_on' => '2026-12-25'];

        foreach ([
            ['kind', 'party'], ['scope', 'everyone'], ['starts_on', ''], ['starts_on', '25-12-2026'], ['starts_on', '2026-11-20'], ['ends_on', '2026-12-24'], ['ends_on', '2027-12-26'],
            ['reason', str_repeat('a', 161)],
        ] as [$field, $value]) {
            $this->post(route('business.booking.closures.store', $team), [$field => $value] + $base)->assertSessionHasErrors();
        }

        $block = ['kind' => 'block', 'scope' => 'business', 'starts_on' => '2026-12-25', 'from' => '10:00', 'to' => '09:00'];
        $this->post(route('business.booking.closures.store', $team), $block)->assertSessionHasErrors('to');
        $this->post(route('business.booking.closures.store', $team), ['from' => '', 'to' => ''] + $block)->assertSessionHasErrors();
        $this->post(route('business.booking.closures.store', $team), ['from' => '10:00', 'to' => '10:00'] + $block)->assertSessionHasErrors('to');
        $this->assertSame(0, BookingClosure::count());

        // Exactly 366 days is allowed; 367 is not.
        $this->post(route('business.booking.closures.store', $team), $base + ['ends_on' => '2027-12-25'])->assertSessionDoesntHaveErrors();
        $this->post(route('business.booking.closures.store', $team), $base + ['ends_on' => '2027-12-26'])->assertSessionHasErrors('ends_on');
        $this->assertSame(1, BookingClosure::count());
    }

    public function test_closures_for_the_business_and_for_one_provider_have_their_own_permissions(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team);
        $manager = $this->member($team, 'manager', 'Mo Manager');
        $thandi = $this->member($team, 'member');
        $own = $this->provider($team, $thandi);
        $theirs = $this->provider($team, $this->member($team, 'member', 'Sipho Dube'), 'Sipho');
        $plain = $this->member($team, 'member', 'Pat Plain');
        [, $outsider] = $this->business('available', 'Other Co');
        $form = fn (string $scope) => ['kind' => 'holiday', 'scope' => $scope, 'starts_on' => '2026-12-25'];
        $url = route('business.booking.closures.store', $team);

        $this->actingAs($thandi)->post($url, $form((string) $own->id))->assertRedirect();
        $this->assertSame(1, BookingClosure::where('booking_provider_id', $own->id)->count());
        $this->actingAs($thandi)->post($url, $form('business'))->assertForbidden();
        $this->actingAs($thandi)->post($url, $form((string) $theirs->id))->assertForbidden();
        $this->actingAs($plain)->get(route('business.booking.closures', $team))->assertForbidden();
        $this->actingAs($plain)->post($url, $form('business'))->assertForbidden();
        $this->actingAs($outsider)->get(route('business.booking.closures', $team))->assertForbidden();
        $this->actingAs($outsider)->post($url, $form('business'))->assertForbidden();
        $this->assertSame(1, BookingClosure::count());

        foreach ([$owner, $manager] as $person) {
            $this->actingAs($person)->post($url, $form('business'))->assertRedirect();
            $this->actingAs($person)->post($url, $form((string) $theirs->id))->assertRedirect();
        }
        $this->assertSame(5, BookingClosure::count());
    }

    public function test_a_provider_sees_and_removes_only_their_own_closures(): void
    {
        [$team] = $this->business();
        $this->settings($team);
        $thandi = $this->member($team, 'member');
        $own = $this->provider($team, $thandi);
        $theirs = $this->provider($team, $this->member($team, 'member', 'Sipho Dube'), 'Sipho');
        $mine = BookingClosure::forceCreate(['team_id' => $team->id, 'booking_provider_id' => $own->id, 'kind' => 'block', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'reason' => 'My dentist']);
        $sipho = BookingClosure::forceCreate(['team_id' => $team->id, 'booking_provider_id' => $theirs->id, 'kind' => 'block', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'reason' => 'Sipho leave']);
        $wide = BookingClosure::forceCreate(['team_id' => $team->id, 'kind' => 'holiday', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'reason' => 'Business shutdown']);
        $this->actingAs($thandi);

        $this->get(route('business.booking.closures', $team))->assertOk()->assertSee('My dentist')->assertDontSee('Sipho leave')->assertDontSee('Business shutdown');
        $this->post(route('business.booking.closures.delete', [$team, $sipho]))->assertForbidden();
        $this->post(route('business.booking.closures.delete', [$team, $wide]))->assertForbidden();
        $this->post(route('business.booking.closures.delete', [$team, $mine]))->assertRedirect();

        $this->assertNull($mine->fresh());
        $this->assertNotNull($sipho->fresh());
        $this->assertNotNull($wide->fresh());
    }

    public function test_a_manager_removes_a_closure_and_it_is_recorded_without_the_note(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team);
        $closure = BookingClosure::forceCreate(['team_id' => $team->id, 'kind' => 'holiday', 'starts_at' => '2026-12-24 22:00:00', 'ends_at' => '2026-12-25 22:00:00', 'reason' => 'Private reason words']);
        $this->actingAs($owner)->post(route('business.booking.closures.delete', [$team, $closure]))->assertRedirect(route('business.booking.closures', $team));

        $this->assertNull($closure->fresh());
        $event = BookingEvent::where('type', 'closure_removed')->firstOrFail();
        $this->assertSame($closure->id, $event->details['closure_id']);
        $this->assertStringNotContainsString('Private', json_encode($event->details));
    }

    public function test_adding_a_closure_is_recorded_and_the_engine_sees_it(): void
    {
        [$team, $owner] = $this->business();
        $this->settings($team);
        $provider = $this->provider($team, $this->member($team, 'member'));
        $type = $this->type($team);
        $type->providers()->sync([$provider->id]);
        WorkingHour::forceCreate(['team_id' => $team->id, 'booking_provider_id' => null, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $engine = app(Availability::class);
        $now = CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC');
        $this->assertSame(Availability::AVAILABLE, $engine->dayStatus($type, '2026-11-30', null, $now));
        $this->actingAs($owner);

        $this->post(route('business.booking.closures.store', $team), ['kind' => 'block', 'scope' => (string) $provider->id, 'starts_on' => '2026-11-30', 'from' => '09:00', 'to' => '11:00', 'reason' => 'Dentist']);
        $this->assertSame(['09:00'], $engine->slots($type, '2026-11-30', null, $now)->map(fn ($s) => $s->start->format('H:i'))->all(), 'blocked 09:00 to 11:00 local leaves the 11:00 start');

        $this->post(route('business.booking.closures.store', $team), ['kind' => 'holiday', 'scope' => 'business', 'starts_on' => '2026-11-30']);
        $this->assertSame(Availability::UNAVAILABLE, $engine->dayStatus($type, '2026-11-30', null, $now));

        $events = BookingEvent::where('type', 'closure_added')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame($provider->id, $events[0]->details['provider_id']);
        $this->assertStringNotContainsString('Dentist', json_encode($events->toArray()));
    }
}
