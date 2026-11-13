<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments moved to Dot.Finance on 2026-11-13. Four columns (and one unique index) still carried the name of the payment
 * system they were first built against; they get a name that says what they hold. Values, defaults and the unique index
 * are kept. Each step is guarded, so this does nothing on a database created after the earlier migrations were updated
 * (they now create the new names directly) and nothing when run a second time.
 */
return new class extends Migration
{
    /** table => [old column => new column] */
    private const COLUMNS = [
        'team_licences' => ['billing_status' => 'payment_status', 'billing_ref' => 'payment_ref'],
        'seminar_payout_accounts' => ['billing_account_id' => 'finance_account_id'],
        'seminar_host_debts' => ['billing_debt_id' => 'finance_debt_id'],
    ];

    /**
     * A renamed column keeps its unique index under the old name: [table, old index, new index, column once renamed].
     * The index is dropped and created again under the new name, not renamed in place: MariaDB before 10.5.2 has no
     * RENAME INDEX, and a migration that fails on the host fails after maintenance mode has started.
     */
    private const INDEXES = [
        ['seminar_host_debts', 'seminar_host_debts_billing_debt_id_unique', 'seminar_host_debts_finance_debt_id_unique', 'finance_debt_id'],
    ];

    public function up(): void
    {
        $this->rename(self::COLUMNS, self::INDEXES);
    }

    public function down(): void
    {
        $this->rename(
            array_map('array_flip', self::COLUMNS),
            array_map(fn (array $i) => [$i[0], $i[2], $i[1], array_search($i[3], self::COLUMNS[$i[0]], true)], self::INDEXES),
        );
    }

    /**
     * @param  array<string, array<string, string>>  $columns
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $indexes
     */
    private function rename(array $columns, array $indexes): void
    {
        foreach ($columns as $table => $pairs) {
            foreach ($pairs as $from => $to) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
                }
            }
        }

        foreach ($indexes as [$table, $from, $to, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && Schema::hasIndex($table, $from) && ! Schema::hasIndex($table, $to)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($from));
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($column, $to));
            }
        }
    }
};
