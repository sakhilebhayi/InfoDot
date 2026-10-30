<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-14: entries the official InfoDot account publishes from the curated files in database/knowledge/ carry the file's
 * key, so running `knowledge:seed` twice updates each entry in place instead of publishing it twice. Community-written
 * solutions keep NULL here; MySQL and SQLite both allow any number of NULLs under a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('solutions', 'seed_key')) {
            return;
        }

        Schema::table('solutions', function (Blueprint $table) {
            $table->string('seed_key', 100)->nullable()->unique('solutions_seed_key_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('solutions', 'seed_key')) {
            Schema::table('solutions', function (Blueprint $table) {
                $table->dropUnique('solutions_seed_key_unique');
                $table->dropColumn('seed_key');
            });
        }
    }
};
