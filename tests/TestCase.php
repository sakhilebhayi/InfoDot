<?php

namespace Tests;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensureOauthKeys();

        // R-72 (S-03): tests never ask the real Pwned Passwords service. These few stand in for passwords found in a breach.
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data)
            {
                return ! in_array(strtolower((string) ($data['value'] ?? '')), ['password1234', 'qwertyuiop12', 'iloveyou1234'], true);
            }
        });
    }

    /**
     * R-20: the OIDC provider signs tokens with the Passport keypair. Generated once per checkout
     * into storage/ (gitignored), exactly as the deploy does on the host.
     */
    private function ensureOauthKeys(): void
    {
        if (! is_file(Passport::keyPath('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }
    }
}
