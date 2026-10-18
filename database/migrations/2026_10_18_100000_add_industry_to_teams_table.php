<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the company does (retail, mining, ...). Optional: it only decides which examples
 * and suggestions InfoDot shows. See config/industries.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teams') && ! Schema::hasColumn('teams', 'industry')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('industry', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teams', 'industry')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('industry');
            });
        }
    }
};
