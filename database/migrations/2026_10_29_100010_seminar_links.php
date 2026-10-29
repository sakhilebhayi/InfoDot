<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seminar links (R-13): a short InfoDot link for every seminar that always opens (so a host never has to hand out a typed address), what the
 * link check said about the host's meeting link, and attendees' reports that a meeting link did not open. Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            if (! Schema::hasColumn('seminars', 'public_code')) {
                $table->string('public_code', 12)->nullable()->unique();
                $table->string('join_url_status', 8)->nullable();      // ok | warn
                $table->timestamp('join_url_checked_at')->nullable();
            }
        });

        foreach (DB::table('seminars')->whereNull('public_code')->pluck('id') as $id) {
            DB::table('seminars')->where('id', $id)->update(['public_code' => Str::lower(Str::random(8))]);
        }

        if (! Schema::hasTable('seminar_link_reports')) {
            Schema::create('seminar_link_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seminar_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['seminar_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_link_reports');
    }
};
