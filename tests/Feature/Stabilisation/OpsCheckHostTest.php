<?php

namespace Tests\Feature\Stabilisation;

use App\Services\Ops\ErrorLog;
use App\Services\Ops\OpsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ops:check counted database conditions only. A folder the web user cannot write to, a full disk and a log filling
 * with errors are the host conditions behind the upload and sign-up failures of 2026-11-12; each is now a line.
 */
class OpsCheckHostTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = storage_path();
        $this->storage = storage_path('framework/testing/ops-check-'.Str::random(10));
        File::ensureDirectoryExists($this->storage.'/app/public/profile-photos');
        File::ensureDirectoryExists($this->storage.'/framework');
        File::ensureDirectoryExists($this->storage.'/logs');
        // The signing keys are looked for under storage/ too; without them a different check would fail.
        foreach (['oauth-private.key', 'oauth-public.key'] as $key) {
            File::copy($this->original.'/'.$key, $this->storage.'/'.$key);
        }
        $this->app->useStoragePath($this->storage);
        config(['logging.channels.daily.path' => $this->storage.'/logs/laravel.log']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach (['/app/public/profile-photos', '/app/public', '/framework'] as $folder) {
            if (is_dir($this->storage.$folder)) {
                chmod($this->storage.$folder, 0755);
            }
        }
        File::deleteDirectory($this->storage);
        $this->app->useStoragePath($this->original);
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Root can write to a read-only folder, so the permission cases prove nothing there. */
    private function skipWhenRoot(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('Folder permissions do not bind root.');
        }
    }

    /** @return array<string, int> */
    private function found(): array
    {
        return app(OpsCheck::class)->run();
    }

    public function test_writable_folders_are_ok(): void
    {
        $found = $this->found();

        foreach (['storage_app_not_writable', 'profile_photos_not_writable', 'storage_framework_not_writable', 'bootstrap_cache_not_writable'] as $kind) {
            $this->assertSame(0, $found[$kind], $kind);
        }
    }

    public function test_a_folder_that_cannot_be_written_is_a_problem(): void
    {
        $this->skipWhenRoot();
        File::chmod($this->storage.'/app/public/profile-photos', 0555);
        File::chmod($this->storage.'/framework', 0555);

        $found = $this->found();

        $this->assertSame(1, $found['profile_photos_not_writable']);
        $this->assertSame(1, $found['storage_framework_not_writable']);
        $this->assertSame(0, $found['storage_app_not_writable']);
    }

    public function test_a_missing_profile_photo_folder_is_reported_by_whether_it_could_be_made_and_is_never_made(): void
    {
        $this->skipWhenRoot();
        File::deleteDirectory($this->storage.'/app/public/profile-photos');

        $this->assertSame(0, $this->found()['profile_photos_not_writable'], 'the first upload creates it');
        $this->assertDirectoryDoesNotExist($this->storage.'/app/public/profile-photos');

        File::chmod($this->storage.'/app/public', 0555);

        $this->assertSame(1, $this->found()['profile_photos_not_writable']);
        $this->assertDirectoryDoesNotExist($this->storage.'/app/public/profile-photos');
    }

    public function test_free_disk_space_below_the_configured_minimum_is_a_problem(): void
    {
        $this->assertSame(1024 * 1024 * 1024, config('ops.min_free_bytes'));

        config(['ops.min_free_bytes' => 1]);
        $this->assertSame(0, $this->found()['disk_space_low']);

        config(['ops.min_free_bytes' => PHP_INT_MAX]);
        $this->assertSame(1, $this->found()['disk_space_low']);
    }

    public function test_the_disk_check_is_left_out_when_php_cannot_tell(): void
    {
        $ops = new class(app(ErrorLog::class)) extends OpsCheck
        {
            protected function freeBytes(): ?float
            {
                return null;
            }
        };

        $this->assertArrayNotHasKey('disk_space_low', $ops->run());
    }

    public function test_errors_logged_in_the_last_day_are_a_warning_with_their_count(): void
    {
        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->assertSame(['errors_logged_24h' => 0], app(OpsCheck::class)->warnings());

        File::append($this->storage.'/logs/laravel-2026-11-11.log', "[2026-11-11 23:00:00] production.ERROR: too old\n");
        File::append($this->storage.'/logs/laravel-2026-11-12.log', "[2026-11-12 09:59:59] production.ERROR: just outside\n[2026-11-12 10:00:00] production.ERROR: inside\n[2026-11-12 22:00:00] production.INFO: not an error\n");
        File::append($this->storage.'/logs/laravel-2026-11-13.log', "[2026-11-13 08:00:00] production.CRITICAL: today\n#0 a stack line with production.ERROR: in it\n");

        $this->assertSame(['errors_logged_24h' => 2], app(OpsCheck::class)->warnings());
    }

    public function test_the_command_prints_the_warning_in_its_own_column_and_a_warning_alone_is_not_a_failure(): void
    {
        File::append($this->storage.'/logs/laravel-'.now()->format('Y-m-d').'.log', '['.now()->format('Y-m-d H:i:s')."] production.ERROR: one\n");
        Log::spy();

        $this->artisan('ops:check')
            ->expectsOutputToContain('ok      storage_framework_not_writable')
            ->expectsOutputToContain('warn    errors_logged_24h')
            ->assertExitCode(0);

        // A warning is not reported as an error: that line would be counted as an error the next day, for ever.
        Log::shouldNotHaveReceived('error');
    }

    public function test_the_command_says_ok_for_a_clean_log_and_problem_for_a_folder(): void
    {
        $this->skipWhenRoot();
        File::chmod($this->storage.'/framework', 0555);

        $this->artisan('ops:check')
            ->expectsOutputToContain('PROBLEM storage_framework_not_writable')
            ->expectsOutputToContain('ok      errors_logged_24h')
            ->assertExitCode(1);
    }
}
