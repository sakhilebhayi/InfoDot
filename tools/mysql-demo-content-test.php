<?php

/*
 * The demonstration content (R-49) installed and removed on a real MySQL. The PHPUnit suite proves it on SQLite, but production runs MySQL,
 * and removal reads the schema itself (foreign keys, columns), which the two engines report differently. This script runs the real commands,
 * each in its own PHP process as on the host, and checks:
 *
 *   refuse     in production, `demo:install` without --force fails and creates nothing
 *   install    `demo:install --force` succeeds, the two demonstration companies are listed for a signed-out visitor, their files exist,
 *              and no moderator was created (that needs --with-moderator)
 *   again      a second `demo:install --force` creates nothing: every table's count is unchanged
 *   remove     `demo:remove --force` succeeds and every table's row count equals the count before the install; the files are gone.
 *              A second database on the same server (as on the shared host) does not confuse it.
 *
 * Run by the CI job "migrations on MySQL" after `php artisan migrate:fresh` (DB_* and APP_ENV=production in the environment). The commands'
 * output is never printed: it contains the one-time passwords. Exit code 0 only if every check held.
 *
 *   php tools/mysql-demo-content-test.php
 *
 * DEMO_TEST_ANY_DRIVER=1 lets it run against another engine, to try the script itself on a development machine.
 */

use App\Models\DemoRecord;
use App\Models\Team;
use App\Models\User;
use App\Services\Directory\BusinessDirectory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::connection()->getDriverName() !== 'mysql' && ! getenv('DEMO_TEST_ANY_DRIVER')) {
    fwrite(STDERR, 'This test needs MySQL (DB_CONNECTION=mysql); got '.DB::connection()->getDriverName().".\n");
    exit(2);
}

if (! $app->isProduction()) {
    fwrite(STDERR, "This test needs APP_ENV=production, so that the --force rule is exercised.\n");
    exit(2);
}

$failures = 0;

$check = function (string $what, bool $held, string $detail = '') use (&$failures): void {
    echo ($held ? 'ok    ' : 'FAIL  ').$what.($detail !== '' && ! $held ? "\n      ".$detail : '')."\n";
    $failures += $held ? 0 : 1;
};

/** Every table's row count. @return array<string, int> */
$counts = function (): array {
    $counts = [];
    foreach (array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name') as $table) {
        $counts[$table] = DB::table($table)->count();
    }
    ksort($counts);

    return $counts;
};

/** The tables whose counts differ, as "table: before -> after". */
$difference = function (array $before, array $after): string {
    $lines = [];
    foreach ($before + $after as $table => $ignored) {
        if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
            $lines[] = $table.': '.($before[$table] ?? 'absent').' -> '.($after[$table] ?? 'absent');
        }
    }

    return implode('; ', $lines);
};

/** How many files the demonstration writes to: business media on the private disk, profile pictures on the public one. */
$files = fn (): int => count(Storage::disk('local')->allFiles('attachments/branding')) + count(Storage::disk('public')->allFiles('profile-photos'));

/** Run an artisan command in its own process. The output is kept from the log (it holds passwords) and shown only as a line count. */
$artisan = function (string $arguments): int {
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../artisan').' '.$arguments.' --no-interaction 2>&1', $output, $code);
    echo '      php artisan '.$arguments.' -> exit '.$code.', '.count($output)." lines of output (not shown)\n";

    return $code;
};

// Production's MySQL account can see the other Dot applications' databases. A second database with a table this one does not have
// stands in for them: removal must read this database's schema only, or it would look for that table here and fail.
$elsewhere = null;
if (DB::connection()->getDriverName() === 'mysql') {
    $elsewhere = DB::connection()->getDatabaseName().'_elsewhere';
    DB::statement('create database if not exists `'.$elsewhere.'`');
    DB::statement('create table if not exists `'.$elsewhere.'`.`only_elsewhere` (id int unsigned primary key, user_id int unsigned, team_id int unsigned)');
}

$before = $counts();
$filesBefore = $files();

// ---- refuse ------------------------------------------------------------------------------------------------------------------

$check('without --force the install is refused in production', $artisan('demo:install') !== 0);
$check('and the refusal created nothing', $counts() === $before, $difference($before, $counts()));

// ---- install -----------------------------------------------------------------------------------------------------------------

$check('demo:install --force succeeds', $artisan('demo:install --force') === 0);

$installed = $counts();
$check('the install created records', DemoRecord::count() > 0 && $installed !== $before);

$listed = app(BusinessDirectory::class)->search([], null)['results']->getCollection()->pluck('name')->all();
foreach (['Ledger Works (Demonstration)', 'Fieldline IT (Demonstration)'] as $name) {
    $check($name.' is listed for a signed-out visitor', in_array($name, $listed, true), 'listed: '.implode(', ', $listed));
}

$found = app(BusinessDirectory::class)->search(['q' => 'bookkeeping'], null)['results']->getCollection()->pluck('name')->all();
$check('a directory search finds the bookkeeping company', in_array('Ledger Works (Demonstration)', $found, true), 'found: '.implode(', ', $found));

$companies = Team::whereIn('name', ['Ledger Works (Demonstration)', 'Fieldline IT (Demonstration)'])->get();
$check('each company has a logo and a cover stored as attachments', $companies->count() === 2
    && $companies->every(fn (Team $team) => $team->currentMedia('logo') !== null && $team->currentMedia('cover') !== null));
$check('the pictures were written to disk', $files() === $filesBefore + 7, 'files before '.$filesBefore.', now '.$files().' (expected 4 business pictures and 3 profile pictures more)');
$check('no demonstration moderator was created by default', User::where('email', 'like', '%@demo.infodot.test')->where('is_moderator', true)->doesntExist());
$check('nothing waits to be paid out (the points outbox gained no event)', $installed['reward_outbox'] === $before['reward_outbox']);

// ---- again -------------------------------------------------------------------------------------------------------------------

$check('a second demo:install --force succeeds', $artisan('demo:install --force') === 0);
$check('and creates nothing new', $counts() === $installed, $difference($installed, $counts()));

// ---- remove ------------------------------------------------------------------------------------------------------------------

$check('without --force the removal is refused in production', $artisan('demo:remove') !== 0 && $counts() === $installed);
$check('demo:remove --force succeeds', $artisan('demo:remove --force') === 0);

$after = $counts();
$check('every table holds what it held before the install', $after === $before, $difference($before, $after));
$check('the pictures are gone from disk', $files() === $filesBefore, 'files before '.$filesBefore.', now '.$files());

if ($elsewhere !== null) {
    DB::statement('drop database if exists `'.$elsewhere.'`');
}

echo $failures === 0 ? "\nAll demonstration-content checks held.\n" : "\n".$failures." check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
