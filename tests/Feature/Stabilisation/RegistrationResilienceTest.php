<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R-48 S-02. Production 2026-11-12: a sign-up answered 500 with "Duplicate entry ... users_email_unique". The
 * address had passed the unique rule, so another request for the same address was already in flight (a second
 * tap on Register). The account existed; the person saw a server error.
 */
class RegistrationResilienceTest extends TestCase
{
    use RefreshDatabase;

    /** Made per test; a literal here reads as a committed secret to scanners. */
    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->password = Str::random(20).'aA1!';
    }

    /** @return array<string, string> */
    private function form(string $email = 'new@example.com'): array
    {
        return ['name' => 'Pat Example', 'email' => $email, 'password' => $this->password, 'password_confirmation' => $this->password, 'terms' => 'on'];
    }

    /**
     * Stand in for the other request: its row lands after this request's validation and before its insert. The
     * first copy makes this request's insert collide (and is rolled back with it); the second is the row the
     * other request really committed.
     */
    private function anotherRequestWinsTheRace(string $email, string $password): void
    {
        $row = fn () => DB::table('users')->insert([
            'name' => 'Pat Example', 'email' => $email, 'password' => Hash::make($password),
            'uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $collided = false;
        $committed = false;

        User::creating(function () use (&$collided, $row) {
            if (! $collided) {
                $collided = true;
                $row();
            }
        });
        Event::listen(TransactionRolledBack::class, function () use (&$collided, &$committed, $row) {
            if ($collided && ! $committed) {
                $committed = true;
                $row();
            }
        });
    }

    public function test_a_second_tap_on_register_signs_the_person_in_instead_of_failing(): void
    {
        $this->anotherRequestWinsTheRace('new@example.com', $this->password);

        $response = $this->post('/register', $this->form());

        $response->assertRedirect();
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(1, User::where('email', 'new@example.com')->count());
        $this->assertAuthenticatedAs(User::where('email', 'new@example.com')->firstOrFail());
    }

    public function test_a_lost_race_against_someone_elses_password_is_an_ordinary_validation_error(): void
    {
        $this->anotherRequestWinsTheRace('new@example.com', Str::random(24));

        $response = $this->from('/register')->post('/register', $this->form());

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'new@example.com')->count());
    }

    public function test_an_account_that_already_existed_is_never_signed_in_by_the_sign_up_form(): void
    {
        $existing = User::factory()->create(['email' => 'old@example.com', 'password' => Hash::make($this->password), 'created_at' => now()->subDay()]);

        $response = $this->from('/register')->post('/register', $this->form('old@example.com'));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_sign_up_still_succeeds_when_the_verification_email_cannot_be_sent(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        Log::spy();

        $response = $this->post('/register', $this->form());

        $response->assertRedirect();
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertAuthenticated();
        $this->assertSame(1, User::where('email', 'new@example.com')->count());
        $response->assertSessionHas('verification_mail_failed', true);
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => $message === 'registration.verification_mail_failed')->once();
    }

    public function test_exactly_one_verification_email_is_sent_on_sign_up(): void
    {
        Notification::fake();

        $this->post('/register', $this->form())->assertRedirect();

        Notification::assertSentToTimes(User::where('email', 'new@example.com')->firstOrFail(), VerifyEmail::class, 1);
    }

    public function test_the_register_button_cannot_be_pressed_twice(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('x-on:submit="sending = true"', $html);
        $this->assertStringContainsString('x-bind:disabled="sending"', $html);
    }
}
