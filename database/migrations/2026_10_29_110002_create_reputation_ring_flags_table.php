<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advisory flags for groups of accounts that accept each other's answers (R-13 ring detection). A flag is a question for a
 * moderator, never a verdict: it bans nobody and the people in it are not told. One row per kind and member set
 * (`member_hash` is the sha1 of the sorted member ids), so detecting again updates the row instead of adding another.
 * users.id is INT UNSIGNED, so the reviewer key matches it. Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reputation_ring_flags')) {
            return;
        }

        Schema::create('reputation_ring_flags', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);                       // cycle | cluster
            $table->json('member_ids');                       // sorted user ids
            $table->string('member_hash', 40);
            $table->unsignedInteger('edge_count');            // acceptances between the members in the window
            $table->decimal('internal_ratio', 5, 4);          // share of the help the members received that came from each other
            $table->timestamp('detected_at');
            $table->string('status', 12)->default('open');    // open | dismissed | confirmed
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->string('review_note', 1000)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'member_hash']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reputation_ring_flags');
    }
};
