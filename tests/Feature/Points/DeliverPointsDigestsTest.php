<?php

namespace Tests\Feature\Points;

use App\Mail\PointsDigestMail;
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

class DeliverPointsDigestsTest extends TestCase
{
    use FakesDopemine;
    use RefreshDatabase;

    /** @var list<array<string, mixed>> what the fake service holds as pending, per period */
    private array $pending = ['week' => [], 'month' => []];

    /** @var array<string, string> digest_id => the outcome we reported */
    private array $reports = [];

    /** When true the fake answers every result report with a 503 (and records nothing). */
    private bool $reportsFail = false;

    /** @var list<string> the periods the app asked for, in order */
    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@infodot.test']);

        $this->upstream['GET /api/points/digests'] = function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $this->asked[] = (string) $q['period'];
            $due = array_values(array_filter($this->pending[$q['period']], fn ($d) => ! isset($this->reports[$d['digest_id']])));

            return Http::response(array_slice($due, 0, (int) ($q['limit'] ?? 100)));
        };
        $this->upstream['POST /api/points/digests/{id}/result'] = function (Request $request) {
            if ($this->reportsFail) {
                return Http::response(['code' => 'unavailable', 'message' => 'x'], 503);
            }
            preg_match('#/digests/([^/]+)/result$#', $request->url(), $m);
            $this->reports[$m[1]] = json_decode($request->body(), true)['outcome'];

            return Http::response(['digest_id' => $m[1], 'state' => 'delivered']);
        };
        $this->fakeDopemine();
    }

    private function person(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email_verified_at' => now(), 'points_digest' => 'week']);
    }

    /** @param array<string, mixed> $override */
    private function due(User|string $user, array $override = [], string $period = 'week'): string
    {
        static $n = 0;
        $id = $override['digest_id'] ?? sprintf('5d0c8f0e-6a1b-4f0c-9d54-%012d', ++$n);
        $this->pending[$period][] = $override + [
            'digest_id' => $id,
            'user_uuid' => $user instanceof User ? $user->uuid : $user,
            'period' => $period,
            'period_start' => $period === 'week' ? '2026-10-12' : '2026-09-01',
            'period_end' => $period === 'week' ? '2026-10-18' : '2026-09-30',
            'points_earned' => 20,
            'awards' => [
                ['rule_key' => 'infodot.answer.accepted', 'label' => 'Answer accepted as the solution by the asker or a moderator who is not the answerer.', 'count' => 1, 'points' => 10],
                ['rule_key' => 'infodot.answer.published', 'label' => 'Substantive, original answer.', 'count' => 2, 'points' => 10],
            ],
            'points_reversed' => 0,
            'balance' => 25,
            'expiring_next_30_days' => null,
        ];

        return $id;
    }

    private function run_(string $period = 'week', array $options = []): array
    {
        $code = Artisan::call('points:deliver-digests', ['period' => $period] + $options);

        return [$code, Artisan::output()];
    }

    private function logRows(): int
    {
        return DB::table('points_digest_mail_log')->count();
    }

    // ---------------------------------------------------------------- the email

    public function test_a_due_digest_sends_exactly_one_plain_email_with_the_exact_numbers_and_reports_delivered(): void
    {
        $user = $this->person(['name' => 'Thandi Nkosi']);
        $id = $this->due($user);

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertSent(PointsDigestMail::class, 1);
        Mail::assertSent(PointsDigestMail::class, function (PointsDigestMail $mail) use ($user) {
            $html = $mail->render();
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertSame('Your Dot Points for 12 to 18 October 2026', $mail->envelope()->subject);
            $this->assertStringContainsString('Hello Thandi Nkosi,', $html);
            $this->assertStringContainsString('in the week of 12 to 18 October 2026', $html);
            $this->assertStringContainsString('You earned 20 points', $html);
            $this->assertStringContainsString('10 points, 1 time: Answer accepted as the solution by the asker or a moderator who is not the answerer.', $html);
            $this->assertStringContainsString('10 points, 2 times: Substantive, original answer.', $html);
            $this->assertStringContainsString('Your balance is 25 points.', $html);
            $this->assertStringContainsString('href="'.route('points').'"', $html);
            $this->assertStringContainsString(route('points').'#digest-emails', $html);
            $this->assertStringContainsString('To stop it, choose', $html);
            $this->assertStringContainsString('no cash value', $html);
            // Nothing to take back and nothing about to lapse: neither is mentioned.
            $this->assertStringNotContainsString('taken back', $html);
            $this->assertStringNotContainsString('lapse within', $html);

            return true;
        });
        $this->assertSame([$id => 'delivered'], $this->reports);
        $this->assertDatabaseHas('points_digest_mail_log', ['digest_id' => $id, 'user_id' => $user->id, 'period' => 'week', 'period_start' => '2026-10-12']);
        $this->assertStringContainsString('Emailed 1;', $output);
    }

    public function test_a_monthly_digest_reads_as_the_month_and_shows_reversals_and_expiring_points_calmly(): void
    {
        $user = $this->person(['points_digest' => 'month', 'name' => 'Thandi Nkosi']);
        $this->due($user, [
            'points_earned' => 1,
            'awards' => [['rule_key' => 'infodot.answer.published', 'label' => 'Substantive, original answer.', 'count' => 1, 'points' => 1]],
            'points_reversed' => 5,
            'balance' => 1,
            'expiring_next_30_days' => ['points' => 12, 'first_at' => '2026-11-04T00:00:00+00:00'],
        ], 'month');

        $this->run_('month');

        Mail::assertSent(PointsDigestMail::class, function (PointsDigestMail $mail) {
            $html = $mail->render();
            $this->assertSame('Your Dot Points for September 2026', $mail->envelope()->subject);
            $this->assertStringContainsString('in September 2026', $html);
            $this->assertStringContainsString('You earned 1 point', $html);
            $this->assertStringContainsString('1 point, 1 time: Substantive, original answer.', $html);
            // Reversals sit beside the earnings, never netted into them.
            $this->assertStringContainsString('Separately, 5 points that were awarded earlier were taken back in this period', $html);
            $this->assertStringContainsString('not subtracted from the 1 point above', $html);
            $this->assertStringContainsString('Your balance is 1 point.', $html);
            $this->assertStringContainsString('12 of yours lapse within the next 30 days, the first on 4 November 2026.', $html);
            $this->assertStringContainsString('monthly summary', $html);

            return true;
        });
    }

    public function test_the_email_has_none_of_the_pressure_comparison_or_streak_language(): void
    {
        $user = $this->person();
        $this->due($user, ['points_reversed' => 3, 'expiring_next_30_days' => ['points' => 4, 'first_at' => '2026-11-01T00:00:00+00:00']]);

        $this->run_();

        Mail::assertSent(PointsDigestMail::class, function (PointsDigestMail $mail) {
            // Only our own words: the rule labels come from the points service and are shown as given.
            $text = strip_tags($mail->render());
            $text = substr($text, (int) strpos($text, 'Hello '));
            $text = substr($text, 0, (int) strpos($text, '© ') ?: null);
            $this->assertStringStartsWith('Hello ', $text);
            $this->assertStringContainsString('Nothing else changes.', $text);
            foreach ([
                'hurry', 'last chance', "don't miss", "don't lose", 'act now', 'running out', 'urgent', 'before it', 'countdown', 'expire soon', 'expiring soon',
                'streak', 'in a row', 'consecutive', 'leaderboard', 'rank', 'top contributor', 'other people', 'everyone else', 'compared', 'than last',
                'behind', 'catch up', 'falling', 'fell', 'missed', 'miss out', 'keep it up', 'on a roll', 'don\'t break', 'do not break', 'only ', 'disappoint',
                'we noticed', 'you haven', 'come back', 'we miss', 'last week', 'last month', 'better than', 'worse than', 'record',
            ] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase($banned, $text, "The digest must not contain \"{$banned}\".");
            }
            $this->assertStringNotContainsString('!', $text);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text);

            return true;
        });
    }

    public function test_a_label_from_the_service_is_shown_as_plain_text(): void
    {
        $user = $this->person();
        $this->due($user, ['awards' => [['rule_key' => 'x', 'label' => "<script>alert(1)</script>[click](http://evil.test) **big**\nsecond line", 'count' => 1, 'points' => 20]]]);

        $this->run_();

        Mail::assertSent(PointsDigestMail::class, function (PointsDigestMail $mail) {
            $html = $mail->render();
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringNotContainsString('href="http://evil.test', $html);
            $this->assertStringNotContainsString('<strong>big', $html);

            return true;
        });
    }

    public function test_running_again_does_not_email_the_same_digest_twice(): void
    {
        $this->due($this->person());

        $this->run_();
        $this->run_();

        Mail::assertSent(PointsDigestMail::class, 1);
        $this->assertSame(1, $this->logRows());
    }

    // ---------------------------------------------------------------- closing without an email

    public function test_the_default_is_not_to_send(): void
    {
        $user = $this->person(['points_digest' => 'off']);
        $id = $this->due($user);

        [, $output] = $this->run_();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame([$id => 'skipped'], $this->reports);
        $this->assertSame(0, $this->logRows());
        $this->assertStringContainsString('skipped without an email 1', $output);
        $this->assertSame('off', User::factory()->create()->fresh()->points_digest);
    }

    public function test_someone_who_chose_the_other_period_is_skipped(): void
    {
        $monthly = $this->person(['points_digest' => 'month']);
        $weekly = $this->person(['points_digest' => 'week']);
        $a = $this->due($monthly, [], 'week');
        $b = $this->due($weekly, [], 'month');

        $this->run_('week');
        $this->run_('month');

        Mail::assertNothingSent();
        $this->assertSame([$a => 'skipped', $b => 'skipped'], $this->reports);
    }

    public function test_an_unknown_user_is_skipped(): void
    {
        $id = $this->due('00000000-0000-4000-8000-000000000000');

        $this->run_();

        Mail::assertNothingSent();
        $this->assertSame([$id => 'skipped'], $this->reports);
    }

    public function test_an_unverified_address_is_not_emailed(): void
    {
        $id = $this->due($this->person(['email_verified_at' => null]));

        $this->run_();

        Mail::assertNothingSent();
        $this->assertSame([$id => 'skipped'], $this->reports);
    }

    public function test_a_second_id_for_the_same_period_is_never_a_second_email(): void
    {
        $user = $this->person();
        $first = $this->due($user);
        $second = $this->due($user);

        $this->run_();

        Mail::assertSent(PointsDigestMail::class, 1);
        $this->assertSame([$first => 'delivered', $second => 'skipped'], $this->reports);
    }

    public function test_the_week_and_the_month_are_asked_for_separately(): void
    {
        $this->due($this->person(['points_digest' => 'month']), [], 'month');

        $this->run_('month');

        $this->assertSame(['month'], array_values(array_unique($this->asked)));
        Mail::assertSent(PointsDigestMail::class, 1);
    }

    public function test_an_unknown_period_is_refused_without_asking_anything(): void
    {
        [$code, $output] = $this->run_('fortnight');

        $this->assertSame(2, $code);
        $this->assertStringContainsString('"week" or "month"', $output);
        $this->assertSame(0, $this->reached);
    }

    public function test_rows_that_are_not_the_documented_shape_are_left_alone(): void
    {
        $user = $this->person();
        $this->due($user, ['points_earned' => 0]);
        $this->due($user, ['period' => 'month']);
        $this->due($user, ['awards' => 'nope']);
        $this->due($user, ['period_start' => '2026-13-45']);

        [$code] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertNothingSent();
        $this->assertSame([], $this->reports);
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
        $this->assertStringContainsString('points.digest_mail_failed', $all);
        $this->assertStringNotContainsString($user->email, $all);
        $this->assertStringNotContainsString($user->email, $output);
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
        Mail::assertSent(PointsDigestMail::class, 1);
        $this->assertSame(1, $this->logRows());
        $this->assertSame([], $this->reports);
        $this->assertStringContainsString('could not report 1', $output);

        $this->reportsFail = false;
        [, $output] = $this->run_();

        Mail::assertSent(PointsDigestMail::class, 1); // still one
        $this->assertSame([$id => 'delivered'], $this->reports);
        $this->assertStringContainsString('reported again after an earlier send 1', $output);
    }

    public function test_the_service_not_knowing_the_digest_is_not_a_crash(): void
    {
        $this->due($this->person());
        $this->upstream['POST /api/points/digests/{id}/result'] = Http::response(['code' => 'digest_not_found', 'message' => 'x'], 404);

        [$code] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertSent(PointsDigestMail::class, 1);
    }

    public function test_an_unreachable_service_is_a_quiet_no_op(): void
    {
        $this->due($this->person());
        $this->upstream['GET /api/points/digests'] = fn () => throw new ConnectionException('down');

        [$code, $output] = $this->run_();

        $this->assertSame(0, $code);
        Mail::assertNothingSent();
        $this->assertStringContainsString('unreachable', $output);
    }

    public function test_a_rejected_signature_fails_the_command_without_showing_secrets(): void
    {
        $this->upstream['GET /api/points/digests'] = Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401);

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
    public function test_without_a_real_mailer_it_reports_nothing_and_leaves_the_digests_pending(array $config): void
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

        Mail::assertSent(PointsDigestMail::class, 1);
    }

    // ---------------------------------------------------------------- the cap, hygiene, schedule

    public function test_one_run_never_handles_more_than_200_whatever_is_asked(): void
    {
        foreach (range(1, 230) as $i) {
            $this->due('00000000-0000-4000-8000-000000000000');
        }

        $this->run_('week', ['--cap' => 9999]);

        $this->assertCount(200, $this->reports);

        $this->run_();
        $this->assertCount(230, $this->reports);
    }

    public function test_the_output_holds_counts_only_never_an_address_a_uuid_or_the_secret(): void
    {
        $users = [$this->person(), $this->person(['points_digest' => 'off'])];
        foreach ($users as $user) {
            $this->due($user);
        }

        [, $output] = $this->run_();

        foreach ($users as $user) {
            $this->assertStringNotContainsString($user->email, $output);
            $this->assertStringNotContainsString($user->uuid, $output);
            $this->assertStringNotContainsString($user->name, $output);
        }
        $this->assertStringNotContainsString(self::SECRET, $output);
        $this->assertStringNotContainsString('@', $output);
        $this->assertSame("Emailed 1; skipped without an email 1; to retry 0; reported again after an earlier send 0; could not report 0.\n", $output);
    }

    public function test_it_is_scheduled_weekly_on_monday_at_eight_and_monthly_on_the_first_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events());
        $week = $events->first(fn ($e) => str_contains($e->command, 'points:deliver-digests week'));
        $month = $events->first(fn ($e) => str_contains($e->command, 'points:deliver-digests month'));

        $this->assertNotNull($week);
        $this->assertNotNull($month);
        // First run at 08:00 app time; the later hourly runs the same day only let a failed send be retried.
        $this->assertSame('0 8-23 * * 1', $week->expression);
        $this->assertSame('0 8-23 1 * *', $month->expression);
        foreach ([$week, $month] as $event) {
            $this->assertTrue($event->withoutOverlapping);
            $this->assertTrue($event->onOneServer);
        }
    }
}
