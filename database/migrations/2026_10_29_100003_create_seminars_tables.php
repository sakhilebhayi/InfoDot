<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Seminars, who registered, who attended, and how it was rated (R-13). Additive and guarded; nothing existing is touched. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seminars')) {
            Schema::create('seminars', function (Blueprint $table) {
                $table->id();
                $table->foreignId('host_id')->index();
                $table->unsignedBigInteger('topic_id')->nullable()->index();
                $table->string('title', 140);
                $table->string('summary', 280);
                $table->text('description')->nullable();
                $table->timestamp('starts_at')->index();
                $table->unsignedSmallInteger('duration_minutes');
                $table->unsignedSmallInteger('capacity')->nullable();
                $table->string('join_url', 500);                 // supplied by the host; shown only to registered people in the window
                $table->string('status', 12)->default('draft');   // draft | published | cancelled
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'starts_at']);
            });
        }

        if (! Schema::hasTable('seminar_registrations')) {
            Schema::create('seminar_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seminar_id');
                $table->foreignId('user_id');
                $table->string('status', 12)->default('registered'); // registered | waitlist | cancelled
                $table->timestamp('attended_at')->nullable();
                $table->timestamps();

                $table->unique(['seminar_id', 'user_id']);
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('seminar_feedback')) {
            Schema::create('seminar_feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seminar_id');
                $table->foreignId('user_id');
                $table->unsignedTinyInteger('rating');
                $table->string('comment', 1000)->nullable();     // visible to the host only
                $table->timestamps();

                $table->unique(['seminar_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_feedback');
        Schema::dropIfExists('seminar_registrations');
        Schema::dropIfExists('seminars');
    }
};
