<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-66 phase 3: "Request an appointment" for a business with no published calendar. The request is an
 * enquiry in the `appointment` category; these columns hold what was asked for and what the business decided.
 * All nullable: every other enquiry leaves them empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->date('requested_on')->nullable()->after('customer_team_id');
            // "any", "morning", "afternoon", or a set time as HH:MM.
            $table->string('requested_time', 16)->nullable()->after('requested_on');
            $table->unsignedSmallInteger('requested_minutes')->nullable()->after('requested_time');
            // requested, accepted or declined; null for every enquiry that is not an appointment request.
            $table->string('appointment_state', 16)->nullable()->after('requested_minutes');
            $table->dateTime('appointment_at')->nullable()->after('appointment_state');
            $table->unsignedSmallInteger('appointment_minutes')->nullable()->after('appointment_at');
            $table->dateTime('appointment_decided_at')->nullable()->after('appointment_minutes');
            $table->index(['team_id', 'appointment_state']);
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'appointment_state']);
            $table->dropColumn(['requested_on', 'requested_time', 'requested_minutes', 'appointment_state', 'appointment_at', 'appointment_minutes', 'appointment_decided_at']);
        });
    }
};
