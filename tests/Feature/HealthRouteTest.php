<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /up is what the deploy's smoke check and monitoring request. It must answer
 * 200 to a GET, and it must be GET-only so that a POST (the deploy's debug-page
 * probe) is a small 405 that never reaches a controller.
 */
class HealthRouteTest extends TestCase
{
    public function test_up_answers_ok(): void
    {
        $this->get('/up')->assertOk()->assertSee('OK');
    }

    public function test_up_is_get_only(): void
    {
        $this->post('/up')->assertStatus(405);
    }
}
