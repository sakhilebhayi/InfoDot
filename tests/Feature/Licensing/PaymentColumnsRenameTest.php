<?php

namespace Tests\Feature\Licensing;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The live database was created with the old column names; a fresh one gets the new names from the earlier migrations.
 * The rename migration has to carry the first across without losing a value or the unique index, and do nothing on the second.
 */
class PaymentColumnsRenameTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_13_100000_rename_payment_columns.php';

    /** The three tables exactly as the migrations of 2026-10-29 first created them. */
    private function buildTheOldSchema(): void
    {
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
    }

    /** @return list<string> */
    private function uniqueIndexes(string $table): array
    {
        return collect(Schema::getIndexes($table))->where('unique', true)->where('primary', false)
            ->map(fn (array $index) => $index['name'].':'.implode(',', $index['columns']))->sort()->values()->all();
    }

    public function test_a_fresh_database_already_has_the_new_names_and_the_migration_changes_nothing(): void
    {
        $before = [Schema::getColumnListing('team_licences'), Schema::getColumnListing('seminar_payout_accounts'), Schema::getColumnListing('seminar_host_debts')];

        $this->assertTrue(Schema::hasColumns('team_licences', ['payment_status', 'payment_ref']));
        $this->assertTrue(Schema::hasColumn('seminar_payout_accounts', 'finance_account_id'));
        $this->assertTrue(Schema::hasColumn('seminar_host_debts', 'finance_debt_id'));
        $this->assertContains('seminar_host_debts_finance_debt_id_unique:finance_debt_id', $this->uniqueIndexes('seminar_host_debts'));

        (require database_path(self::MIGRATION))->up();

        $this->assertSame($before, [Schema::getColumnListing('team_licences'), Schema::getColumnListing('seminar_payout_accounts'), Schema::getColumnListing('seminar_host_debts')]);
    }

    public function test_an_old_database_is_carried_across_with_its_values_defaults_and_unique_indexes(): void
    {
        $this->buildTheOldSchema();
        DB::table('team_licences')->insert(['team_id' => 7, 'purchased_seats' => 4, 'billing_status' => 'past_due', 'billing_ref' => 'team-yearly']);
        DB::table('seminar_payout_accounts')->insert(['user_id' => 3, 'billing_account_id' => 77, 'bank_code' => '058', 'account_last4' => '9876']);
        DB::table('seminar_host_debts')->insert(['user_id' => 3, 'billing_debt_id' => 501, 'amount_cents' => 20000]);

        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $migration->up(); // safe twice

        $this->assertSame(['id', 'team_id', 'included_seats', 'purchased_seats', 'payment_status', 'payment_ref', 'grace_until', 'created_at', 'updated_at'], Schema::getColumnListing('team_licences'));
        $this->assertFalse(Schema::hasColumn('seminar_payout_accounts', 'billing_account_id'));
        $this->assertFalse(Schema::hasColumn('seminar_host_debts', 'billing_debt_id'));

        $licence = DB::table('team_licences')->where('team_id', 7)->first();
        $this->assertSame([4, 'past_due', 'team-yearly'], [(int) $licence->purchased_seats, $licence->payment_status, $licence->payment_ref]);
        $this->assertSame(77, (int) DB::table('seminar_payout_accounts')->where('user_id', 3)->value('finance_account_id'));
        $this->assertSame(501, (int) DB::table('seminar_host_debts')->where('user_id', 3)->value('finance_debt_id'));

        // The default survives the rename: a licence created without a status is still "free".
        DB::table('team_licences')->insert(['team_id' => 8]);
        $this->assertSame('free', DB::table('team_licences')->where('team_id', 8)->value('payment_status'));

        // Every unique index is still there, and the renamed column's index has the name a fresh database gives it.
        $this->assertSame(['team_licences_team_id_unique:team_id'], $this->uniqueIndexes('team_licences'));
        $this->assertSame(['seminar_payout_accounts_user_id_unique:user_id'], $this->uniqueIndexes('seminar_payout_accounts'));
        $this->assertSame(['seminar_host_debts_finance_debt_id_unique:finance_debt_id'], $this->uniqueIndexes('seminar_host_debts'));

        try {
            DB::table('seminar_host_debts')->insert(['user_id' => 4, 'billing_debt_id' => 501, 'amount_cents' => 1]);
            $this->fail('the old column name must be gone');
        } catch (QueryException) {
            // expected
        }

        $this->expectException(QueryException::class);
        DB::table('seminar_host_debts')->insert(['user_id' => 4, 'finance_debt_id' => 501, 'amount_cents' => 1]);
    }

    public function test_rolling_back_restores_the_old_names_and_values_and_migrating_again_works(): void
    {
        $this->buildTheOldSchema();
        DB::table('team_licences')->insert(['team_id' => 7, 'billing_status' => 'active', 'billing_ref' => 'team-monthly']);
        DB::table('seminar_host_debts')->insert(['user_id' => 3, 'billing_debt_id' => 501, 'amount_cents' => 20000]);
        $migration = require database_path(self::MIGRATION);

        $migration->up();
        $migration->down();
        $migration->down(); // safe twice

        $this->assertTrue(Schema::hasColumns('team_licences', ['billing_status', 'billing_ref']));
        $this->assertFalse(Schema::hasColumn('team_licences', 'payment_status'));
        $this->assertTrue(Schema::hasColumn('seminar_payout_accounts', 'billing_account_id'));
        $this->assertSame(['seminar_host_debts_billing_debt_id_unique:billing_debt_id'], $this->uniqueIndexes('seminar_host_debts'));
        $this->assertSame('team-monthly', DB::table('team_licences')->where('team_id', 7)->value('billing_ref'));

        $migration->up();

        $this->assertSame('active', DB::table('team_licences')->where('team_id', 7)->value('payment_status'));
        $this->assertSame(501, (int) DB::table('seminar_host_debts')->value('finance_debt_id'));
        $this->assertSame(['seminar_host_debts_finance_debt_id_unique:finance_debt_id'], $this->uniqueIndexes('seminar_host_debts'));
    }
}
