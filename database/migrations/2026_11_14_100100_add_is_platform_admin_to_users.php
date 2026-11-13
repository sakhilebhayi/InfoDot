<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-50: the platform administrator. A separate mark from `is_moderator`: moderators look after content and get no
 * commercial powers; a platform administrator manages the catalogue, prices and complimentary access. It is only ever
 * set from the host (`platform:admin grant`), never from a screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_platform_admin')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_platform_admin');
            });
        }
    }
};
