<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reputation snapshots (append-only, auditable) and appeals (R-13). Additive and guarded; nothing existing is touched. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reputation_snapshots')) {
            Schema::create('reputation_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedSmallInteger('rules_version');
                $table->unsignedInteger('total');
                $table->string('tier', 24);
                $table->json('dimensions');      // points and the evidence counts behind each, so a recalculation can be explained
                $table->timestamp('computed_at')->useCurrent();

                $table->index(['user_id', 'id']);
            });
        }

        if (! Schema::hasTable('reputation_appeals')) {
            Schema::create('reputation_appeals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('snapshot_id');
                $table->string('message', 1000);
                $table->string('status', 12)->default('open');   // open | reviewed
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->string('resolution_note', 1000)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reputation_appeals');
        Schema::dropIfExists('reputation_snapshots');
    }
};
