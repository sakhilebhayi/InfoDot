<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removal refund choice, appeals against a removal, and reports about a person in a room. Additive and guarded; the earlier
 * migration (100012) has already run in production, so this one changes it forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            if (! Schema::hasColumn('seminars', 'removed_refunds')) {
                $table->string('removed_refunds', 12)->nullable();   // all | complainants | none: who was refunded when a moderator removed it
            }
        });

        if (! Schema::hasColumn('seminar_reports', 'about_name')) {
            Schema::table('seminar_reports', function (Blueprint $table) {
                $table->string('about_name', 60)->nullable();         // a report about a person in the room, by the name shown in the chat
            });
        }

        if (Schema::hasIndex('seminar_reports', ['seminar_id', 'reporter_id'], 'unique')) {
            Schema::table('seminar_reports', function (Blueprint $table) {
                $table->dropUnique(['seminar_id', 'reporter_id']);
                $table->index(['seminar_id', 'reporter_id']);
            });
        }

        if (! Schema::hasTable('seminar_removal_appeals')) {
            Schema::create('seminar_removal_appeals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seminar_id')->unique();    // one appeal per removal
                $table->unsignedBigInteger('host_id');
                $table->string('message', 1000);
                $table->string('status', 12)->default('open');       // open | reinstated | denied
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->string('resolution_note', 500)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_removal_appeals');
    }
};
