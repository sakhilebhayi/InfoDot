<?php

namespace Tests\Feature\Booking;

use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\PlatformFeature;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Services\Booking\BookingFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-63 phase 1: a business's booking settings, who may change them, the feature switch that opens them, and the trail of changes. */
class BookingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function feature(string $state): void
    {
        PlatformFeature::where('platform_key', 'infodot')->where('key', 'bookings')->update(['state' => $state]);
    }

    /** @return array{0: Team, 1: User} a business and its owner, the feature open */
    private function business(string $state = 'available'): array
    {
        $this->feature($state);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Dlamini Freight']);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return [$team, $owner];
    }

    private function member(Team $team, string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team->users()->attach($user, ['role' => $role]);
        $team->unsetRelation('users');

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + [
            'enabled' => '1', 'calendar_published' => '1', 'approval_mode' => 'auto', 'timezone' => 'Africa/Johannesburg',
            'min_notice_hours' => 4, 'horizon_days' => 30, 'cancel_cutoff_hours' => 12, 'instructions' => 'Bring your ID.', 'terms' => 'Late arrivals may lose the slot.',
        ];
    }

    public function test_the_feature_ships_in_development_and_is_not_open_until_an_administrator_opens_it(): void
    {
        $row = PlatformFeature::where('platform_key', 'infodot')->where('key', 'bookings')->firstOrFail();
        $this->assertSame(['development', 'free'], [$row->state, $row->kind]);
        $this->assertFalse(app(BookingFeature::class)->available());

        [$team, $owner] = $this->business('development');
        $this->actingAs($owner)->get(route('business.booking.settings', $team))->assertNotFound();
        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form())->assertNotFound();
        $this->assertSame(0, BookingSetting::count());
    }

    public function test_a_missing_feature_row_is_not_treated_as_open(): void
    {
        PlatformFeature::where('platform_key', 'infodot')->where('key', 'bookings')->delete();

        $this->assertFalse(app(BookingFeature::class)->available());
        $this->assertNotNull(app(BookingFeature::class)->message());
    }

    public function test_each_feature_state_other_than_available_closes_the_screen(): void
    {
        foreach (['disabled', 'development', 'retired'] as $state) {
            [$team, $owner] = $this->business($state);
            $this->actingAs($owner)->get(route('business.booking.settings', $team))->assertNotFound();
        }
    }

    public function test_the_owner_sees_the_defaults_and_nothing_is_saved_by_looking(): void
    {
        [$team, $owner] = $this->business();

        $this->actingAs($owner)->get(route('business.booking.settings', $team))->assertOk()
            ->assertSee('Appointment booking')->assertSee('Africa/Johannesburg')->assertSee('We confirm each one');

        $this->assertSame(0, BookingSetting::count());
        $this->assertFalse(BookingSetting::forTeam($team)->isOpen());
    }

    public function test_owner_administrator_and_manager_may_change_the_settings_a_member_may_not(): void
    {
        [$team, $owner] = $this->business();

        foreach ([$owner, $this->member($team, 'admin'), $this->member($team, 'manager')] as $person) {
            $this->actingAs($person)->get(route('business.booking.settings', $team))->assertOk();
            $this->actingAs($person)->post(route('business.booking.settings.update', $team), $this->form())->assertRedirect(route('business.booking.settings', $team));
        }

        $plain = $this->member($team, 'member');
        $this->actingAs($plain)->get(route('business.booking.settings', $team))->assertForbidden();
        $this->actingAs($plain)->post(route('business.booking.settings.update', $team), $this->form(['horizon_days' => 5]))->assertForbidden();
        $this->assertSame(30, BookingSetting::sole()->horizon_days);
    }

    public function test_a_stranger_a_guest_and_another_businesses_manager_are_refused(): void
    {
        [$team] = $this->business();
        [, $otherOwner] = $this->business();

        $this->get(route('business.booking.settings', $team))->assertRedirect();
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get(route('business.booking.settings', $team))->assertForbidden();
        $this->actingAs($otherOwner)->get(route('business.booking.settings', $team))->assertForbidden();
        $this->actingAs($otherOwner)->post(route('business.booking.settings.update', $team), $this->form())->assertForbidden();
        $this->assertSame(0, BookingSetting::count());
    }

    public function test_a_personal_workspace_takes_no_bookings(): void
    {
        $this->feature('available');
        $person = User::factory()->create(['email_verified_at' => now()]);
        $personal = Team::factory()->create(['user_id' => $person->id, 'personal_team' => true]);

        $this->actingAs($person)->get(route('business.booking.settings', $personal))->assertForbidden();
    }

    public function test_saving_stores_the_settings_in_minutes_and_days_and_rereads_them(): void
    {
        [$team, $owner] = $this->business();

        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form(['timezone' => 'Europe/London', 'min_notice_hours' => 6]))->assertSessionHasNoErrors();

        $saved = BookingSetting::sole();
        $this->assertSame([true, true, 'auto', 'Europe/London', 360, 30, 12], [$saved->enabled, $saved->calendar_published, $saved->approval_mode, $saved->timezone, $saved->min_notice_minutes, $saved->horizon_days, $saved->cancel_cutoff_hours]);
        $this->assertSame('Europe/London', $saved->zone()->getName());
        $this->assertTrue($saved->isOpen());

        $this->actingAs($owner)->get(route('business.booking.settings', $team))->assertSee('value="6"', false)->assertSee('Bring your ID.');
    }

    public function test_an_unticked_box_means_off_and_a_second_save_updates_the_same_row(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form());

        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form(['enabled' => '0', 'calendar_published' => '0']));

        $this->assertSame(1, BookingSetting::count());
        $this->assertFalse(BookingSetting::sole()->enabled);
        $this->assertFalse(BookingSetting::sole()->calendar_published);
    }

    public function test_bad_values_are_refused_and_nothing_is_saved(): void
    {
        [$team, $owner] = $this->business();

        foreach ([['timezone' => 'Mars/Olympus'], ['approval_mode' => 'maybe'], ['min_notice_hours' => -1], ['min_notice_hours' => 337], ['horizon_days' => 0], ['horizon_days' => 366], ['cancel_cutoff_hours' => 500], ['instructions' => str_repeat('a', 1501)], ['terms' => str_repeat('a', 4001)], ['horizon_days' => 'soon']] as $bad) {
            $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form($bad))->assertSessionHasErrors(array_key_first($bad) === 'min_notice_hours' ? 'min_notice_hours' : array_key_first($bad));
        }
        $this->assertSame(0, BookingSetting::count());
    }

    public function test_every_change_is_recorded_without_the_free_text_and_an_unchanged_save_records_nothing(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form());

        $events = BookingEvent::where('team_id', $team->id)->orderBy('id')->get();
        $this->assertSame(['settings_changed', 'booking_enabled'], $events->pluck('type')->all());
        $this->assertSame($owner->id, $events[0]->actor_id);
        $details = $events[0]->details;
        $this->assertSame(['from' => 'approval', 'to' => 'auto'], $details['approval_mode']);
        $this->assertSame(['from' => 120, 'to' => 240], $details['min_notice_minutes']);
        $this->assertSame('changed', $details['instructions']);
        $this->assertStringNotContainsString('Bring your ID', json_encode($events->toArray()));

        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form());
        $this->assertSame(2, BookingEvent::count(), 'saving the same values writes nothing');

        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form(['enabled' => '0']));
        $this->assertSame('booking_disabled', BookingEvent::orderByDesc('id')->first()->type);
    }

    public function test_the_bookings_button_shows_on_the_page_only_to_managers_and_only_while_the_feature_is_open(): void
    {
        [$team, $owner] = $this->business();
        $team->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'We move freight.'])->save();
        $plain = $this->member($team, 'member');

        $this->actingAs($owner)->get(route('business.show', $team))->assertOk()->assertSee(route('business.booking.settings', $team), false);
        $this->actingAs($plain)->get(route('business.show', $team))->assertOk()->assertDontSee(route('business.booking.settings', $team), false);
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get(route('business.show', $team))->assertOk()->assertDontSee(route('business.booking.settings', $team), false);
        $this->actingAs($owner)->get(route('business.show', [$team, 'as' => 'visitor']))->assertDontSee(route('business.booking.settings', $team), false);

        $this->feature('development');
        $this->actingAs($owner)->get(route('business.show', $team))->assertOk()->assertDontSee(route('business.booking.settings', $team), false);
    }

    public function test_deleting_a_business_removes_its_settings_and_trail(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner)->post(route('business.booking.settings.update', $team), $this->form());

        $team->delete();

        $this->assertSame([0, 0], [BookingSetting::count(), BookingEvent::count()]);
    }

    public function test_your_own_business_page_offers_to_manage_bookings_and_never_to_book_yourself(): void
    {
        [$team, $owner] = $this->business();

        $html = $this->actingAs($owner)->get(route('business.show', $team))->assertOk()->getContent();

        $this->assertStringContainsString('Manage bookings', $html);
        $this->assertStringContainsString(route('business.booking.settings', $team), $html);
        $this->assertStringNotContainsString('Book an appointment', $html);
    }
}
