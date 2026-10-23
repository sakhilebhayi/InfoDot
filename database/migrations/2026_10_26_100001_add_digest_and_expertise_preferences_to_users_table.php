<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two opt-in preferences, both OFF until the person switches them on (on the /points page):
 *  - points_digest: 'off' (default), 'week' or 'month'. Which summary email of their Dot Points they want, if any.
 *  - share_expertise: whether Dot.Memory may remember which topics they help with (topic and count only).
 * Additive and guarded so a re-run does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'points_digest')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('points_digest', 8)->default('off');
            });
        }

        if (! Schema::hasColumn('users', 'share_expertise')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('share_expertise')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['points_digest', 'share_expertise'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
