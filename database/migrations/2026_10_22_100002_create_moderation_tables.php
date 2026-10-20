<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `moderation_actions` is the audit trail of every moderator decision: rows are only ever added, never updated or
 * deleted (the model refuses both). `moderation_appeals` holds the author's one appeal against a hide.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('moderation_actions')) {
            Schema::create('moderation_actions', function (Blueprint $table) {
                $table->id();
                // users.id is INT UNSIGNED. A deleted account must not delete the trail, so the link is cut (null) and the
                // person's uuid, copied here when the row is written, still says who acted.
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('actor_uuid', 36)->nullable();
                $table->morphs('subject');
                $table->string('action', 24); // dismissed | hidden | restored | acceptance_revoked | appeal_upheld | appeal_denied | verified | unverified
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('report_id')->nullable();
                $table->unsignedBigInteger('appeal_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['subject_type', 'subject_id', 'id'], 'moderation_actions_by_subject');
            });
        }

        if (! Schema::hasTable('moderation_appeals')) {
            Schema::create('moderation_appeals', function (Blueprint $table) {
                $table->id();
                $table->morphs('subject');
                // The 'hidden' audit row this appeal answers: one appeal per hide, so a denied appeal is final.
                $table->unsignedBigInteger('hide_action_id');
                $table->unsignedInteger('appellant_id');
                $table->unsignedInteger('hidden_by')->nullable(); // the moderator who hid it; they may not decide the appeal
                $table->text('body');
                $table->string('status', 12)->default('open'); // open | upheld | denied
                $table->unsignedInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();

                $table->foreign('appellant_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
                $table->unique(['subject_type', 'subject_id', 'hide_action_id'], 'one_appeal_per_hide');
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_appeals');
        Schema::dropIfExists('moderation_actions');
    }
};
