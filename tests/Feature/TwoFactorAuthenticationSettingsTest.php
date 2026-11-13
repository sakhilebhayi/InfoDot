<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Http\Livewire\TwoFactorAuthenticationForm;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Two-factor is switched on in two steps (R-49, finding L-10): "Enable" shows the QR code and asks for one code from the
 * authenticator; only a right code makes it active. The earlier tests enabled it in one call; they now go through the
 * confirmation, which is the behaviour being asked for.
 */
class TwoFactorAuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function form(User $user): Testable
    {
        $this->actingAs($user);
        $this->withSession(['auth.password_confirmed_at' => time()]);

        return Livewire::test(TwoFactorAuthenticationForm::class);
    }

    /** The code the person's authenticator would show now. */
    private function currentCode(User $user): string
    {
        return app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    }

    private function enabledAndConfirmed(User $user): Testable
    {
        $form = $this->form($user)->call('enableTwoFactorAuthentication');

        return $form->set('code', $this->currentCode($user))->call('confirmTwoFactorAuthentication')->assertHasNoErrors();
    }

    public function test_the_confirm_option_is_on(): void
    {
        $this->assertTrue(Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'));
    }

    public function test_enabling_shows_the_qr_code_and_asks_for_a_code(): void
    {
        $user = User::factory()->create();

        $form = $this->form($user)->call('enableTwoFactorAuthentication');

        $form->assertSet('showingQrCode', true)->assertSet('showingConfirmation', true)->assertSet('showingRecoveryCodes', false)
            ->assertSee('Finish turning on two factor authentication.')->assertSee('It is not on yet.')
            ->assertSeeHtml('<svg')->assertSee('Setup key')->assertSee(decrypt($user->fresh()->two_factor_secret))
            ->assertSeeHtml('id="two-factor-setup-code"')->assertSee('Code from your authenticator app')->assertSee('Confirm and turn on')
            ->assertDontSee('Store these recovery codes');

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at, 'not active until a code is entered');
    }

    public function test_a_wrong_code_does_not_enable_it(): void
    {
        $user = User::factory()->create();
        $form = $this->form($user)->call('enableTwoFactorAuthentication');
        $right = $this->currentCode($user);
        $wrong = str_pad((string) (((int) $right + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $form->set('code', $wrong)->call('confirmTwoFactorAuthentication')->assertHasErrors('code')
            ->assertSet('showingConfirmation', true)->assertDontSee('Store these recovery codes');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_a_right_code_enables_it_and_shows_the_recovery_codes_once_confirmed(): void
    {
        $user = User::factory()->create();

        $form = $this->enabledAndConfirmed($user);

        $form->assertSet('showingConfirmation', false)->assertSet('showingQrCode', false)->assertSet('showingRecoveryCodes', true)
            ->assertSee('You have enabled two factor authentication.')->assertSee('Store these recovery codes')->assertSee($user->fresh()->recoveryCodes()[0]);

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertCount(8, $user->fresh()->recoveryCodes());
    }

    public function test_an_unfinished_setup_is_cleared_when_the_page_is_opened_again(): void
    {
        $user = User::factory()->create();
        $this->form($user)->call('enableTwoFactorAuthentication');
        $this->assertNotNull($user->fresh()->two_factor_secret);

        $this->form($user->fresh())->assertSee('You have not enabled two factor authentication.');

        $this->assertNull($user->fresh()->two_factor_secret, 'a setup nobody finished leaves nothing behind');
    }

    public function test_cancelling_the_setup_turns_nothing_on(): void
    {
        $user = User::factory()->create();

        $this->form($user)->call('enableTwoFactorAuthentication')->assertSee('Cancel')->call('disableTwoFactorAuthentication')->assertSet('showingConfirmation', false);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $user = User::factory()->create();
        $form = $this->enabledAndConfirmed($user);
        $before = $user->fresh()->recoveryCodes();

        $form->call('regenerateRecoveryCodes');

        $this->assertCount(8, $user->fresh()->recoveryCodes());
        $this->assertCount(8, array_diff($before, $user->fresh()->recoveryCodes()));
    }

    public function test_two_factor_authentication_can_be_disabled(): void
    {
        $user = User::factory()->create();
        $form = $this->enabledAndConfirmed($user);

        $form->call('disableTwoFactorAuthentication');

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_a_person_who_already_had_two_factor_keeps_it_through_the_migration(): void
    {
        $migration = require database_path('migrations/2026_11_13_150000_add_two_factor_confirmed_at_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'two_factor_confirmed_at'));

        $with = User::factory()->create();
        $without = User::factory()->create();
        DB::table('users')->where('id', $with->id)->update(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['a-b']))]);

        $migration->up();
        $migration->up();

        $this->assertNotNull(DB::table('users')->where('id', $with->id)->value('two_factor_confirmed_at'));
        $this->assertNull(DB::table('users')->where('id', $without->id)->value('two_factor_confirmed_at'));

        // Opening account settings does not switch it off.
        $this->form(User::findOrFail($with->id))->assertSee('You have enabled two factor authentication.');
        $this->assertNotNull(DB::table('users')->where('id', $with->id)->value('two_factor_secret'));
    }
}
