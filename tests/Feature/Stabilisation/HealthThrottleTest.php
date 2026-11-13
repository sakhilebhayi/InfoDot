<?php

namespace Tests\Feature\Stabilisation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * /up runs a database query for anyone who asks. Like /ready it carries a per-address limit, so a
 * flood of probes is answered with a small 429 instead of one query each.
 */
class HealthThrottleTest extends TestCase
{
    public function test_up_still_answers_ok(): void
    {
        $this->get('/up')->assertOk()->assertSee('OK');
    }

    public function test_up_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('up');

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1,up', $route->gatherMiddleware());
    }

    public function test_requests_over_the_limit_are_refused_without_a_server_error(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get('/up')->assertOk();
        }

        $this->get('/up')->assertStatus(429);
    }
}
