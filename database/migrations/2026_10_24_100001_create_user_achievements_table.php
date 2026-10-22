<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contribution milestones a person has reached. A milestone is never deleted: when the work behind it stops
 * qualifying (hidden, acceptance or verification removed) `revoked_at` is set, and when it qualifies again it is
 * cleared, so `earned_at` always keeps the first time. Guarded so a re-run, or a half-finished earlier run, does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_achievements')) {
            return;
        }

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            // users.id is INT UNSIGNED (`increments`), so this key must be too: a BIGINT key column is refused by MySQL.
            $table->unsignedInteger('user_id');
            $table->string('key', 64);
            $table->timestamp('earned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedSmallInteger('rule_version');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
