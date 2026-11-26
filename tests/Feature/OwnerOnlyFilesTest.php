<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 2026-11-26: twice after a release the web server answered 200 for /main/.env and other app files for a few seconds.
 * Files only the account can read cannot be sent by the web server, so the deploy makes them 600 and new logs are born 600.
 */
class OwnerOnlyFilesTest extends TestCase
{
    public function test_new_log_files_are_created_owner_only(): void
    {
        $this->assertSame(0600, config('logging.channels.single.permission'));
        $this->assertSame(0600, config('logging.channels.daily.permission'));

        $path = storage_path('logs/owner-only-test-'.uniqid().'.log');
        config(['logging.channels.owner_only_test' => ['driver' => 'single', 'path' => $path, 'permission' => config('logging.channels.single.permission')]]);
        Log::channel('owner_only_test')->info('owner-only check');

        $this->assertFileExists($path);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
        File::delete($path);
    }

    public function test_the_deploy_locks_secrets_and_probes_with_a_canary(): void
    {
        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        foreach (['.env', 'bootstrap/cache/config.php', 'database/database.sqlite', 'storage/oauth-private.key'] as $file) {
            $this->assertStringContainsString($file, $deploy);
        }
        $this->assertStringContainsString('chmod 600 "$f"', $deploy);
        $this->assertStringContainsString("find storage/logs -maxdepth 1 -type f -name '*.log' -exec chmod 600", $deploy);
        $this->assertStringContainsString('dot-owner-only-canary.txt', $deploy);
        // The probe reads a 200's body only through a counter; it never saves or prints it.
        $this->assertStringContainsString('KEY=value line(s)', $deploy);
        $this->assertStringNotContainsString('-o "$WORK/env', $deploy);
    }
}
