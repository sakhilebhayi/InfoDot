<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A company's identity across the Dot ecosystem. Other platforms have their own
 * database and their own team ids, so they link a local team to the InfoDot team by
 * this uuid. It never changes, even if the team is renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        if (! Schema::hasColumn('teams', 'uuid')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
        }

        DB::table('teams')->whereNull('uuid')->orderBy('id')->each(function ($team) {
            DB::table('teams')->where('id', $team->id)->update(['uuid' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'uuid')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
