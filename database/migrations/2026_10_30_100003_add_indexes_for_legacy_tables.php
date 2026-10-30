<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the legacy tables whose foreign-key calls never created any (`->constrained()` on a plain integer, and the typo `contrained`): enterprise audit 2026-10-30, finding C-24.
 * Each is added only when the table and columns exist and no index on exactly those columns does. Small tables: no lock concern.
 */
return new class extends Migration
{
    private const INDEXES = [
        // Looked up on every user card and thread; no foreign key was ever created, so no index came with it.
        'associates' => [['user_id'], ['associate_id']],
        'comments' => [['user_id'], ['parent_id']],
        // The team scope filters on team_id.
        'objects' => [['team_id'], ['parent_id']],
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
        return substr('legacy_'.$table.'_'.implode('_', $columns), 0, 64);
    }
};
