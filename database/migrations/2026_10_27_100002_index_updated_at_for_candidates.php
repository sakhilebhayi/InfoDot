<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The candidates endpoint asks "what changed since this moment?" of questions, answers and solutions. A plain index on
 * updated_at keeps that a range scan however large the tables grow. Additive and guarded: nothing is rewritten.
 */
return new class extends Migration
{
    private const TABLES = ['questions' => 'questions_updated_at_index', 'answers' => 'answers_updated_at_index', 'solutions' => 'solutions_updated_at_index'];

    public function up(): void
    {
        foreach (self::TABLES as $table => $name) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'updated_at') && ! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index('updated_at', $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $name) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
