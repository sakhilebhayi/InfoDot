<?php

namespace Tests\Feature\Stabilisation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * /ready asked whether storage/framework looked writable. A full disk or an exhausted quota still looks writable:
 * only writing shows it. The probe writes a few bytes under storage/framework and removes them.
 */
class ReadinessStorageTest extends TestCase
{
    public function test_a_disk_that_accepts_no_write_answers_503_and_says_nothing_else(): void
    {
        File::partialMock()->shouldReceive('put')->withArgs(fn ($path) => str_starts_with($path, storage_path('framework').'/ready-'))->once()->andReturn(false);
        Log::spy();

        $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'degraded']);

        Log::shouldHaveReceived('error')->withArgs(fn ($m, $ctx = []) => $m === 'readiness check failed' && ($ctx['check'] ?? null) === 'storage')->once();
    }

    public function test_a_write_that_throws_answers_503_without_the_path(): void
    {
        File::partialMock()->shouldReceive('put')->andThrow(new \ErrorException('file_put_contents(/home/acct/storage/framework/ready-x): Failed to open stream: Disk quota exceeded'));

        $r = $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'degraded']);

        $this->assertStringNotContainsString('/home/acct', (string) $r->getContent());
    }

    public function test_the_probe_file_is_written_and_removed(): void
    {
        $before = glob(storage_path('framework').'/ready-*') ?: [];
        $written = null;
        File::partialMock()->shouldReceive('put')->once()->passthru()->withArgs(function ($path) use (&$written) {
            $written = $path;

            return true;
        });

        $this->get('/ready')->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertNotNull($written);
        $this->assertStringStartsWith(storage_path('framework').'/ready-', $written);
        $this->assertSame($before, glob(storage_path('framework').'/ready-*') ?: []);
    }
}
