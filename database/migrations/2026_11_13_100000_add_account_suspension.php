<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account suspension. InfoDot signs people in to every Dot platform, so there has to be a way to switch one account
 * off everywhere: a mark on the user (when, and why) and an append-only trail of who suspended or restored whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->index();
            $table->string('suspended_reason', 255)->nullable();
        });

        Schema::create('account_suspension_events', function (Blueprint $table) {
            $table->id();
            // users.id is a legacy unsigned INT (increments), so the referencing columns are unsigned INT too.
            $table->unsignedInteger('user_id');
            $table->string('action', 20); // suspended | restored
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('actor_id')->nullable(); // null: done from the command line
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_suspension_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['suspended_at']);
            $table->dropColumn(['suspended_at', 'suspended_reason']);
        });
    }
};
