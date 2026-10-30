<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-16 phases 4-5. Improvements: a proposed change to a solution's description, to one step, or a new step — pending
 * until the original author decides; the approved solution never changes while a proposal waits. Versions: every
 * approved improvement writes the solution's new content as the next version, keeping the previous one readable, the
 * original author's name on the work, and the improver credited on the version they contributed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solution_improvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solution_id');
            $table->foreignId('proposer_id');
            $table->unsignedInteger('base_version'); // the solution version the proposal was written against
            $table->string('target_kind', 12); // description | step | new-step
            $table->unsignedBigInteger('step_id')->nullable();
            $table->string('proposed_heading', 255)->nullable();
            $table->text('proposed_body');
            $table->text('rationale');
            $table->string('status', 12)->default('pending'); // pending | clarify | approved | rejected
            $table->string('decision_note', 2000)->nullable();
            $table->foreignId('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['solution_id', 'status'], 'improvements_by_solution');
            $table->index('proposer_id', 'improvements_by_proposer');
        });

        Schema::create('solution_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solution_id');
            $table->unsignedInteger('version');
            $table->string('solution_title', 255);
            $table->longText('solution_description')->nullable();
            $table->json('steps'); // [{heading, body}] in order, the whole procedure at this version
            $table->foreignId('author_id'); // the original author: versions never transfer ownership
            $table->foreignId('contributor_id')->nullable(); // who proposed the change this version carries
            $table->string('summary', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['solution_id', 'version'], 'solution_versions_once');
        });

        Schema::table('solutions', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('solutions', function (Blueprint $table) {
            $table->dropColumn('version');
        });
        Schema::dropIfExists('solution_versions');
        Schema::dropIfExists('solution_improvements');
    }
};
