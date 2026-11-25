<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared set-up for the R-63 phase 4 dashboard tests. "Now" is Friday 20 November 2026, 10:00 Johannesburg (08:00Z). Mondays and Tuesdays are open 09:00 to 17:00
 * local (07:00Z to 15:00Z); Johannesburg is two hours ahead of UTC all year.
 */
abstract class AppointmentsTestCase extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    protected Team $team;

    protected User $owner;

    protected AppointmentType $type;

    protected User $thandi;

    protected User $sipho;

    protected BookingProvider $a;

    protected BookingProvider $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        [$this->team, $this->owner] = $this->business('available');
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'approval', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 60, 'horizon_days' => 365, 'cancel_cutoff_hours' => 24]);
        foreach ([1, 2] as $weekday) {
            WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        }
        $this->type = $this->type($this->team);
        $this->thandi = $this->member($this->team, 'member', 'Thandi Nkosi');
        $this->sipho = $this->member($this->team, 'member', 'Sipho Dube');
        $this->a = $this->provider($this->team, $this->thandi, 'Thandi');
        $this->b = $this->provider($this->team, $this->sipho, 'Sipho');
        $this->type->providers()->attach([$this->a->id, $this->b->id]);
    }

    protected function mk(string $startUtc, string $status = Booking::PENDING, ?BookingProvider $provider = null, array $over = []): Booking
    {
        $start = CarbonImmutable::parse($startUtc, 'UTC');

        return Booking::create($over + [
            'reference' => 'DF-B-'.Str::upper(Str::random(10)), 'team_id' => $this->team->id, 'appointment_type_id' => $this->type->id, 'booking_provider_id' => $provider?->id,
            'customer_name' => 'Zodwa Secret', 'customer_email' => 'zodwa@example.test', 'customer_phone' => '082 000 0000', 'customer_company' => 'Secret Traders', 'reason' => 'A private matter',
            'starts_at' => $start, 'ends_at' => $start->addHour(), 'blocks_from' => $start, 'blocks_until' => $start->addHour(), 'status' => $status,
        ]);
    }

    protected function url(string $path = ''): string
    {
        return '/business/'.$this->team->uuid.'/appointments'.$path;
    }

    protected function stranger(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }
}
