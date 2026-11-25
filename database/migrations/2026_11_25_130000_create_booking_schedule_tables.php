<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-63 phase 2: what a business offers and when. Appointment types, the people who offer them (providers), recurring working hours (business-wide
 * and per provider), closures (holidays and blocked time) and the bookings the availability engine has to respect. Every row belongs to one business
 * (`team_id`). Instants (`starts_at`, `ends_at`, closures) are stored in UTC; recurring hours are local times in the business time zone. users.id is a
 * legacy unsigned INT. Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointment_types')) {
            Schema::create('appointment_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('duration_minutes');             // 5 to 480
                $table->unsignedSmallInteger('slot_interval_minutes')->default(30); // start times are offered this far apart
                $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
                $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
                $table->unsignedInteger('fee_cents')->nullable();             // information only; nothing is charged here
                $table->string('currency', 3)->default('ZAR');
                $table->string('meeting_format', 12)->default('in_person');   // in_person | online | phone
                $table->string('location_note', 300)->nullable();             // an address, or how to join
                $table->text('preparation')->nullable();
                $table->boolean('requires_approval')->nullable();             // null follows the business setting
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['team_id', 'active', 'sort']);
            });
        }

        if (! Schema::hasTable('booking_providers')) {
            Schema::create('booking_providers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->string('public_name', 80);                            // what customers see; never the person's account name by default
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['team_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('appointment_type_provider')) {
            Schema::create('appointment_type_provider', function (Blueprint $table) {
                $table->foreignId('appointment_type_id')->constrained('appointment_types')->cascadeOnDelete();
                $table->foreignId('booking_provider_id')->constrained('booking_providers')->cascadeOnDelete();
                $table->primary(['appointment_type_id', 'booking_provider_id']);
            });
        }

        if (! Schema::hasTable('working_hours')) {
            Schema::create('working_hours', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('booking_provider_id')->nullable()->constrained('booking_providers')->cascadeOnDelete(); // null = the business's own hours
                $table->unsignedTinyInteger('weekday');                       // 1 Monday to 7 Sunday
                $table->time('starts_at');                                    // local time in the business time zone
                $table->time('ends_at');                                      // same day; a break is the gap between two rows
                $table->timestamps();

                $table->index(['team_id', 'booking_provider_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('booking_closures')) {
            Schema::create('booking_closures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('booking_provider_id')->nullable()->constrained('booking_providers')->cascadeOnDelete(); // null = the whole business
                $table->string('kind', 10)->default('block');                 // holiday | block
                $table->timestamp('starts_at');                               // UTC
                $table->timestamp('ends_at');                                 // UTC, exclusive
                $table->string('reason', 160)->nullable();                    // for the business only
                $table->timestamps();

                $table->index(['team_id', 'starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 24)->nullable()->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->foreignId('appointment_type_id')->constrained('appointment_types')->cascadeOnDelete(); // the app never deletes a type that has bookings: it switches it off
                $table->foreignId('booking_provider_id')->nullable()->constrained('booking_providers')->nullOnDelete();
                $table->unsignedInteger('customer_user_id')->nullable();
                $table->foreign('customer_user_id')->references('id')->on('users')->nullOnDelete();
                $table->string('customer_name', 120);
                $table->string('customer_email', 190);
                $table->string('customer_phone', 40)->nullable();
                $table->string('customer_company', 160)->nullable();
                $table->text('reason')->nullable();
                $table->json('answers')->nullable();
                $table->timestamp('starts_at');                               // UTC
                $table->timestamp('ends_at');                                 // UTC, exclusive
                $table->timestamp('blocks_from');                             // starts_at less the type's buffer before
                $table->timestamp('blocks_until');                            // ends_at plus the buffer after
                $table->string('status', 24)->default('pending');
                $table->text('internal_notes')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['team_id', 'booking_provider_id', 'blocks_from']);
                $table->index(['team_id', 'status', 'starts_at']);
                $table->index('customer_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_closures');
        Schema::dropIfExists('working_hours');
        Schema::dropIfExists('appointment_type_provider');
        Schema::dropIfExists('booking_providers');
        Schema::dropIfExists('appointment_types');
    }
};
