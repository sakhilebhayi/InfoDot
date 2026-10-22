<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the person lets other people see their achievements and public contributions on their profile.
 * Off until they switch it on. Additive and guarded so a re-run does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'achievements_public')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('achievements_public')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'achievements_public')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('achievements_public');
        });
    }
};
