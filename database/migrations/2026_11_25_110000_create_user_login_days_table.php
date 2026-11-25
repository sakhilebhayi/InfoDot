<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-62 phase 6: one row per person per calendar day on which they signed in. It is what "came back" is counted from (retention windows in the
 * Growth Centre); it holds no address, device or session detail. Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_login_days')) {
            return;
        }

        Schema::create('user_login_days', function (Blueprint $table) {
            $table->unsignedInteger('user_id');   // users.id is an unsigned INT
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('day');
            $table->primary(['user_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_days');
    }
};
