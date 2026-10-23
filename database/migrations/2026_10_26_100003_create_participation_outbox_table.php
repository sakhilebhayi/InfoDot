<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Participation events waiting to reach Dot.Memory, for people who opted in. A row holds only what Memory is sent:
 * a deterministic event id, the person's uuid, a topic slug, the kind and an opaque contribution reference. It is
 * written in the same request that caused it, so a Memory outage loses nothing; the flush command retries with
 * growing delays. Rows go when the person turns sharing off or their account is deleted (cascade).
 * users.id is INT UNSIGNED, so user_id must be too (MySQL refuses a bigint foreign key).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('participation_outbox')) {
            return;
        }

        Schema::create('participation_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->unsignedInteger('user_id');
            $table->string('contribution_ref', 64);
            $table->string('topic', 64);
            $table->string('kind', 16);
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['sent_at', 'failed_at', 'next_attempt_at']);
            $table->index(['user_id', 'contribution_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participation_outbox');
    }
};
