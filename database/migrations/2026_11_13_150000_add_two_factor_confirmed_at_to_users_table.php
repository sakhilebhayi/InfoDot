<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-factor confirmation (R-49, finding L-10): two-factor is active only once the person has entered one code from their
 * authenticator, which this column records. Anyone who already has two-factor switched on is marked confirmed now, so they
 * keep it: without that, the first visit to account settings would switch it off as an unfinished setup.
 * Guarded (the users table is shared with other Dot platforms) and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'two_factor_confirmed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('two_factor_confirmed_at')->nullable();
            });
        }

        DB::table('users')->whereNotNull('two_factor_secret')->whereNull('two_factor_confirmed_at')->update(['two_factor_confirmed_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'two_factor_confirmed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('two_factor_confirmed_at');
            });
        }
    }
};
