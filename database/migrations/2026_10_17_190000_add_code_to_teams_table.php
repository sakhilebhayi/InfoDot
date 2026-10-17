<?php

use App\Support\OwnerCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every team gets its owner code (four letters, unique), derived from its name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        if (! Schema::hasColumn('teams', 'code')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('code', 4)->nullable()->unique();
            });
        }

        DB::table('teams')->whereNull('code')->orderBy('id')->each(function ($team) {
            $code = OwnerCode::for($team->name, fn (string $c) => DB::table('teams')->where('code', $c)->exists());
            DB::table('teams')->where('id', $team->id)->update(['code' => $code]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'code')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
