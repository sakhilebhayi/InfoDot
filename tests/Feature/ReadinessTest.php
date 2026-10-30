<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** /ready asks the database, cache and storage, and says only ok or degraded. */
class ReadinessTest extends TestCase
{
    public function test_it_says_ok_when_the_dependencies_answer(): void
    {
        $this->get('/ready')->assertOk()->assertExactJson(['status' => 'ok'])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_failing_dependency_gives_503_and_leaks_nothing(): void
    {
        DB::shouldReceive('select')->andThrow(new \RuntimeException('SQLSTATE[HY000] secret-host-name:3306 refused'));

        $r = $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'degraded']);

        $this->assertStringNotContainsString('secret-host-name', $r->getContent());
    }

    public function test_a_broken_cache_is_reported_as_degraded(): void
    {
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('redis down'));

        $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'degraded']);
    }
}
