<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-64 phase 8: what a person chose for each category of notification (a pop-up or not, and how email reaches them), and when their last summary email
 * went. A category with no row uses its default (App\Services\Notifications\NotificationPreferences::CATEGORIES); security has no row at all because it
 * cannot be changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->string('category', 24);
                $table->boolean('popup')->default(true);
                $table->string('email', 12)->default('digest'); // instant | digest | off
                $table->timestamps();

                $table->unique(['user_id', 'category']);
            });
        }

        if (! Schema::hasColumn('users', 'notices_digest_sent_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('notices_digest_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');

        if (Schema::hasColumn('users', 'notices_digest_sent_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notices_digest_sent_at');
            });
        }
    }
};
