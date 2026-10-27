<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
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

    public function test_up_does_not_answer_ok_when_the_database_is_unreachable(): void
    {
        config([
            'app.debug' => false,
            'database.default' => 'unreachable',
            'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent-folder/health.sqlite', 'prefix' => ''],
        ]);
        DB::purge('unreachable');

        $response = $this->get('/up');

        $response->assertStatus(500);
        $this->assertStringNotContainsString('nonexistent-folder', $response->getContent());
    }
}
