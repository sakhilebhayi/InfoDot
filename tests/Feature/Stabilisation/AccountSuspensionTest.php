<?php

namespace Tests\Feature\Stabilisation;

use App\Models\AccountSuspensionEvent;
use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use App\Support\EcosystemHandoff;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * InfoDot signs people in to every Dot platform, so an account an administrator has suspended must stop working at
 * every door at once: signing in, a session that is already open, opening a platform, a platform redeeming a launch
 * code, and API tokens. Each door has its own test here; the OpenID Connect doors are in AccountSuspensionOidcTest.
 */
class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'This account is suspended. Contact InfoDot support.';

    private string $password;

    private string $serviceSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = Str::password(24);
        $this->serviceSecret = Str::random(40);

        config([
            'ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'active' => true],
            'ecosystem.service_secrets.files' => $this->serviceSecret,
        ]);
    }

    private function person(bool $suspended = false): User
    {
        $user = User::factory()->withPersonalTeam()->create(['password' => Hash::make($this->password)]);

        if ($suspended) {
            $user->forceFill(['suspended_at' => now(), 'suspended_reason' => 'test'])->save();
        }

        return $user;
    }

    public function test_suspending_marks_the_account_and_ends_everything_it_holds(): void
    {
        $user = $this->person();
        $other = $this->person();
        $actor = $this->person();

        $user->createToken('integration');
        $other->createToken('integration');

        foreach ([$user, $other] as $owner) {
            DB::table('sessions')->insert([
                'id' => Str::random(40), 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $rememberToken = $user->remember_token;

        $summary = app(AccountSuspension::class)->suspend($user, 'Reported for abuse', $actor);

        $user->refresh();
        $this->assertTrue($user->isSuspended());
        $this->assertNotNull($user->suspended_at);
        $this->assertSame('Reported for abuse', $user->suspended_reason);
        $this->assertNotSame($rememberToken, $user->remember_token, 'a remembered browser can no longer sign itself back in');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $summary['sessions']);
        $this->assertSame(1, $summary['api_tokens']);

        // Nobody else is touched.
        $this->assertFalse($other->fresh()->isSuspended());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertSame(1, $other->tokens()->count());

        $this->assertDatabaseHas('account_suspension_events', [
            'user_id' => $user->id, 'action' => 'suspended', 'reason' => 'Reported for abuse', 'actor_id' => $actor->id,
        ]);
    }

    public function test_restoring_clears_the_mark_and_is_recorded(): void
    {
        $user = $this->person();
        $service = app(AccountSuspension::class);

        $service->suspend($user, 'Mistaken identity', null);
        $service->restore($user->fresh(), 'Cleared', null);

        $user->refresh();
        $this->assertFalse($user->isSuspended());
        $this->assertNull($user->suspended_at);
        $this->assertNull($user->suspended_reason);

        $this->assertSame(
            ['suspended', 'restored'],
            AccountSuspensionEvent::where('user_id', $user->id)->orderBy('id')->pluck('action')->all()
        );

        $this->post('/login', ['email' => $user->email, 'password' => $this->password]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspending_works_where_sessions_are_not_kept_in_the_database(): void
    {
        Schema::drop('sessions');

        $user = $this->person();
        $summary = app(AccountSuspension::class)->suspend($user, null, null);

        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertSame(0, $summary['sessions']);
    }

    public function test_the_audit_trail_cannot_be_rewritten(): void
    {
        $user = $this->person();
        app(AccountSuspension::class)->suspend($user, 'First reason', null);

        $event = AccountSuspensionEvent::where('user_id', $user->id)->firstOrFail();

        $this->expectException(\LogicException::class);
        $event->update(['reason' => 'Another reason']);
    }

    public function test_a_suspended_person_cannot_sign_in_with_the_right_password(): void
    {
        $user = $this->person(suspended: true);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => $this->password])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertGuest();
    }

    public function test_a_wrong_password_does_not_reveal_that_the_account_is_suspended(): void
    {
        $user = $this->person(suspended: true);

        $response = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => Str::password(24)]);

        $response->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertStringNotContainsStringIgnoringCase('suspend', implode(' ', session('errors')->all()));
        $this->assertGuest();
    }

    public function test_everyone_else_signs_in_as_before(): void
    {
        $user = $this->person();

        $this->post('/login', ['email' => $user->email, 'password' => $this->password])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => Str::password(24)])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_an_open_session_ends_on_its_next_request_once_the_account_is_suspended(): void
    {
        $user = $this->person();

        $this->actingAs($user)->get('/user/profile/edit')->assertOk();

        $user->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($user->fresh())->get('/user/profile/edit')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest('web');
    }

    public function test_an_open_session_of_a_suspended_person_gets_a_plain_refusal_for_json(): void
    {
        $this->actingAs($this->person(suspended: true))->getJson('/user/profile/edit')->assertUnauthorized();
        $this->assertGuest('web');
    }

    public function test_the_session_check_leaves_guests_and_other_people_alone_and_asks_the_database_nothing(): void
    {
        $this->get('/login')->assertOk();

        $user = $this->person();
        $this->actingAs($user)->get('/user/profile/edit')->assertOk();
        $this->assertAuthenticatedAs($user);

        // The check reads the person the session already loaded: it never queries for the suspension itself.
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)->get('/user/profile/edit')->assertOk();

        $this->assertSame([], array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'suspen'))));
    }

    public function test_a_suspended_person_cannot_open_a_platform(): void
    {
        $user = $this->person(suspended: true);

        $response = $this->actingAs($user)->get('/go/files');

        $this->assertStringNotContainsString('name="token"', (string) $response->getContent());
        $this->assertNull(EcosystemHandoff::launchForm($user, 'files'));
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count(), 'no launch code is ever made');
    }

    public function test_a_launch_code_of_a_suspended_person_is_refused_like_any_other_and_is_spent(): void
    {
        $user = $this->person();
        $code = $user->createToken(EcosystemHandoff::tokenName('files'), ['ecosystem:read'], now()->addMinute())->plainTextToken;
        $user->forceFill(['suspended_at' => now()])->save();

        $this->withHeader('Authorization', 'Bearer '.$this->serviceSecret)
            ->postJson('/api/ecosystem/redeem', ['token' => $code, 'platform' => 'files'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'invalid_request']);

        $this->assertNull(PersonalAccessToken::findToken($code));
    }

    public function test_a_handoff_into_infodot_does_not_sign_a_suspended_person_in(): void
    {
        $user = $this->person();
        $code = $user->createToken(EcosystemHandoff::tokenName(null), ['ecosystem:read'], now()->addMinute())->plainTextToken;
        $user->forceFill(['suspended_at' => now()])->save();

        $this->withHeader('Origin', 'http://localhost')
            ->post('/auth/ecosystem', ['token' => $code])
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertNull(PersonalAccessToken::findToken($code));
    }

    public function test_an_api_token_of_a_suspended_person_stops_working(): void
    {
        $user = $this->person();
        $token = $user->createToken('integration', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')->assertOk();

        $user->forceFill(['suspended_at' => now()])->save();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_resetting_the_password_of_a_suspended_account_does_not_sign_anyone_in(): void
    {
        Notification::fake();

        $user = $this->person(suspended: true);
        $newPassword = Str::password(24);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $newPassword) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

            return true;
        });

        $this->assertGuest();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => $newPassword])
            ->assertSessionHasErrors(['email' => self::MESSAGE]);
        $this->assertGuest();
        $this->assertTrue($user->fresh()->isSuspended(), 'a password reset never lifts a suspension');
    }

    public function test_the_suspend_command_suspends_by_email_and_says_what_it_did(): void
    {
        $user = $this->person();
        $user->createToken('integration');

        $this->artisan('account:suspend', ['email' => $user->email, '--reason' => 'Spam'])
            ->expectsOutputToContain('suspended')
            ->expectsOutputToContain('API tokens deleted: 1')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertDatabaseHas('account_suspension_events', ['user_id' => $user->id, 'action' => 'suspended', 'reason' => 'Spam', 'actor_id' => null]);
    }

    public function test_the_suspend_command_refuses_an_email_it_cannot_find(): void
    {
        $this->artisan('account:suspend', ['email' => 'nobody@example.test'])->assertFailed();

        $this->assertDatabaseCount('account_suspension_events', 0);
    }

    public function test_the_restore_command_restores_by_email(): void
    {
        $user = $this->person(suspended: true);

        $this->artisan('account:restore', ['email' => $user->email])->assertSuccessful();

        $this->assertFalse($user->fresh()->isSuspended());
        $this->assertDatabaseHas('account_suspension_events', ['user_id' => $user->id, 'action' => 'restored']);

        $this->artisan('account:restore', ['email' => 'nobody@example.test'])->assertFailed();
    }
}
