<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R-63 phase 7: an appointment's history was found by reading a JSON field of every event of the business. A real, indexed column makes it a direct
 * lookup. Existing events are filled from the id they already carry in `details`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Two separate steps: MySQL schema changes are not transactional, so a run that stopped between them can be repeated safely.
        if (! Schema::hasColumn('booking_events', 'booking_id')) {
            Schema::table('booking_events', function (Blueprint $table) {
                $table->unsignedBigInteger('booking_id')->nullable()->after('team_id');
            });
        }

        if (! Schema::hasIndex('booking_events', ['team_id', 'booking_id'])) {
            Schema::table('booking_events', function (Blueprint $table) {
                $table->index(['team_id', 'booking_id']);
            });
        }

        DB::table('booking_events')->whereNull('booking_id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $id = json_decode((string) $row->details, true)['booking_id'] ?? null;

                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    DB::table('booking_events')->where('id', $row->id)->update(['booking_id' => (int) $id]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_events', 'booking_id')) {
            Schema::table('booking_events', function (Blueprint $table) {
                $table->dropIndex(['team_id', 'booking_id']);
                $table->dropColumn('booking_id');
            });
        }
    }
};
