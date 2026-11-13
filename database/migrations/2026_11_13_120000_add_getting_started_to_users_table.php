<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Getting started" block on Home (R-49). Its steps are read from what a person has really done; two facts had
 * nowhere to live: that the person put the block away for good, and that they have opened a platform at least once
 * (the sign-in token for a platform is deleted the moment it is used, so nothing else remembers it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'getting_started_dismissed_at')) {
                $table->timestamp('getting_started_dismissed_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'platform_opened_at')) {
                $table->timestamp('platform_opened_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['getting_started_dismissed_at', 'platform_opened_at'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
