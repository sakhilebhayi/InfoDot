<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration 100013 dropped the one-report-per-person key so a person could also report someone in the room; the code then relied on
 * firstOrCreate, which two simultaneous submits can both pass. A non-null `about_key` (the lower-cased name, or empty for a report about
 * the seminar itself) restores a key the database enforces. Duplicates that already exist are removed first (the oldest is kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seminar_reports') || Schema::hasColumn('seminar_reports', 'about_key')) {
            return;
        }

        Schema::table('seminar_reports', function (Blueprint $table) {
            $table->string('about_key', 60)->default('');
        });

        DB::table('seminar_reports')->whereNotNull('about_name')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('seminar_reports')->where('id', $row->id)->update(['about_key' => mb_substr(mb_strtolower(trim((string) $row->about_name)), 0, 60)]);
            }
        });

        $dupes = DB::table('seminar_reports')->select('seminar_id', 'reporter_id', 'about_key', DB::raw('min(id) as keep'))->groupBy('seminar_id', 'reporter_id', 'about_key')->havingRaw('count(*) > 1')->get();

        foreach ($dupes as $d) {
            DB::table('seminar_reports')->where('seminar_id', $d->seminar_id)->where('reporter_id', $d->reporter_id)->where('about_key', $d->about_key)->where('id', '!=', $d->keep)->delete();
        }

        Schema::table('seminar_reports', function (Blueprint $table) {
            $table->unique(['seminar_id', 'reporter_id', 'about_key'], 'seminar_reports_one_each');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('seminar_reports', 'about_key')) {
            Schema::table('seminar_reports', function (Blueprint $table) {
                $table->dropUnique('seminar_reports_one_each');
                $table->dropColumn('about_key');
            });
        }
    }
};
