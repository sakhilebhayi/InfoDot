<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sign-in was limited per email-and-address pair only, so one address could keep trying as long as
 * it changed the email each time. There are now two limits: the pair, and the address on its own.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_has_a_limit_for_the_pair_and_one_for_the_address(): void
    {
        $request = Request::create('/login', 'POST', ['email' => 'pat@example.com'], server: ['REMOTE_ADDR' => '203.0.113.9']);

        $limits = RateLimiter::limiter('login')($request);

        $this->assertIsArray($limits);
        $this->assertCount(2, $limits);
        $this->assertContainsOnlyInstancesOf(Limit::class, $limits);

        [$pair, $address] = $limits;
        $this->assertSame(5, $pair->maxAttempts);
        $this->assertStringContainsString('pat@example.com', (string) $pair->key);
        $this->assertSame(30, $address->maxAttempts);
        $this->assertStringNotContainsString('pat@example.com', (string) $address->key);
        $this->assertStringContainsString('203.0.113.9', (string) $address->key);
        $this->assertNotSame($pair->key, $address->key);
    }

    public function test_the_sixth_try_for_one_email_is_refused(): void
    {
        User::factory()->create(['email' => 'pat@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'pat@example.com', 'password' => Str::random(16)])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'pat@example.com', 'password' => Str::random(16)])->assertStatus(429);
    }

    public function test_one_address_cannot_keep_going_by_changing_the_email(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', ['email' => "person{$i}@example.com", 'password' => Str::random(16)])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'another@example.com', 'password' => Str::random(16)])->assertStatus(429);
        $this->assertGuest();
    }
}
