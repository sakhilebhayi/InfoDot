<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily counts of interaction friction (R-12). One row per day, route template, event type, status, error category and speed
 * bucket, with a count. It holds no person, no session and no address by design; see config/ux.php. Guarded so a re-run does nothing twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ux_daily_counts')) {
            return;
        }

        Schema::create('ux_daily_counts', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('route', 120);
            $table->string('type', 32);
            $table->string('status', 16);
            $table->string('error', 16)->default('');
            $table->string('bucket', 12)->default('');
            $table->unsignedInteger('hits')->default(0);

            $table->unique(['day', 'route', 'type', 'status', 'error', 'bucket'], 'ux_daily_counts_unique');
            $table->index(['day', 'route']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ux_daily_counts');
    }
};
