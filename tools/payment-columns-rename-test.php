<?php

/*
 * The payment-column rename on a real MySQL. Production was created with the old column names, and the PHPUnit suite (which has the same test,
 * tests/Feature/Licensing/PaymentColumnsRenameTest.php) only runs on SQLite. This script rebuilds the three tables as the migrations of
 * 2026-10-29 first created them, puts a row in each, runs database/migrations/2026_11_13_100000_rename_payment_columns.php and checks that the
 * values, the default and the unique indexes came across, that a second run changes nothing, and that down() goes back.
 *
 * Run by the CI job "migrations on MySQL" after `php artisan migrate:fresh` (DB_* in the environment). IT DROPS AND RECREATES three tables
 * (team_licences, seminar_payout_accounts, seminar_host_debts), so it refuses to start without --throwaway-database and in production.
 * Exit code 0 only if every check held.
 *
 *   php tools/payment-columns-rename-test.php --throwaway-database
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! in_array('--throwaway-database', $argv, true) || $app->environment('production')) {
    fwrite(STDERR, "This test drops and recreates three tables. Run it only against a throwaway database, with --throwaway-database, never in production.\n");
    exit(2);
}

$failed = false;
$check = function (bool $ok, string $what) use (&$failed) {
    echo ($ok ? 'ok   - ' : 'FAIL - ').$what."\n";
    $failed = $failed || ! $ok;
};
$uniques = fn (string $table) => collect(Schema::getIndexes($table))->where('unique', true)->where('primary', false)
    ->map(fn (array $index) => $index['name'].':'.implode(',', $index['columns']))->sort()->values()->all();
$refused = function (callable $write): bool {
    try {
        $write();
    } catch (Throwable) {
        return true;
    }

    return false;
};

echo 'driver: '.DB::connection()->getDriverName()."\n";

$migration = require __DIR__.'/../database/migrations/2026_11_13_100000_rename_payment_columns.php';

// ---- a fresh database already has the new names ---------------------------------------------------------------------------
$check(Schema::hasColumns('team_licences', ['payment_status', 'payment_ref']) && Schema::hasColumn('seminar_payout_accounts', 'finance_account_id') && Schema::hasColumn('seminar_host_debts', 'finance_debt_id'), 'migrate:fresh creates the new column names');
$check($uniques('seminar_host_debts') === ['seminar_host_debts_finance_debt_id_unique:finance_debt_id'], 'and the unique index on the debt id under its new name');
$before = [Schema::getColumnListing('team_licences'), Schema::getColumnListing('seminar_payout_accounts'), Schema::getColumnListing('seminar_host_debts')];
$migration->up();
$check($before === [Schema::getColumnListing('team_licences'), Schema::getColumnListing('seminar_payout_accounts'), Schema::getColumnListing('seminar_host_debts')], 'the rename migration changes nothing there');

// ---- the three tables as they were first created ----------------------------------------------------------------------------
Schema::drop('team_licences');
Schema::drop('seminar_payout_accounts');
Schema::drop('seminar_host_debts');
Schema::create('team_licences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->unique();
    $table->unsignedSmallInteger('included_seats')->nullable();
    $table->unsignedSmallInteger('purchased_seats')->default(0);
    $table->string('billing_status', 16)->default('free');
    $table->string('billing_ref', 80)->nullable();
    $table->timestamp('grace_until')->nullable();
    $table->timestamps();
});
Schema::create('seminar_payout_accounts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->unique();
    $table->unsignedBigInteger('billing_account_id');
    $table->string('bank_code', 24);
    $table->string('account_last4', 4);
    $table->string('status', 12)->default('active');
    $table->timestamps();
});
Schema::create('seminar_host_debts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->index();
    $table->unsignedBigInteger('billing_debt_id')->unique();
    $table->string('ticket_ref', 64)->nullable();
    $table->string('currency', 3)->default('ZAR');
    $table->unsignedInteger('amount_cents');
    $table->unsignedInteger('recovered_cents')->default(0);
    $table->unsignedInteger('remaining_cents')->default(0);
    $table->string('status', 12)->default('open');
    $table->string('note', 500)->nullable();
    $table->timestamp('opened_at')->nullable();
    $table->timestamp('last_event_at')->nullable();
    $table->timestamps();
});
DB::table('team_licences')->insert(['team_id' => 7, 'purchased_seats' => 4, 'billing_status' => 'past_due', 'billing_ref' => 'team-yearly']);
DB::table('seminar_payout_accounts')->insert(['user_id' => 3, 'billing_account_id' => 77, 'bank_code' => '058', 'account_last4' => '9876']);
DB::table('seminar_host_debts')->insert(['user_id' => 3, 'billing_debt_id' => 501, 'amount_cents' => 20000]);

// ---- old schema, then the migration -----------------------------------------------------------------------------------------
$migration->up();
$migration->up();
$check(Schema::getColumnListing('team_licences') === ['id', 'team_id', 'included_seats', 'purchased_seats', 'payment_status', 'payment_ref', 'grace_until', 'created_at', 'updated_at'], 'team_licences has the new names, in place, after two runs');
$check(! Schema::hasColumn('seminar_payout_accounts', 'billing_account_id') && ! Schema::hasColumn('seminar_host_debts', 'billing_debt_id'), 'the old payout and debt columns are gone');
$licence = DB::table('team_licences')->where('team_id', 7)->first();
$check([(int) $licence->purchased_seats, $licence->payment_status, $licence->payment_ref] === [4, 'past_due', 'team-yearly'], 'the licence row kept its values');
$check((int) DB::table('seminar_payout_accounts')->where('user_id', 3)->value('finance_account_id') === 77, 'the payout account kept its id');
$check((int) DB::table('seminar_host_debts')->where('user_id', 3)->value('finance_debt_id') === 501, 'the debt kept its id');
DB::table('team_licences')->insert(['team_id' => 8]);
$check(DB::table('team_licences')->where('team_id', 8)->value('payment_status') === 'free', 'the default "free" survived the rename');
$check($uniques('team_licences') === ['team_licences_team_id_unique:team_id'], 'team_licences keeps its unique index');
$check($uniques('seminar_payout_accounts') === ['seminar_payout_accounts_user_id_unique:user_id'], 'seminar_payout_accounts keeps its unique index');
$check($uniques('seminar_host_debts') === ['seminar_host_debts_finance_debt_id_unique:finance_debt_id'], 'the debt id index is renamed with its column');
$check($refused(fn () => DB::table('seminar_host_debts')->insert(['user_id' => 4, 'finance_debt_id' => 501, 'amount_cents' => 1])), 'a second debt with the same id is refused');
$check($refused(fn () => DB::table('team_licences')->insert(['team_id' => 7])), 'a second licence for the same company is refused');

// ---- and back -----------------------------------------------------------------------------------------------------------------
$migration->down();
$migration->down();
$check(Schema::hasColumns('team_licences', ['billing_status', 'billing_ref']) && ! Schema::hasColumn('team_licences', 'payment_status') && Schema::hasColumn('seminar_payout_accounts', 'billing_account_id'), 'down() restores the old names');
$check($uniques('seminar_host_debts') === ['seminar_host_debts_billing_debt_id_unique:billing_debt_id'], 'and the old index name');
$check(DB::table('team_licences')->where('team_id', 7)->value('billing_ref') === 'team-yearly', 'with the values intact');
$migration->up();
$check(DB::table('team_licences')->where('team_id', 7)->value('payment_status') === 'past_due' && $uniques('seminar_host_debts') === ['seminar_host_debts_finance_debt_id_unique:finance_debt_id'], 'and up() carries it across again');

DB::table('team_licences')->delete();
DB::table('seminar_payout_accounts')->delete();
DB::table('seminar_host_debts')->delete();

echo $failed ? "RESULT: FAILED\n" : "RESULT: every check held\n";
exit($failed ? 1 : 0);
