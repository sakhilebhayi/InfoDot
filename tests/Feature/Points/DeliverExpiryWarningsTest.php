<?php

namespace Tests\Feature\Points;

use App\Mail\PointsExpiryReminder;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DeliverExpiryWarningsTest extends TestCase
{
    use FakesDopemine;
    use RefreshDatabase;

    /** @var list<array{warning_id: int, user_uuid: string, points: int, expires_on: string}> what the fake service holds as pending */
    private array $pending = [];

    /** @var array<int, string> warning_id => the outcome we reported */
    private array $reports = [];

    /** When true the fake answers every result report with a 503 (and records nothing). */
    private bool $reportsFail = false;

    /** When true, a warning reported "retry" stays in the pending list (a service that hands it out again at once). */
    private bool $retriesStayDue = false;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@infodot.test']);

        $this->upstream['GET /api/points/expiry-warnings'] = function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $due = array_values(array_filter($this->pending, fn ($w) => ! isset($this->reports[$w['warning_id']])
                || ($this->retriesStayDue && $this->reports[$w['warning_id']] === 'retry')));

            return Http::response(array_slice($due, 0, (int) ($q['limit'] ?? 100)));
        };
        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = function (Request $request) {
            if ($this->reportsFail) {
                return Http::response(['code' => 'unavailable', 'message' => 'x'], 503);
            }
            preg_match('#/expiry-warnings/([^/]+)/result$#', $request->url(), $m);
            $this->reports[(int) $m[1]] = json_decode($request->body(), true)['outcome'];

            return Http::response(['warning_id' => (int) $m[1], 'state' => 'closed']);
        };
        $this->fakeDopemine();
    }

    private function person(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email_verified_at' => now()]);
    }

    private function due(User|string $user, int $points = 12, string $on = '2026-11-04'): int
    {
        $id = count($this->pending) + 1;
        $this->pending[] = ['warning_id' => $id, 'user_uuid' => $user instanceof User ? $user->uuid : $user, 'points' => $points, 'expires_on' => $on];

        return $id;
    }

    private function run_(array $options = []): array
    {
        $code = Artisan::call('points:deliver-expiry-warnings', $options);

        return [$code, Artisan::output()];
    }

    private function logRows(): int
    {
        return DB::table('points_expiry_mail_log')->count();
    }

    // ---------------------------------------------------------------- the email

    public function test_a_due_warning_sends_exactly_one_plain_email_and_reports_delivered(): void
    {
        $user = $this->person(['name' => 'Thandi Nkosi']);
        $id = $this->due($user, 12, '2026-11-04');

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertSent(PointsExpiryReminder::class, 1);
        Mail::assertSent(PointsExpiryReminder::class, function (PointsExpiryReminder $mail) use ($user) {
            $html = $mail->render();
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertSame('12 of your Dot Points lapse on 4 November 2026', $mail->envelope()->subject);
            $this->assertStringContainsString('Hello Thandi Nkosi,', $html);
            $this->assertStringContainsString('12 of your Dot Points lapse on 4 November 2026.', $html);
            $this->assertStringContainsString(route('points'), $html);
            $this->assertStringContainsString(route('points').'#expiry-emails', $html);
            $this->assertStringContainsString('no cash value', $html);
            $this->assertStringContainsString('12 months after it was granted', $html);
            $this->assertStringContainsString('Email me before my points expire', $html);
            // Non-manipulative by design: nothing that counts down, hurries or guilt-trips.
            $text = strip_tags(substr($html, (int) strpos($html, 'Hello '), (int) strpos($html, '</td>', (int) strpos($html, 'Hello ')) - (int) strpos($html, 'Hello ')));
            $this->assertStringStartsWith('Hello Thandi Nkosi,', $text);
            $this->assertDoesNotMatchRegularExpression('/\b(hurry|last chance|don.t miss|don.t lose|act now|running out|only \d+ days?|countdown|urgent|expire[sd]? soon|before it.s too late)\b/i', $text);
            $this->assertDoesNotMatchRegularExpression('/(!|⏰|⚠)/u', $text);

            return true;
        });
        $this->assertSame([$id => 'delivered'], $this->reports);
        $this->assertSame(1, $this->logRows());
        $this->assertDatabaseHas('points_expiry_mail_log', ['warning_id' => (string) $id, 'user_id' => $user->id]);
        $this->assertStringContainsString('Emailed 1;', $output);
        $this->assertStringNotContainsString($user->email, $output);
        $this->assertStringNotContainsString($user->uuid, $output);
    }

    public function test_one_point_reads_correctly(): void
    {
        $this->due($this->person(), 1, '2026-12-01');

        $this->run_();

        Mail::assertSent(PointsExpiryReminder::class, fn ($mail) => $mail->envelope()->subject === '1 of your Dot Points lapses on 1 December 2026'
            && str_contains($mail->render(), '1 of your Dot Points lapses on 1 December 2026.'));
    }

    public function test_running_again_does_not_email_the_same_warning_twice(): void
    {
        $this->due($this->person());

        $this->run_();
        $this->run_();

        Mail::assertSent(PointsExpiryReminder::class, 1);
        $this->assertSame(1, $this->logRows());
    }

    // ---------------------------------------------------------------- closing without an email

    public function test_an_opted_out_person_gets_no_email_and_the_warning_is_closed(): void
    {
        $user = $this->person(['points_expiry_emails' => false]);
        $id = $this->due($user);

        [, $output] = $this->run_();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame([$id => 'permanent_failure'], $this->reports);
        $this->assertSame(0, $this->logRows());
        $this->assertStringContainsString('closed without an email 1', $output);
    }

    public function test_an_unknown_user_closes_the_warning_with_a_permanent_failure(): void
    {
        $id = $this->due('00000000-0000-4000-8000-000000000000');

        $this->run_();

        Mail::assertNothingSent();
        $this->assertSame([$id => 'permanent_failure'], $this->reports);
    }

    public function test_an_unverified_address_is_not_emailed(): void
    {
        $id = $this->due($this->person(['email_verified_at' => null]));

        $this->run_();

        Mail::assertNothingSent();
        $this->assertSame([$id => 'permanent_failure'], $this->reports);
    }

    // ---------------------------------------------------------------- failures

    public function test_a_mail_failure_reports_retry_and_leaves_no_log_row(): void
    {
        $user = $this->person();
        $id = $this->due($user);
        Log::spy();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('550 rejected '.$user->email));

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        $this->assertSame([$id => 'retry'], $this->reports);
        $this->assertSame(0, $this->logRows());
        $this->assertStringContainsString('to retry 1', $output);
        $logged = [];
        Log::shouldHaveReceived('warning')->withArgs(function (...$args) use (&$logged) {
            $logged[] = json_encode($args);

            return true;
        });
        $all = implode(' ', $logged);
        $this->assertStringContainsString('points.expiry_mail_failed', $all);
        $this->assertStringNotContainsString($user->email, $all);
        $this->assertStringNotContainsString($user->email, $output);
    }

    public function test_a_warning_that_comes_straight_back_after_retry_is_not_attempted_twice_in_one_run(): void
    {
        $this->due($this->person());
        $this->retriesStayDue = true;
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('down'));

        $this->run_();

        $this->assertSame(0, $this->logRows());
    }

    public function test_three_failed_sends_in_a_row_stop_the_run(): void
    {
        foreach (range(1, 5) as $i) {
            $this->due($this->person());
        }
        Mail::shouldReceive('to')->times(3)->andThrow(new RuntimeException('down'));

        [, $output] = $this->run_();

        $this->assertCount(3, $this->reports);
        $this->assertSame(['retry'], array_values(array_unique($this->reports)));
        $this->assertStringContainsString('stopping this run', $output);
    }

    public function test_when_the_result_report_fails_after_the_send_the_next_run_reports_delivered_without_a_second_email(): void
    {
        $user = $this->person();
        $id = $this->due($user);
        $this->reportsFail = true;

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertSent(PointsExpiryReminder::class, 1);
        $this->assertSame(1, $this->logRows());
        $this->assertSame([], $this->reports);
        $this->assertStringContainsString('could not report 1', $output);

        $this->reportsFail = false;
        [, $output] = $this->run_();

        Mail::assertSent(PointsExpiryReminder::class, 1); // still one
        $this->assertSame([$id => 'delivered'], $this->reports);
        $this->assertStringContainsString('reported again after an earlier send 1', $output);
    }

    public function test_the_service_not_knowing_the_warning_is_not_a_crash(): void
    {
        $this->due($this->person());
        $this->upstream['POST /api/points/expiry-warnings/{id}/result'] = Http::response(['code' => 'warning_not_found', 'message' => 'x'], 404);

        [$code] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertSent(PointsExpiryReminder::class, 1);
    }

    public function test_an_unreachable_service_is_a_quiet_no_op(): void
    {
        $this->due($this->person());
        $this->upstream['GET /api/points/expiry-warnings'] = fn () => throw new ConnectionException('down');

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertNothingSent();
        $this->assertStringContainsString('unreachable', $output);
    }

    public function test_a_rejected_signature_fails_the_command_without_showing_secrets(): void
    {
        $this->upstream['GET /api/points/expiry-warnings'] = Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401);

        [$code, $output] = $this->run_();

        $this->assertSame(1, $code);
        $this->assertStringNotContainsString(self::SECRET, $output);
        $this->assertStringNotContainsString('sha256=', $output);
    }

    // ---------------------------------------------------------------- configuration

    public function test_without_dopemine_configured_it_says_so_once_and_sends_nothing(): void
    {
        $this->due($this->person());
        config(['services.dopemine.secret' => null]);

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        $this->assertSame(1, substr_count($output, 'not configured'));
        $this->assertSame(0, $this->reached);
        Mail::assertNothingSent();
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function undeliverableMail(): array
    {
        return [
            'log' => [['mail.default' => 'log']],
            'array' => [['mail.default' => 'array']],
            'failover that falls back to the log' => [['mail.default' => 'failover']],
            'unknown mailer' => [['mail.default' => 'nope']],
            'placeholder from address' => [['mail.from.address' => 'hello@example.com']],
            'no from address' => [['mail.from.address' => '']],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('undeliverableMail')]
    public function test_without_a_real_mailer_it_reports_nothing_and_leaves_the_warnings_pending(array $config): void
    {
        $this->due($this->person());
        config($config);

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Mail not configured', $output);
        $this->assertSame(0, $this->reached, 'Dopemine must not be asked anything, so nothing can be closed.');
        $this->assertSame([], $this->reports);
        Mail::assertNothingSent();
        $this->assertSame(0, $this->logRows());
    }

    public function test_a_failover_between_real_mailers_counts_as_configured(): void
    {
        config(['mail.default' => 'failover', 'mail.mailers.failover.mailers' => ['smtp', 'sendmail']]);
        $this->due($this->person());

        $this->run_();

        Mail::assertSent(PointsExpiryReminder::class, 1);
    }

    // ---------------------------------------------------------------- the cap

    public function test_one_run_never_handles_more_than_200_whatever_is_asked(): void
    {
        foreach (range(1, 230) as $i) {
            $this->due('00000000-0000-4000-8000-0000000000'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT));
        }

        $this->run_(['--cap' => 9999]);

        $this->assertCount(200, $this->reports);

        $this->run_();
        $this->assertCount(230, $this->reports); // the next run takes the rest
    }

    public function test_the_cap_can_be_lowered(): void
    {
        foreach (range(1, 10) as $i) {
            $this->due('00000000-0000-4000-8000-000000000000');
        }

        $this->run_(['--cap' => 4]);

        $this->assertCount(4, $this->reports);
    }

    // ---------------------------------------------------------------- hygiene and schedule

    public function test_the_output_holds_counts_only_never_an_address_a_uuid_or_the_secret(): void
    {
        $users = [$this->person(), $this->person(['points_expiry_emails' => false])];
        foreach ($users as $user) {
            $this->due($user);
        }
        $this->due('00000000-0000-4000-8000-000000000000');

        [, $output] = $this->run_();

        foreach ($users as $user) {
            $this->assertStringNotContainsString($user->email, $output);
            $this->assertStringNotContainsString($user->uuid, $output);
            $this->assertStringNotContainsString($user->name, $output);
        }
        $this->assertStringNotContainsString(self::SECRET, $output);
        $this->assertStringNotContainsString('@', $output);
        $this->assertSame("Emailed 1; closed without an email 2; to retry 0; reported again after an earlier send 0; could not report 0.\n", $output);
    }

    public function test_it_is_scheduled_hourly_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'points:deliver-expiry-warnings'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
