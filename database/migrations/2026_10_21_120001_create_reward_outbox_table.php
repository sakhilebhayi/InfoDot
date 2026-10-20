<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contribution events waiting to reach the points ledger. A row is written in the same request that caused it, so
 * an outage of the ledger (or of the queue) loses nothing; the flush command retries with growing delays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('type', 80);
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->useCurrent();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'failed_at', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_outbox');
    }
};
