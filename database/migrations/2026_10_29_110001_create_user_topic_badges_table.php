<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specialist badges (R-13): one row per person and topic while their qualifying work reaches a level, removed when it no longer
 * does. Computed from the work (see config/achievements.php `badges`), never typed in. Additive and guarded so a re-run does nothing.
 * users.id is INT UNSIGNED and topics.id is BIGINT UNSIGNED, so the keys match them or MySQL refuses the foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_topic_badges')) {
            Schema::create('user_topic_badges', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('topic_id');
                $table->string('level', 32);
                $table->unsignedSmallInteger('work_count');
                $table->unsignedSmallInteger('rules_version');
                $table->timestamp('updated_at')->nullable();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
                $table->unique(['user_id', 'topic_id']);
                $table->index('topic_id');
            });
        }

        if (! Schema::hasColumn('users', 'badges_visible')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('badges_visible')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_topic_badges');

        if (Schema::hasColumn('users', 'badges_visible')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('badges_visible');
            });
        }
    }
};
