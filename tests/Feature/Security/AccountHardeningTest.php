<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

/**
 * R-72: passwords of at least 12 characters that are not known from a breach (S-03), a "forgot password" answer that never says whether
 * an account exists (S-04), and limits on guessing the current password when confirming or changing it (S-05).
 */
class AccountHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $password): TestResponse
    {
        return $this->post('/register', [
            'name' => 'Lindiwe Dube', 'email' => 'lindiwe@example.com',
            'password' => $password, 'password_confirmation' => $password,
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);
    }

    public function test_a_new_password_needs_twelve_characters(): void
    {
        $this->register('short-pass1')->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_a_password_known_from_a_breach_is_refused(): void
    {
        $this->register('password1234')->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_a_long_unbreached_password_is_accepted(): void
    {
        $this->register('green-kettle-river-9')->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_forgot_password_answers_the_same_for_a_known_and_an_unknown_address(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect('/forgot-password')->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'If an InfoDot account uses that address, we have emailed it a link to choose a new password. The link works for an hour.');
        }

        // Asking again at once (the broker's own throttle) still gives nothing away.
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.com'])->assertSessionHasNoErrors();

        $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->assertJsonPath('message', trans('passwords.sent'));
    }

    public function test_confirming_the_password_stops_after_five_wrong_tries(): void
    {
        $user = User::factory()->create(['password' => Hash::make('green-kettle-river-9')]);
        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/user/confirm-password', ['password' => 'wrong-guess-'.$i])->assertSessionHasErrors('password');
        }

        // Even the right password is refused until the wait is over.
        $this->post('/user/confirm-password', ['password' => 'green-kettle-river-9'])
            ->assertSessionHasErrors(['password' => 'Too many wrong passwords. Wait 10 minutes and try again.']);
    }

    public function test_changing_the_password_stops_after_five_wrong_current_passwords(): void
    {
        $user = User::factory()->create(['password' => Hash::make('green-kettle-river-9')]);
        $updater = app(UpdatesUserPasswords::class);
        $attempt = fn (string $current) => rescue(fn () => $updater->update($user, ['current_password' => $current, 'password' => 'blue-teapot-harbour-4', 'password_confirmation' => 'blue-teapot-harbour-4']), fn ($e) => $e, false);

        for ($i = 0; $i < 5; $i++) {
            $this->assertArrayHasKey('current_password', $attempt('wrong-'.$i)->errors());
        }

        $blocked = $attempt('green-kettle-river-9');
        $this->assertStringContainsString('Too many wrong passwords', $blocked->errors()['current_password'][0]);
        $this->assertTrue(Hash::check('green-kettle-river-9', $user->fresh()->password), 'the password did not change');
    }

    public function test_changing_the_email_address_needs_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $updater = app(UpdatesUserProfileInformation::class);
        $try = fn (array $input) => rescue(fn () => $updater->update($user, ['name' => $user->name] + $input), fn ($e) => $e, false);

        $this->assertArrayHasKey('current_password', $try(['email' => 'thief@example.com'])->errors());
        $this->assertArrayHasKey('current_password', $try(['email' => 'thief@example.com', 'current_password' => 'not-it'])->errors());
        $this->assertSame('old@example.com', $user->fresh()->email);

        // A name change alone needs no password.
        $this->assertNull($try(['email' => 'old@example.com', 'name' => 'New Name']));

        $this->assertNull($try(['email' => 'new@example.com', 'current_password' => 'password']));
        $this->assertSame('new@example.com', $user->fresh()->email);
    }
}
