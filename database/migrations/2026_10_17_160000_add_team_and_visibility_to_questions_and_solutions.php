<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questions and solutions belong to a company (team) and are either private to it
 * or published to the public community. Until now they were visible to everyone.
 *
 * Existing rows keep their current reach: they are marked public (so nothing
 * disappears) and attached to their author's current team, falling back to a team
 * the author owns, so an owner can later make them private.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['questions', 'solutions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'team_id')) {
                    $t->unsignedBigInteger('team_id')->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'visibility')) {
                    $t->string('visibility', 16)->default('company');
                }
            });

            DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
                $teamId = DB::table('users')->where('id', $row->user_id)->value('current_team_id')
                    ?? DB::table('teams')->where('user_id', $row->user_id)->orderBy('id')->value('id');

                DB::table($table)->where('id', $row->id)->update([
                    'team_id' => $row->team_id ?? $teamId,
                    'visibility' => 'public',
                ]);
            });
        }
    }

    public function down(): void
    {
        foreach (['questions', 'solutions'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    foreach (['team_id', 'visibility'] as $col) {
                        if (Schema::hasColumn($table, $col)) {
                            $t->dropColumn($col);
                        }
                    }
                });
            }
        }
    }
};
