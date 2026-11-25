<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\PlatformFeature;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Shared set-up for the R-63 booking setup tests: a business with its feature open, members in each role, providers and bookings. */
trait BuildsBookingBusinesses
{
    private function feature(string $state): void
    {
        PlatformFeature::where('platform_key', 'infodot')->where('key', 'bookings')->update(['state' => $state]);
    }

    /** @return array{0: Team, 1: User} a business and its owner, the feature in the given state */
    private function business(string $state = 'available', string $name = 'Dlamini Freight'): array
    {
        $this->feature($state);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => $name]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return [$team, $owner];
    }

    private function member(Team $team, string $role, string $name = 'Thandi Nkosi'): User
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => $name]);
        $team->users()->attach($user, ['role' => $role]);
        $team->unsetRelation('users');

        return $user->fresh();
    }

    private function provider(Team $team, User $user, string $label = 'Thandi', bool $active = true): BookingProvider
    {
        return $team->bookingProviders()->create(['user_id' => $user->id, 'public_name' => $label, 'active' => $active]);
    }

    private function type(Team $team, array $over = []): AppointmentType
    {
        return $team->appointmentTypes()->create($over + ['name' => 'Consultation', 'duration_minutes' => 60, 'slot_interval_minutes' => 60]);
    }

    private function settings(Team $team, string $zone = 'Africa/Johannesburg'): BookingSetting
    {
        return BookingSetting::forceCreate(['team_id' => $team->id, 'enabled' => true, 'timezone' => $zone, 'min_notice_minutes' => 0, 'horizon_days' => 365]);
    }

    private function book(AppointmentType $type, ?BookingProvider $provider = null): Booking
    {
        $start = CarbonImmutable::parse('2026-12-01 08:00:00', 'UTC');

        return Booking::create([
            'team_id' => $type->team_id, 'appointment_type_id' => $type->id, 'booking_provider_id' => $provider?->id, 'customer_name' => 'A Customer', 'customer_email' => 'c@example.test',
            'starts_at' => $start, 'ends_at' => $start->addHour(), 'blocks_from' => $start, 'blocks_until' => $start->addHour(), 'status' => Booking::CONFIRMED,
        ]);
    }

    /** @return array<string, mixed> */
    private function typeForm(array $over = []): array
    {
        return $over + [
            'name' => 'Initial consultation', 'description' => 'A first talk.', 'duration_minutes' => 45, 'slot_interval_minutes' => 15, 'buffer_before_minutes' => 5, 'buffer_after_minutes' => 10,
            'fee' => '250.50', 'currency' => 'ZAR', 'meeting_format' => 'online', 'location_note' => 'A link arrives by email.', 'preparation' => 'Bring your last statement.', 'approval' => 'follow', 'sort' => 2, 'active' => '1',
        ];
    }

    /** @return array<string, mixed> a week form: Monday and Tuesday 09:00 to 17:00 */
    private function week(array $over = []): array
    {
        return ['hours' => $over + [1 => [['from' => '09:00', 'to' => '17:00']], 2 => [['from' => '09:00', 'to' => '17:00']]]];
    }
}
