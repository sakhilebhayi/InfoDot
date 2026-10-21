<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the person wants an email before some of their Dot Points lapse. On by default (the email is a courtesy
 * about something they own); the switch is on the /points page. Additive and guarded so a re-run does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'points_expiry_emails')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('points_expiry_emails')->default(true);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'points_expiry_emails')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('points_expiry_emails');
        });
    }
};
