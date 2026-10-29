<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The person's own switch for the knowledge score (R-13, ADR-0026 D3). Off by default: nobody is opted out until they choose. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'reputation_opt_out')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('reputation_opt_out')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'reputation_opt_out')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('reputation_opt_out');
            });
        }
    }
};
