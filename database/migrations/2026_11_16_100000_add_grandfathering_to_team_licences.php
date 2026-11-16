<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-53 phase 1. A company that existed before the one-free-licence rule keeps its current headcount free until `grandfathered_until`
 * (owner decision D7). `grandfather_snapshot` holds what the company's row looked like before `licence:grandfather --apply`, so
 * `--rollback` can put it back exactly. Additive and guarded: a re-run does nothing, and nothing is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_licences', function (Blueprint $table) {
            if (! Schema::hasColumn('team_licences', 'grandfathered_until')) {
                $table->timestamp('grandfathered_until')->nullable();
            }
            if (! Schema::hasColumn('team_licences', 'grandfather_snapshot')) {
                $table->json('grandfather_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_licences', function (Blueprint $table) {
            foreach (['grandfathered_until', 'grandfather_snapshot'] as $column) {
                if (Schema::hasColumn('team_licences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
