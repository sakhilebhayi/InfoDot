<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reporting and removing seminars, and keeping out someone the host removed from a room (R-13 privacy and safety gaps). Additive and guarded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            if (! Schema::hasColumn('seminars', 'removed_at')) {
                $table->timestamp('removed_at')->nullable();           // a moderator removed it
                $table->string('removed_reason', 500)->nullable();
            }
        });

        if (! Schema::hasTable('seminar_reports')) {
            Schema::create('seminar_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seminar_id')->index();
                $table->unsignedBigInteger('reporter_id');
                $table->string('reason', 16);                          // misleading | harmful | spam | other
                $table->string('detail', 1000)->nullable();
                $table->string('status', 12)->default('open');         // open | actioned | dismissed
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->string('resolution_note', 500)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->unique(['seminar_id', 'reporter_id']);
            });
        }

        if (! Schema::hasTable('seminar_room_bans')) {
            Schema::create('seminar_room_bans', function (Blueprint $table) {
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
        Schema::dropIfExists('seminar_room_bans');
        Schema::dropIfExists('seminar_reports');
    }
};
