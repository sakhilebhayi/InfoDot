<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Tests\Feature\Oidc\OidcTestCase;

/**
 * Signing out and resetting a forgotten password already ended a person's sessions on the Dot platforms. Deleting the
 * account and changing the password while signed in did not: the platforms kept their sessions and the provider
 * tokens stayed valid. Both now end them the same way.
 */
class SessionsEndElsewhereTest extends OidcTestCase
{
    private function backchannelCalls(): int
    {
        app(Kernel::class)->terminate(request(), response('')); // run anything queued for after the response

        return Http::recorded(fn ($request) => $request->url() === 'https://central.test/sso/backchannel-logout')->count();
    }

    public function test_deleting_the_account_ends_platform_sessions_and_revokes_provider_tokens(): void
    {
        Http::fake();

        $password = Str::password(24);
        $user = User::factory()->withPersonalTeam()->create(['password' => Hash::make($password)]);
        $this->completeCodeFlow($user);
        $tokenIds = Token::where('user_id', $user->id)->pluck('id');
        $this->assertNotEmpty($tokenIds);

        Livewire::actingAs($user)->test(DeleteUserForm::class)->set('password', $password)->call('deleteUser');

        $this->assertNull($user->fresh());
        $this->assertSame(1, $this->backchannelCalls());
        $this->assertSame(0, Token::whereIn('id', $tokenIds)->where('revoked', false)->count());
        $this->assertDatabaseMissing('sso_platform_sessions', ['user_id' => $user->id]);
    }

    public function test_changing_the_password_ends_platform_sessions_and_revokes_provider_tokens(): void
    {
        Http::fake();

        $password = Str::password(24);
        $newPassword = Str::password(24);
        $user = User::factory()->withPersonalTeam()->create(['password' => Hash::make($password)]);
        $this->completeCodeFlow($user);

        Livewire::actingAs($user)->test(UpdatePasswordForm::class)
            ->set('state', ['current_password' => $password, 'password' => $newPassword, 'password_confirmation' => $newPassword])
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertSame(1, $this->backchannelCalls());
        $this->assertSame(0, Token::where('user_id', $user->id)->where('revoked', false)->count());
        $this->assertDatabaseMissing('sso_platform_sessions', ['user_id' => $user->id]);
    }

    public function test_a_refused_password_change_ends_nothing(): void
    {
        Http::fake();

        $password = Str::password(24);
        $newPassword = Str::password(24);
        $user = User::factory()->withPersonalTeam()->create(['password' => Hash::make($password)]);
        $this->completeCodeFlow($user);

        Livewire::actingAs($user)->test(UpdatePasswordForm::class)
            ->set('state', ['current_password' => Str::password(24), 'password' => $newPassword, 'password_confirmation' => $newPassword])
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);

        $this->assertSame(0, $this->backchannelCalls());
        $this->assertSame(1, Token::where('user_id', $user->id)->where('revoked', false)->count());
    }
}
