<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers that the Dot Points event for hosting a seminar was queued, and the event id it carries, so it is queued once and a
 * cancellation can reverse exactly that award (R-13). Additive and guarded; nothing existing is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['host_points_event_id' => fn (Blueprint $t) => $t->string('host_points_event_id', 64)->nullable(),
            'host_points_queued_at' => fn (Blueprint $t) => $t->timestamp('host_points_queued_at')->nullable(),
            'host_points_reversed_at' => fn (Blueprint $t) => $t->timestamp('host_points_reversed_at')->nullable()] as $column => $define) {
            if (! Schema::hasColumn('seminars', $column)) {
                Schema::table('seminars', fn (Blueprint $table) => $define($table));
            }
        }
    }

    public function down(): void
    {
        foreach (['host_points_event_id', 'host_points_queued_at', 'host_points_reversed_at'] as $column) {
            if (Schema::hasColumn('seminars', $column)) {
                Schema::table('seminars', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
