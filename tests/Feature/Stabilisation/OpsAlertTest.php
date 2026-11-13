<?php

namespace Tests\Feature\Stabilisation;

use App\Mail\OpsAlert as OpsAlertMail;
use App\Services\Ops\ErrorLog;
use App\Services\Ops\OpsAlert;
use App\Services\Ops\OpsCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * The host has no error tracker and no monitor: a 500 was only found when somebody read the log. ops:alert reads the
 * new error lines of the daily log and the ops:check conditions every ten minutes and sends one plain email, at most
 * once an hour, with nothing in it that identifies a person.
 */
class OpsAlertTest extends TestCase
{
    private string $dir;

    private string $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/ops-alert-'.Str::random(10));
        File::ensureDirectoryExists($this->dir.'/logs');
        $this->address = 'ops-'.Str::lower(Str::random(6)).'@alerts.test';

        config([
            'logging.channels.daily.path' => $this->dir.'/logs/laravel.log',
            'ops.alert_state_path' => $this->dir.'/ops-alert.json',
            'ops.alert_address' => $this->address,
        ]);
        Cache::flush();
        Mail::fake();
        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->checks([]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @param  array<string, int>  $found */
    private function checks(array $found): void
    {
        $this->mock(OpsCheck::class, fn ($m) => $m->shouldReceive('run')->andReturn($found));
    }

    private function logFile(?string $day = null): string
    {
        return $this->dir.'/logs/laravel-'.($day ?? now()->format('Y-m-d')).'.log';
    }

    private function write(string $level, string $message, ?string $class = null, ?string $at = null, ?string $day = null): void
    {
        $at ??= now()->format('Y-m-d H:i:s');
        $line = "[$at] production.$level: $message";

        if ($class !== null) {
            $line .= ' {"userId":7,"exception":"[object] ('.$class.'(code: 0): '.$message." at /home/acct/app/Thing.php:10)\n[stacktrace]\n#0 /home/acct/app/Thing.php(10): App\\\\Thing->secretMethod()\n#1 {main}\n\"} ";
        }

        File::append($this->logFile($day), $line."\n");
    }

    private function alert(): string
    {
        return app(OpsAlert::class)->run();
    }

    public function test_a_quiet_log_and_passing_checks_send_nothing(): void
    {
        $this->write('INFO', 'a person signed in');
        $this->write('WARNING', 'something slow');

        $this->assertSame('quiet', $this->alert());
        Mail::assertNothingSent();
    }

    public function test_a_missing_log_file_is_not_an_error(): void
    {
        $this->assertSame('quiet', $this->alert());
        $this->assertSame('quiet', $this->alert());
        Mail::assertNothingSent();
    }

    public function test_new_errors_send_one_email_with_counts_and_masked_first_lines_and_no_trace(): void
    {
        $token = Str::random(40);
        $this->write('ERROR', "Duplicate entry 'thandi.m@example.co.za' for key users_email_unique", 'Illuminate\\Database\\QueryException');
        $this->write('ERROR', "Duplicate entry 'thandi.m@example.co.za' for key users_email_unique", 'Illuminate\\Database\\QueryException');
        $this->write('CRITICAL', "Refused token=$token from 196.25.1.9 for 0821234567", 'RuntimeException');
        $this->write('ERROR', 'readiness check failed '.str_repeat('long ', 80));
        $this->write('INFO', 'not an error');
        $this->checks(['outbox_points_parked' => 3, 'holds_not_released_1h' => 0]);

        $this->assertSame('sent', $this->alert());

        Mail::assertSent(OpsAlertMail::class, 1);
        $mail = Mail::sent(OpsAlertMail::class)->first();
        $this->assertTrue($mail->hasTo($this->address));
        $this->assertSame(4, $mail->total);
        $this->assertSame(['Illuminate\\Database\\QueryException' => 2, 'RuntimeException' => 1, OpsAlert::NO_CLASS => 1], $mail->classes);
        $this->assertCount(3, $mail->samples, 'the repeated error is listed once');
        $this->assertSame(['outbox_points_parked' => 3], $mail->checks);

        foreach ($mail->samples as $sample) {
            $this->assertLessThanOrEqual(200, mb_strlen($sample));
        }

        $mail->assertSeeInText('Illuminate\\Database\\QueryException');
        $mail->assertSeeInText("Duplicate entry '[email]'");
        $mail->assertSeeInText('outbox_points_parked');
        foreach (['thandi.m', 'example.co.za', $token, '196.25.1.9', '0821234567', 'stacktrace', 'secretMethod', '#0', 'userId', '/home/acct'] as $secret) {
            $mail->assertDontSeeInText($secret);
        }
    }

    public function test_at_most_five_distinct_errors_are_quoted(): void
    {
        foreach (range(1, 8) as $i) {
            $this->write('ERROR', "failure kind $i", 'RuntimeException');
        }

        $this->alert();

        $mail = Mail::sent(OpsAlertMail::class)->first();
        $this->assertSame(8, $mail->total);
        $this->assertCount(5, $mail->samples);
    }

    public function test_a_second_run_within_the_hour_is_held_and_its_count_is_carried_into_the_next_email(): void
    {
        $this->write('ERROR', 'first failure', 'RuntimeException');
        $this->assertSame('sent', $this->alert());

        Carbon::setTestNow('2026-11-13 10:10:00');
        foreach (range(1, 4) as $i) {
            $this->write('ERROR', 'second failure', 'LogicException');
        }
        $this->assertSame('held', $this->alert());

        Carbon::setTestNow('2026-11-13 10:50:00');
        $this->assertSame('held', $this->alert());
        Mail::assertSent(OpsAlertMail::class, 1);

        Carbon::setTestNow('2026-11-13 11:00:01');
        $this->write('ERROR', 'third failure', 'LogicException');
        $this->assertSame('sent', $this->alert());

        Mail::assertSent(OpsAlertMail::class, 2);
        $second = Mail::sent(OpsAlertMail::class)->last();
        $this->assertSame(5, $second->total);
        $this->assertSame(4, $second->held);
        $this->assertSame(['LogicException' => 5], $second->classes, 'the first alert is not repeated');
        $second->assertSeeInText('4 more since the alert at 10:00');

        // Nothing new: nothing sent, however long it has been.
        Carbon::setTestNow('2026-11-13 13:00:00');
        $this->assertSame('quiet', $this->alert());
        Mail::assertSent(OpsAlertMail::class, 2);
    }

    public function test_without_an_address_nothing_is_read_or_sent_and_one_line_a_day_says_so(): void
    {
        config(['ops.alert_address' => '']);
        $this->write('ERROR', 'a failure', 'RuntimeException');
        Log::spy();

        $this->assertSame('off', $this->alert());
        $this->assertSame('off', $this->alert());

        Mail::assertNothingSent();
        $this->assertFileDoesNotExist($this->dir.'/ops-alert.json');
        Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, 'OPS_ALERT_ADDRESS'))->once();

        Carbon::setTestNow('2026-11-14 10:00:00');
        $this->assertSame('off', $this->alert());
        Log::shouldHaveReceived('info')->withArgs(fn ($m) => str_contains($m, 'OPS_ALERT_ADDRESS'))->twice();
    }

    public function test_the_rest_of_yesterdays_file_is_read_before_todays_after_midnight(): void
    {
        Carbon::setTestNow('2026-11-13 23:50:00');
        $this->write('ERROR', 'before the last run of the day', 'RuntimeException');
        $this->assertSame('sent', $this->alert());

        $this->write('ERROR', 'late last night', 'LogicException', '2026-11-13 23:58:00');
        Carbon::setTestNow('2026-11-14 01:00:00');
        $this->write('ERROR', 'early this morning', 'DomainException');

        $this->assertSame('sent', $this->alert());

        $mail = Mail::sent(OpsAlertMail::class)->last();
        $this->assertSame(['LogicException' => 1, 'DomainException' => 1], $mail->classes);

        // The new day's file is followed from where this run stopped.
        Carbon::setTestNow('2026-11-14 03:00:00');
        $this->write('ERROR', 'later', 'DomainException');
        $this->alert();
        $this->assertSame(['DomainException' => 1], Mail::sent(OpsAlertMail::class)->last()->classes);
    }

    public function test_yesterdays_file_already_deleted_and_a_replaced_file_do_not_break_the_run(): void
    {
        Carbon::setTestNow('2026-11-13 23:50:00');
        $this->write('ERROR', 'one', 'RuntimeException');
        $this->alert();
        File::delete($this->logFile());

        Carbon::setTestNow('2026-11-14 01:00:00');
        $this->assertSame('quiet', $this->alert());

        // A file shorter than the remembered position was replaced: it is read from its start.
        $this->write('ERROR', str_repeat('x', 300), 'RuntimeException');
        Carbon::setTestNow('2026-11-14 03:00:00');
        $this->alert();
        File::put($this->logFile(), '');
        $this->write('ERROR', 'short', 'LogicException');
        Carbon::setTestNow('2026-11-14 05:00:00');
        $this->assertSame('sent', $this->alert());
        $this->assertSame(['LogicException' => 1], Mail::sent(OpsAlertMail::class)->last()->classes);
    }

    public function test_a_line_still_being_written_is_left_for_the_next_run(): void
    {
        File::append($this->logFile(), '[2026-11-13 10:00:00] production.ERROR: half a li');
        $this->assertSame('quiet', $this->alert());

        File::append($this->logFile(), "ne\n");
        $this->assertSame('sent', $this->alert());
        $this->assertSame(1, Mail::sent(OpsAlertMail::class)->first()->total);
    }

    public function test_a_mail_failure_is_logged_not_thrown_and_the_errors_go_out_with_the_next_run(): void
    {
        $this->write('ERROR', 'a failure', 'RuntimeException');
        Mail::shouldReceive('to')->once()->andThrow(new TransportException('Connection to smtp.internal-host.example refused for user mailbox-login'));
        Log::spy();

        $this->assertSame('failed', $this->alert());
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx = []) => str_contains($m, 'ops.alert') && ! str_contains(json_encode($ctx), 'internal-host'))->once();

        $this->app->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        Mail::fake();
        Carbon::setTestNow('2026-11-13 10:10:00');
        $this->assertSame('sent', $this->alert());
        $this->assertSame(1, Mail::sent(OpsAlertMail::class)->first()->total);
    }

    public function test_a_failing_check_alone_is_emailed_once_a_day_not_every_hour(): void
    {
        $this->checks(['sso_signing_keys_missing' => 1]);

        $this->assertSame('sent', $this->alert());
        $this->assertSame(0, Mail::sent(OpsAlertMail::class)->first()->total);

        Carbon::setTestNow('2026-11-13 12:00:00');
        $this->assertSame('quiet', $this->alert());

        // A different check starts failing: that is news.
        $this->checks(['sso_signing_keys_missing' => 1, 'bootstrap_cache_not_writable' => 1]);
        Carbon::setTestNow('2026-11-13 14:00:00');
        $this->assertSame('sent', $this->alert());

        $this->checks(['sso_signing_keys_missing' => 1]);
        Carbon::setTestNow('2026-11-14 09:00:00');
        $this->assertSame('sent', $this->alert());
        Mail::assertSent(OpsAlertMail::class, 3);
    }

    public function test_the_lines_ops_check_logs_itself_are_not_counted_twice(): void
    {
        $this->write('ERROR', 'ops.check');
        $this->write('ERROR', 'Operations check: outbox_points_parked = 3', 'RuntimeException');

        $this->assertSame('quiet', $this->alert());
    }

    public function test_a_check_that_cannot_run_is_itself_reported(): void
    {
        $this->mock(OpsCheck::class, fn ($m) => $m->shouldReceive('run')->andThrow(new \RuntimeException('SQLSTATE[HY000] db-host.example refused')));

        $this->assertSame('sent', $this->alert());
        $mail = Mail::sent(OpsAlertMail::class)->first();
        $this->assertSame(['ops_check_could_not_run' => 1], $mail->checks);
        $mail->assertDontSeeInText('db-host');
    }

    public function test_json_formatted_log_lines_are_read_too(): void
    {
        $line = json_encode(['message' => 'boom', 'context' => ['exception' => ['class' => 'RuntimeException', 'message' => 'boom']], 'level' => 400, 'level_name' => 'ERROR', 'channel' => 'production', 'datetime' => '2026-11-13T09:59:00.000000+02:00']);
        $entry = app(ErrorLog::class)->parse($line."\n");

        $this->assertSame(['time' => '2026-11-13 09:59:00', 'level' => 'ERROR', 'class' => 'RuntimeException', 'message' => 'boom'], $entry);
        $this->assertNull(app(ErrorLog::class)->parse(json_encode(['message' => 'fine', 'level_name' => 'INFO'])."\n"));
        $this->assertNull(app(ErrorLog::class)->parse("#0 /home/acct/app/Thing.php(10): production.ERROR: not a first line\n"));
    }

    public function test_the_command_runs_and_is_scheduled_every_ten_minutes(): void
    {
        $this->write('ERROR', 'a failure', 'RuntimeException');

        $this->artisan('ops:alert')->expectsOutputToContain('sent')->assertExitCode(0);
        Mail::assertSent(OpsAlertMail::class, 1);

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'ops:alert'));
        $this->assertNotNull($event);
        $this->assertSame('*/10 * * * *', $event->expression);
    }

    public function test_the_address_comes_from_the_environment_setting_named_in_the_example_file(): void
    {
        $this->assertStringContainsString("\nOPS_ALERT_ADDRESS=\n", (string) file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("env('OPS_ALERT_ADDRESS')", (string) file_get_contents(config_path('ops.php')));
    }
}
