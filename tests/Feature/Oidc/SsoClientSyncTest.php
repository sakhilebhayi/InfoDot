<?php

namespace Tests\Feature\Oidc;

use Tests\TestCase;

/** The dot-ui pattern for the satellite client package: one source here, copied by sync.sh, guarded by a manifest. */
class SsoClientSyncTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/dotsso-sync-'.bin2hex(random_bytes(4));
        mkdir($this->repo.'/bootstrap', 0777, true);
        file_put_contents($this->repo.'/composer.json', json_encode(['name' => 'x/y', 'autoload' => ['psr-4' => ['App\\' => 'app/']]]));
        file_put_contents($this->repo.'/bootstrap/providers.php', "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");
        file_put_contents($this->repo.'/phpunit.xml', "<phpunit>\n    <testsuites>\n        <testsuite name=\"Feature\"><directory>tests/Feature</directory></testsuite>\n    </testsuites>\n</phpunit>\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->repo));
        parent::tearDown();
    }

    private function sync(): string
    {
        exec(escapeshellarg(base_path('packages/dot-sso-client/sync.sh')).' '.escapeshellarg($this->repo).' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));

        return implode("\n", $out);
    }

    public function test_sync_copies_the_package_and_writes_version_manifest_and_drift_test(): void
    {
        $this->sync();

        $this->assertFileExists($this->repo.'/packages/dot-sso-client/src/Http/Controllers/SsoController.php');
        $this->assertFileExists($this->repo.'/tests/Feature/DotSsoClientDriftTest.php');
        $this->assertFileDoesNotExist($this->repo.'/packages/dot-sso-client/sync.sh');
        $this->assertFileDoesNotExist($this->repo.'/packages/dot-sso-client/sync/DotSsoClientDriftTest.php');

        $version = explode("\n", trim(file_get_contents($this->repo.'/packages/dot-sso-client/VERSION')));
        $this->assertSame(trim(file_get_contents(base_path('packages/dot-sso-client/VERSION'))), $version[1]);

        // The manifest verifies with the system tool.
        exec('cd '.escapeshellarg($this->repo.'/packages/dot-sso-client').' && shasum -a 256 -c MANIFEST.sha256 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function test_sync_adds_the_host_wiring_once_and_rerunning_changes_nothing(): void
    {
        $this->sync();
        $first = [file_get_contents($this->repo.'/composer.json'), file_get_contents($this->repo.'/bootstrap/providers.php'), file_get_contents($this->repo.'/phpunit.xml')];
        $this->sync();
        $second = [file_get_contents($this->repo.'/composer.json'), file_get_contents($this->repo.'/bootstrap/providers.php'), file_get_contents($this->repo.'/phpunit.xml')];

        $this->assertSame($first, $second);
        $composer = json_decode($first[0], true);
        $this->assertSame('packages/dot-sso-client/src/', $composer['autoload']['psr-4']['Dot\\SsoClient\\']);
        $this->assertSame('app/', $composer['autoload']['psr-4']['App\\'], 'existing mappings survive');
        $this->assertSame(1, substr_count($first[1], 'DotSsoServiceProvider'));
        $this->assertSame(1, substr_count($first[2], 'DotSsoClient'));
    }

    public function test_a_hand_edit_in_the_copy_no_longer_matches_the_manifest(): void
    {
        $this->sync();
        file_put_contents($this->repo.'/packages/dot-sso-client/src/Support/Sso.php', "\n// hand edit", FILE_APPEND);

        exec('cd '.escapeshellarg($this->repo.'/packages/dot-sso-client').' && shasum -a 256 -c MANIFEST.sha256 2>&1', $out, $code);
        $this->assertNotSame(0, $code);
    }

    public function test_the_packaged_source_has_nothing_that_belongs_to_a_single_platform(): void
    {
        $all = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('packages/dot-sso-client/src'))) as $file) {
            $all .= $file->isFile() ? file_get_contents($file->getPathname()) : '';
        }

        $this->assertStringNotContainsString('App\\Models\\', $all, 'the package knows no platform model');
        $this->assertStringNotContainsString('infodot.co.za/', preg_replace('#https://infodot\.co\.za\'#', '', $all) ?? '', 'no hard-coded provider endpoints');
    }
}
