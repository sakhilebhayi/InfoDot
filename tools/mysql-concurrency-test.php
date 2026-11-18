<?php

/*
 * Real concurrency on a real MySQL. The PHPUnit suite runs on SQLite, which ignores row locks and has one writer, so it cannot show that the
 * `lockForUpdate()` calls in the seat ledger, seminar registration and answer acceptance actually hold. This script starts several PHP processes
 * at the same instant against one MySQL database and checks the invariants afterwards:
 *
 *   seats      6 people invited at once into companies with F free licences (paid, none, grandfathered+1): exactly F succeed each time
 *   capacity   N people register at once for C places: exactly C are registered, the rest wait
 *   accept     the asker and a moderator accept two different answers at once: exactly one answer ends up accepted
 *   counters   (not a race) the directory insight upsert inserts and then adds on MySQL, whose update clause differs from SQLite's
 *
 * Run by the CI job "concurrency on MySQL" after `php artisan migrate:fresh` (DB_* in the environment). It needs a throwaway database: it creates
 * users, a company, a seminar and a question. Exit code 0 only if every invariant held.
 *
 *   php tools/mysql-concurrency-test.php              (parent: seeds, starts workers, checks)
 *   php tools/mysql-concurrency-test.php worker ...   (internal)
 */

use App\Models\Answer;
use App\Models\LicenceBatch;
use App\Models\Questions;
use App\Models\Seminar;
use App\Models\Team;
use App\Models\TeamLicence;
use App\Models\TeamLicenceEvent;
use App\Models\User;
use App\Services\Insights\BusinessInsights;
use App\Services\Knowledge\AnswerService;
use App\Services\Licensing\Grandfathering;
use App\Services\Licensing\LicenceBatchEvents;
use App\Services\Licensing\SeatLedger;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// These races describe the R-53 rule (one free licence). The shipped default is still today's three until the owner sets the host, so the
// test sets the rule itself; the worker processes inherit it.
putenv('LICENCE_INCLUDED_SEATS=1');
$_ENV['LICENCE_INCLUDED_SEATS'] = $_SERVER['LICENCE_INCLUDED_SEATS'] = '1';

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, 'This test needs MySQL (DB_CONNECTION=mysql); got '.DB::connection()->getDriverName().".\n");
    exit(2);
}

// ---- worker ----------------------------------------------------------------------------------------------------------------

if (($argv[1] ?? null) === 'worker') {
    [, , $scenario, $subject, $actorId, $extra, $startAt] = $argv + array_fill(0, 7, null);
    // Everyone waits for the same instant, so the requests really overlap.
    while (microtime(true) < (float) $startAt) {
        usleep(500);
    }

    try {
        switch ($scenario) {
            case 'seats':
                $team = Team::findOrFail((int) $subject);
                $owner = User::findOrFail((int) $actorId);
                app(SeatLedger::class)->guard($team, fn () => $team->teamInvitations()->create(['email' => $extra, 'role' => 'member']), null, $owner, 'invited', $extra);
                echo "OK\n";
                break;
            case 'licence_event':
                echo 'STATUS:'.app(LicenceBatchEvents::class)->apply(json_decode(base64_decode((string) $extra), true))."\n";
                break;
            case 'capacity':
                echo 'STATUS:'.app(SeminarService::class)->register(Seminar::findOrFail((int) $subject), User::findOrFail((int) $actorId))."\n";
                break;
            case 'accept':
                app(AnswerService::class)->accept(Answer::withoutGlobalScopes()->findOrFail((int) $subject), User::findOrFail((int) $actorId));
                echo "OK\n";
                break;
        }
    } catch (Throwable $e) {
        echo 'ERROR:'.class_basename($e)."\n";
    }

    exit(0);
}

// ---- parent ----------------------------------------------------------------------------------------------------------------

$failed = false;
$say = function (string $line) {
    echo $line."\n";
};
$check = function (bool $ok, string $what) use (&$failed, $say) {
    $say(($ok ? 'ok   ' : 'FAIL ').$what);
    $failed = $failed || ! $ok;
};

/** Starts one worker per argument list at the same instant and returns each worker's output line. */
$race = function (string $scenario, array $jobs): array {
    $startAt = microtime(true) + 4.0;   // time for every process to boot first
    $procs = [];

    foreach ($jobs as $i => [$subject, $actor, $extra]) {
        $cmd = [PHP_BINARY, __FILE__, 'worker', $scenario, (string) $subject, (string) $actor, (string) $extra, sprintf('%.4F', $startAt)];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $procs[$i] = [$proc, $pipes];
    }

    $out = [];

    foreach ($procs as $i => [$proc, $pipes]) {
        $out[$i] = trim((string) stream_get_contents($pipes[1]));
        $err = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if ($out[$i] === '' && $err !== '') {
            $out[$i] = 'CRASH:'.substr($err, 0, 200);
        }
    }

    return $out;
};

$user = fn (string $name, array $extra = []) => User::factory()->create(array_merge(['name' => $name, 'email_verified_at' => now()], $extra));

// seats ---------------------------------------------------------------------------------------------------------------------
// R-53: a company has ONE free licence (the owner's), so room only exists when licences are paid for or a frozen headcount leaves room. Three
// races: a company with two paid licences (exactly 2 of 6 succeed), a brand-new company (none succeed), and a grandfathered company that
// bought one licence (exactly 1 succeeds, and the people it already has are not touched).
$seatRace = function (Team $team, User $owner, int $expectedFree, string $label) use ($race, $say, $check) {
    $ledger = app(SeatLedger::class);
    $s = $ledger->summary($team);
    $free = $s['capacity'] - $s['people'] - $s['reserved'];
    $say("seats ({$label}): company with {$free} free licence(s), 6 invitations at the same instant");
    $check($free === $expectedFree, "the company really has {$expectedFree} free licence(s) to race for (has {$free})");
    $out = $race('seats', array_map(fn ($i) => [$team->id, $owner->id, "race{$i}-{$team->id}@example.test"], range(1, 6)));
    $ok = count(array_filter($out, fn ($o) => $o === 'OK'));
    $bad = array_filter($out, fn ($o) => $o !== 'OK' && ! str_starts_with($o, 'ERROR:SeatLimitReached'));
    $check($ok === $expectedFree, "exactly {$expectedFree} invitation(s) succeeded (got {$ok}): ".implode(' ', $out));
    $check($team->teamInvitations()->count() === $expectedFree, 'and exactly that many invitations exist');
    $check($bad === [], 'every refusal was the seat limit, nothing else: '.implode(' ', $bad));
    $check(TeamLicenceEvent::where('team_id', $team->id)->where('type', 'blocked')->count() === 6 - $expectedFree, 'and every refusal was audited as blocked');
};

$owner = $user('Seat Owner');
$team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
TeamLicence::create(['team_id' => $team->id, 'purchased_seats' => 2, 'payment_status' => 'active']);
$seatRace($team, $owner, 2, 'two paid licences');

$newOwner = $user('New Company Owner');
$newTeam = Team::factory()->create(['user_id' => $newOwner->id, 'personal_team' => false]);
$seatRace($newTeam, $newOwner, 0, 'a new company: only the free licence');

$frozenOwner = $user('Frozen Owner');
$frozen = Team::factory()->create(['user_id' => $frozenOwner->id, 'personal_team' => false]);
foreach ([$user('Frozen Member 1'), $user('Frozen Member 2')] as $member) {
    $frozen->users()->attach($member, ['role' => 'member']);
}
app(Grandfathering::class)->apply([$frozen->id]);
TeamLicence::where('team_id', $frozen->id)->update(['purchased_seats' => 1, 'payment_status' => 'active']);
$seatRace($frozen, $frozenOwner, 1, 'a grandfathered company that bought one licence');
$check($frozen->users()->count() === 2, 'the grandfathered company still has both its members');

// licence confirmations ------------------------------------------------------------------------------------------------------
// R-53 phase 2: Dot.Finance may deliver the same signed `paid` more than once at the same instant (retries). Exactly one delivery is applied, the
// rest are duplicates, the batch is active once, one confirmation row exists, and capacity grew by the quantity once.
$payOwner = $user('Licence Owner');
$payTeam = Team::factory()->create(['user_id' => $payOwner->id, 'personal_team' => false]);
$payBatch = LicenceBatch::create(['team_id' => $payTeam->id, 'quantity' => 3, 'interval' => 'month', 'unit_price_minor' => 9900, 'total_minor' => 29700, 'currency' => 'ZAR']);
$payEvent = ['kind' => 'licence_batch', 'event_id' => 'race-paid-1', 'type' => 'paid', 'batch_uuid' => $payBatch->uuid, 'team_uuid' => $payTeam->uuid, 'quantity' => 3, 'interval' => 'month',
    'period_start' => now()->toIso8601String(), 'period_end' => now()->addMonth()->toIso8601String(), 'amount_minor' => 29700, 'currency' => 'ZAR', 'paystack_reference' => 'PSK-RACE'];
$say('licences: the same signed paid event delivered 6 times at the same instant');
$out = $race('licence_event', array_map(fn () => [$payBatch->id, $payOwner->id, base64_encode((string) json_encode($payEvent))], range(1, 6)));
$applied = count(array_filter($out, fn ($o) => $o === 'STATUS:applied'));
$dupes = count(array_filter($out, fn ($o) => $o === 'STATUS:duplicate'));
$check($applied === 1, "exactly one delivery applied (got {$applied}): ".implode(' ', $out));
$check($dupes === 5, "and the other 5 were duplicates (got {$dupes})");
$check(DB::table('platform_payment_confirmations')->where('event_id', 'race-paid-1')->count() === 1, 'one confirmation row');
$check($payBatch->fresh()->status === 'active', 'the batch is active');
$check(app(SeatLedger::class)->summary($payTeam)['capacity'] === 4, 'capacity is the free licence plus 3, once');
$check(TeamLicenceEvent::where('team_id', $payTeam->id)->where('type', 'batch_activated')->count() === 1, 'and it was audited once');

// capacity ------------------------------------------------------------------------------------------------------------------
$host = $user('Seminar Host');
$seminar = Seminar::create(['host_id' => $host->id, 'title' => 'Capacity race', 'summary' => 's', 'starts_at' => now()->addDays(2), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/r', 'status' => 'published', 'capacity' => 2]);
$people = array_map(fn ($i) => $user("Guest {$i}"), range(1, 7));
$say('capacity: 2 places, 7 people register at the same instant');
$out = $race('capacity', array_map(fn ($p) => [$seminar->id, $p->id, ''], $people));
$registered = count(array_filter($out, fn ($o) => $o === 'STATUS:registered'));
$waiting = count(array_filter($out, fn ($o) => $o === 'STATUS:waitlist'));
$check($registered === 2, "exactly 2 registered (got {$registered}): ".implode(' ', $out));
$check($waiting === 5, "the other 5 are on the waiting list (got {$waiting})");
$check(DB::table('seminar_registrations')->where('seminar_id', $seminar->id)->where('status', 'registered')->count() === 2, 'and the database holds exactly 2 registered rows');

// accept --------------------------------------------------------------------------------------------------------------------
$asker = $user('Asker');
$mod = $user('Moderator', ['is_moderator' => true]);
$helperA = $user('Helper A');
$helperB = $user('Helper B');
$question = Questions::create(['user_id' => $asker->id, 'team_id' => null, 'visibility' => 'public', 'question' => 'Which one is right?', 'description' => 'Two good answers, accepted at once.']);
$answers = array_map(fn (User $h) => Answer::create(['question_id' => $question->id, 'user_id' => $h->id, 'body' => 'A helpful answer from '.$h->name, 'body_hash' => md5($h->name), 'status' => Answer::PUBLISHED, 'is_self_answer' => false]), [$helperA, $helperB]);
$say('accept: the asker and a moderator accept two different answers at the same instant');
$out = $race('accept', [[$answers[0]->id, $asker->id, ''], [$answers[1]->id, $mod->id, '']]);
$accepted = Answer::withoutGlobalScopes()->where('question_id', $question->id)->whereNotNull('accepted_at')->count();
$check($accepted === 1, "exactly one answer is accepted afterwards (got {$accepted}): ".implode(' ', $out));
$check(! in_array(true, array_map(fn ($o) => str_starts_with($o, 'CRASH:'), $out), true), 'no worker crashed');

// counters ------------------------------------------------------------------------------------------------------------------
// Not a race: the directory's "appeared in a search" counters are written with an upsert whose update clause differs between SQLite (the
// test suite) and MySQL (production). This runs the real statement on MySQL: a first write inserts, a second adds to the same rows.
$say('counters: the directory insight upsert inserts, then adds, on MySQL');
$addAll = new ReflectionMethod(BusinessInsights::class, 'addAll');
$anyTeam = Team::query()->firstOrFail();
$rows = [
    ['team_id' => $anyTeam->id, 'day' => now()->toDateString(), 'metric' => 'search', 'count' => 1],
    ['team_id' => $anyTeam->id, 'day' => now()->toDateString(), 'metric' => 'service:1', 'count' => 1],
];
try {
    $addAll->invoke(app(BusinessInsights::class), 'business_stats_daily', $rows, ['team_id', 'day', 'metric']);
    $addAll->invoke(app(BusinessInsights::class), 'business_stats_daily', $rows, ['team_id', 'day', 'metric']);
    $counts = DB::table('business_stats_daily')->where('team_id', $anyTeam->id)->pluck('count', 'metric')->all();
    $check(count($counts) === 2 && (int) $counts['search'] === 2 && (int) $counts['service:1'] === 2, 'two rows, each counted twice (got '.json_encode($counts).')');
} catch (Throwable $e) {
    $check(false, 'the upsert ran on MySQL: '.$e->getMessage());
}

$say($failed ? 'RESULT: FAILED' : 'RESULT: every invariant held');
exit($failed ? 1 : 0);
