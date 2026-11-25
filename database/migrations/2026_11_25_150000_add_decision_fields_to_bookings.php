<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-63 phase 4: what the business tells the customer when it decides (a short reason shown on the booking), and a customer's request to move an
 * appointment. A reschedule request leaves the booking exactly as it is, holding its time, until the business moves it or declines.
 * Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'status_note')) {
                $table->string('status_note', 300)->nullable();               // shown to the customer: why it was rejected or cancelled
            }
            if (! Schema::hasColumn('bookings', 'reschedule_requested_at')) {
                $table->timestamp('reschedule_requested_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'reschedule_note')) {
                $table->string('reschedule_note', 300)->nullable();           // the customer's own words about when would suit
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            foreach (['status_note', 'reschedule_requested_at', 'reschedule_note'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
