<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for queries the code makes on every request or on a short schedule (enterprise audit 2026-10-30, findings C-07 and C-24).
 * Each is added only when the table and columns exist and no index on exactly those columns does. Small tables: no lock concern.
 */
return new class extends Migration
{
    private const INDEXES = [
        // Every question/solution list, home and search query ends in (visibility = public OR user_id = me OR team_id = my team) ordered by date.
        'questions' => [['visibility'], ['user_id'], ['created_at']],
        'solutions' => [['visibility'], ['user_id'], ['team_id'], ['created_at']],
        // Jetstream looks up a person's teams by user_id on most requests; the existing unique key starts with team_id.
        'team_user' => [['user_id']],
        // seminars:sweep-payments runs every five minutes and filters on these.
        'seminar_registrations' => [['payment_status', 'hold_expires_at']],
        // notices:prune deletes by age.
        'user_notices' => [['created_at']],
        // The retention job and the room cleanup look signals up by sender.
        'p2p_signals' => [['from_peer']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($sets as $columns) {
                if (! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $this->nameFor($table, $columns)));
            }
        }
    }

    /** Only the indexes this migration made are dropped by down(): an index that was already there is not ours to remove. */
    public function down(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($sets as $columns) {
                $name = $this->nameFor($table, $columns);

                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }

    /** @param  list<string>  $columns */
    private function nameFor(string $table, array $columns): string
    {
        return substr('hot_'.$table.'_'.implode('_', $columns), 0, 64);
    }
};
