<?php

namespace Tests\Feature\Licensing;

use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\LicencePricing;
use App\Services\Licensing\LicenceRefused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-59 Phase 1E: the owner's price, R98 per user per month, flat, no VAT (the business is not VAT registered). Nothing here charges anything. */
class SeatPriceR98Test extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        $user = User::factory()->create();

        return $user->ownedTeams()->create(['name' => 'Acme', 'personal_team' => false]);
    }

    public function test_the_shipped_default_has_no_price_and_nothing_can_be_bought(): void
    {
        $this->assertNull(config('licensing.price.amount_minor_units'));
        $this->assertFalse(config('licensing.finance_connected'));
        $this->expectException(LicenceRefused::class);
        app(LicencePricing::class)->quote($this->team(), 5, 'month');
    }

    public function test_default_bands_are_flat(): void
    {
        $this->assertSame([['up_to' => null, 'percent' => 100]], config('licensing.price.bands'));
    }

    public function test_five_paid_seats_cost_r490_a_month_and_no_vat_is_added(): void
    {
        config(['licensing.price.amount_minor_units' => 9800, 'licensing.price.currency' => 'ZAR']);

        $quote = app(LicencePricing::class)->quote($this->team(), 5, 'month');

        $this->assertSame(9800, $quote['unit_price_minor']);
        $this->assertSame(49000, $quote['total_minor']);
        $this->assertArrayNotHasKey('vat_minor', $quote);
        $this->assertNull(config('licensing.vat'));
    }

    public function test_price_does_not_fall_with_size(): void
    {
        config(['licensing.price.amount_minor_units' => 9800, 'licensing.price.currency' => 'ZAR']);
        $pricing = app(LicencePricing::class);

        $this->assertSame(9800 * 40, $pricing->quote($this->team(), 40, 'month')['total_minor']);
    }

    public function test_billing_country_and_vat_number_are_saved_and_kept_private(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = $owner->ownedTeams()->create(['name' => 'Acme', 'personal_team' => false]);
        $team->profile()->create(['visibility' => 'public', 'description' => 'We trade.']);

        $this->actingAs($owner)->post(route('business.update', $team), ['billing_country' => 'South Africa', 'vat_number' => '4123456789'])->assertSessionHasNoErrors();

        $profile = $team->profile()->first();
        $this->assertSame('South Africa', $profile->billing_country);
        $this->assertSame('4123456789', $profile->vat_number);
        $this->get(route('business.show', $team))->assertDontSee('4123456789');
    }
}
