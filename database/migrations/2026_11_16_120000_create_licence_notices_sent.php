<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-53 phase 4. One row per reminder email already sent, so the daily reminder job can be run twice (or every hour) and never send the same
 * reminder twice. Additive and guarded; nothing is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licence_notices_sent')) {
            return;
        }

        Schema::create('licence_notices_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('ref', 120);
            $table->timestamp('sent_at');
            $table->unique(['team_id', 'kind', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_notices_sent');
    }
};
