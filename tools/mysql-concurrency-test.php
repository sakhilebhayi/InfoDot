<?php

/*
 * Real concurrency on a real MySQL. The PHPUnit suite runs on SQLite, which ignores row locks and has one writer, so it cannot show that the
 * `lockForUpdate()` calls in the seat ledger, seminar registration and answer acceptance actually hold. This script starts several PHP processes
 * at the same instant against one MySQL database and checks the invariants afterwards:
 *
 *   seats      N people invited at once into a company with F free seats: exactly F succeed
 *   capacity   N people register at once for C places: exactly C are registered, the rest wait
 *   accept     the asker and a moderator accept two different answers at once: exactly one answer ends up accepted
 *
 * Run by the CI job "concurrency on MySQL" after `php artisan migrate:fresh` (DB_* in the environment). It needs a throwaway database: it creates
 * users, a company, a seminar and a question. Exit code 0 only if every invariant held.
 *
 *   php tools/mysql-concurrency-test.php              (parent: seeds, starts workers, checks)
 *   php tools/mysql-concurrency-test.php worker ...   (internal)
 */

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Seminar;
use App\Models\Team;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Licensing\SeatLedger;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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
$owner = $user('Seat Owner');
$team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
$free = app(SeatLedger::class)->summary($team)['capacity'] - app(SeatLedger::class)->summary($team)['people'] - app(SeatLedger::class)->summary($team)['reserved'];
$say("seats: company with {$free} free seat(s), 6 invitations at the same instant");
$out = $race('seats', array_map(fn ($i) => [$team->id, $owner->id, "race{$i}@example.test"], range(1, 6)));
$ok = count(array_filter($out, fn ($o) => $o === 'OK'));
$bad = array_filter($out, fn ($o) => $o !== 'OK' && ! str_starts_with($o, 'ERROR:SeatLimitReached'));
$check($free >= 1, 'the company has at least one free seat to race for');
$check($ok === $free, "exactly {$free} invitation(s) succeeded (got {$ok}): ".implode(' ', $out));
$check($team->teamInvitations()->count() === $free, 'and exactly that many invitations exist');
$check($bad === [], 'every refusal was the seat limit, nothing else: '.implode(' ', $bad));

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

$say($failed ? 'RESULT: FAILED' : 'RESULT: every invariant held');
exit($failed ? 1 : 0);
