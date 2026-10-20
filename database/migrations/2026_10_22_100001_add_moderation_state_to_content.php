<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A moderator can hide a question, an answer or a solution. Hiding is a reversible flag, never a delete: the row
 * stays, the author and moderators still see it with a notice, everyone else does not. `hidden_by` and
 * `verified_by` hold a users.id (INT UNSIGNED) and are plain indexed columns, like the team_id added to these tables
 * before: a foreign key added to an existing table behaves differently on SQLite and MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['questions', 'solutions', 'answers'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'hidden_at')) {
                    $t->timestamp('hidden_at')->nullable();
                }
                if (! Schema::hasColumn($table, 'hidden_by')) {
                    $t->unsignedInteger('hidden_by')->nullable();
                }
                if (! Schema::hasColumn($table, 'hidden_reason')) {
                    $t->text('hidden_reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('solutions')) {
            Schema::table('solutions', function (Blueprint $t) {
                if (! Schema::hasColumn('solutions', 'verified_at')) {
                    $t->timestamp('verified_at')->nullable();
                }
                if (! Schema::hasColumn('solutions', 'verified_by')) {
                    $t->unsignedInteger('verified_by')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['questions', 'solutions', 'answers'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['hidden_at', 'hidden_by', 'hidden_reason'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('solutions')) {
            Schema::table('solutions', function (Blueprint $t) {
                foreach (['verified_at', 'verified_by'] as $column) {
                    if (Schema::hasColumn('solutions', $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }
    }
};
