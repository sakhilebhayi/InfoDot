<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

/**
 * R-72 (O-03, O-04, O-10, O-12): the release workflow keeps its safety order. It cannot be run in a test, so the order of its steps is
 * read from the file: back up and verify, then maintenance mode (which must start), then the tested commit, then migrations; the smoke
 * check asks /ready; the uptime probe asks /ready; the log level is enforced.
 */
class ReleasePipelineTest extends TestCase
{
    private function at(string $haystack, string $needle): int
    {
        $position = strpos($haystack, $needle);
        $this->assertNotFalse($position, "deploy.yml no longer contains: $needle");

        return (int) $position;
    }

    public function test_the_release_backs_up_before_it_changes_anything_and_deploys_the_tested_commit(): void
    {
        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

        $backup = $this->at($deploy, '"$HOME/bin/dot-backup" run infodot');
        $verify = $this->at($deploy, '"$HOME/bin/dot-backup" verify infodot');
        $down = $this->at($deploy, '$PHP artisan down --retry=30 || { echo "::error::maintenance mode did not start');
        $reset = $this->at($deploy, 'git reset --hard "$DEPLOY_SHA"');
        $migrate = $this->at($deploy, '$PHP artisan migrate --force');

        $this->assertTrue($backup < $verify && $verify < $down && $down < $reset && $reset < $migrate, 'backup, verify, maintenance mode, tested commit, migrations: in that order');
        $this->at($deploy, 'DEPLOY_SHA="${{ github.sha }}"');
        $this->at($deploy, 'git merge-base --is-ancestor "$DEPLOY_SHA" origin/main');
        $this->assertStringNotContainsString('git reset --hard origin/main', $deploy, 'a release never takes whatever main is when the host fetches');
    }

    public function test_the_live_site_is_checked_for_readiness_and_logs_without_debug(): void
    {
        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
        $uptime = (string) file_get_contents(base_path('.github/workflows/uptime.yml'));

        $this->at($deploy, 'for PAGE in "/" "/login" "/up" "/ready"; do');
        $this->at($deploy, "sed -i 's/^LOG_LEVEL=.*/LOG_LEVEL=info/' .env");
        $this->assertStringContainsString('probe infodot.co.za /ready', $uptime);
        $this->assertFileExists(base_path('docs/programme/RUNBOOK.md'), 'the smoke check points people at the runbook');
        $this->assertFileExists(base_path('.github/dependabot.yml'));
    }

    public function test_a_queue_worker_gives_up_on_a_job_before_the_queue_hands_it_to_another(): void
    {
        // R-72 (R72-9, N-3): a worker's --timeout must be below the queue's retry_after, or a slow send is picked up and sent twice.
        $retryAfter = (int) config('queue.connections.database.retry_after');
        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
        $kernel = (string) file_get_contents(app_path('Console/Kernel.php'));

        foreach (['deploy.yml' => $deploy, 'Kernel.php' => $kernel] as $where => $text) {
            preg_match_all('/queue:work[^\n]*?--timeout=(\d+)/', $text, $m);
            $this->assertNotEmpty($m[1], "$where starts a worker with an explicit --timeout");
            foreach ($m[1] as $timeout) {
                $this->assertLessThan($retryAfter, (int) $timeout, "$where: --timeout=$timeout must be below retry_after=$retryAfter");
            }
        }
    }
}
