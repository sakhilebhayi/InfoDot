<?php

namespace Tests;

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
