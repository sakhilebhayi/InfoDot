<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How a seminar is delivered: the host's own link (default) or an InfoDot room. A room has no link, so join_url may be empty. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seminars', 'delivery')) {
            Schema::table('seminars', function (Blueprint $table) {
                $table->string('delivery', 8)->default('link');
            });
        }

        Schema::table('seminars', function (Blueprint $table) {
            $table->string('join_url', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversed: rooms would have no link to fall back to.
    }
};
