<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Verified library: answers and solutions a moderator has adopted. An entry holds only a reference to the source
 * (never a copy of its text, so hidden or changed content cannot show stale), who adopted it and when, and the Dot.Brain
 * recommendation it answered, if any. `library_outcomes` is both the record of what a moderator decided about a
 * recommendation and the outbox that reports it to Dot.Memory, so an outage of Memory never loses a decision.
 *
 * Key types: solutions.id and users.id are INT UNSIGNED (`increments`), answers.id is BIGINT UNSIGNED (`id()`), and MySQL
 * refuses a foreign key whose type differs although SQLite accepts it, so each column below matches its parent exactly.
 * Every create is guarded so a re-run after a half-finished deploy does nothing twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_entries')) {
            Schema::create('library_entries', function (Blueprint $table) {
                $table->id();
                $table->string('contribution_ref', 40)->unique(); // infodot:answer:ID or infodot:solution:ID
                $table->string('kind', 10); // answer | solution
                $table->unsignedBigInteger('answer_id')->nullable();
                $table->unsignedInteger('solution_id')->nullable();
                $table->unsignedInteger('adopted_by')->nullable(); // the moderator; the account may leave, the entry stays
                $table->timestamp('adopted_at')->useCurrent();
                $table->string('recommendation_event_id', 120)->nullable(); // Dot.Brain's decision this answered, if any
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamps();

                $table->foreign('answer_id')->references('id')->on('answers')->cascadeOnDelete();
                $table->foreign('solution_id')->references('id')->on('solutions')->cascadeOnDelete();
                $table->foreign('adopted_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['withdrawn_at', 'adopted_at']);
                $table->index('recommendation_event_id');
            });
        }

        if (! Schema::hasTable('library_outcomes')) {
            Schema::create('library_outcomes', function (Blueprint $table) {
                $table->id();
                $table->string('decision_event_id', 120)->unique(); // one outcome per recommendation, however often it is clicked
                $table->string('contribution_ref', 40);
                $table->string('outcome', 10); // adopted | declined
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('actor_ref', 36); // the moderator's users.uuid, as Memory is told
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('last_error', 255)->nullable();
                $table->timestamps();

                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['sent_at', 'failed_at', 'next_attempt_at'], 'library_outcomes_due');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_outcomes');
        Schema::dropIfExists('library_entries');
    }
};
