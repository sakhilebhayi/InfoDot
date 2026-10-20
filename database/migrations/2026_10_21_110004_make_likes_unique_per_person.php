<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One like per person per item, enforced by the database (a toggle restores the same row instead of adding one). */
return new class extends Migration
{
    public function up(): void
    {
        // Keep the oldest row of any duplicate group (soft-deleted rows included), remove the rest.
        $keep = DB::table('likes')->selectRaw('min(id) as id')->groupBy('user_id', 'likable_type', 'likable_id')->pluck('id');
        DB::table('likes')->whereNotIn('id', $keep)->delete();

        Schema::table('likes', function (Blueprint $table) {
            $table->unique(['user_id', 'likable_type', 'likable_id'], 'one_like_per_person_per_item');
        });
    }

    public function down(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->dropUnique('one_like_per_person_per_item');
        });
    }
};
