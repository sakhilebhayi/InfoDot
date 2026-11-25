<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R-63 phase 1: appointment booking is a feature of InfoDot in the central feature-control system (R-61). A feature a platform does not list is
 * available, so it is listed here, and as `development`: nothing is open to businesses until an administrator moves it to `available` in the
 * platform catalogue. Free, with no price. Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_features')->insertOrIgnore([
            'platform_key' => 'infodot',
            'key' => 'bookings',
            'name' => 'Appointment booking',
            'description' => 'Businesses publish appointment availability on their page and take bookings from other people and businesses.',
            'kind' => 'free',
            'state' => 'development',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('platform_features')->where('platform_key', 'infodot')->where('key', 'bookings')->delete();
    }
};
