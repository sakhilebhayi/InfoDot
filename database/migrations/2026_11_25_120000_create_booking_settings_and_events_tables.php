<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-63 phase 1: how one business takes appointments, and the trail of who changed it. One settings row per business; it does not exist until a
 * manager saves the screen, and a business with no row takes no bookings. Times are stored in minutes and days so no unit is guessed later.
 * Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_settings')) {
            Schema::create('booking_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
                $table->boolean('enabled')->default(false);            // new appointments may be requested at all
                $table->boolean('calendar_published')->default(true); // visitors may see availability (hidden calendar = no public slots)
                $table->string('approval_mode', 12)->default('approval'); // auto | approval
                $table->string('timezone', 64)->default('Africa/Johannesburg');
                $table->unsignedInteger('min_notice_minutes')->default(120);
                $table->unsignedSmallInteger('horizon_days')->default(60);
                $table->unsignedSmallInteger('cancel_cutoff_hours')->default(24);
                $table->text('instructions')->nullable();
                $table->text('terms')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('booking_events')) {
            Schema::create('booking_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->unsignedInteger('actor_id')->nullable();       // users.id is an unsigned INT
                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
                $table->string('type', 40);                             // settings_changed | booking_enabled | booking_disabled | ...
                $table->json('details')->nullable();                    // what changed, never a customer's personal details
                $table->timestamp('created_at')->useCurrent();

                $table->index(['team_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('booking_settings');
    }
};
