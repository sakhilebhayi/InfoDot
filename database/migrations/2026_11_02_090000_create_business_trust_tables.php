<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-33 Phase 4a (and R-29 Part D): reports about businesses, investigations, restrictions and
 * appeals. Nothing here is public. A report is a reason to look, never a finding; only an
 * investigation's recorded outcome can confirm impersonation or misconduct, and the business can
 * appeal it to a different reviewer. A restriction takes a business out of discovery without
 * labelling it: to everyone outside it, a restricted business simply is not there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reporter_id')->nullable();
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->string('category', 24);
            $table->text('detail');
            $table->string('priority', 8); // urgent | high | normal
            $table->string('status', 12)->default('open'); // open | investigating | actioned | dismissed
            $table->foreignId('investigation_id')->nullable();
            $table->unsignedInteger('resolved_by')->nullable();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index(['team_id', 'status']);
            $table->index(['reporter_id', 'created_at']);
        });

        Schema::create('business_investigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // potential_impersonation | identity_conflict | reported_concern
            $table->string('concern', 24);
            // open | cleared | confirmed_impersonation | confirmed_misconduct | corrected
            $table->string('status', 24)->default('open');
            $table->text('summary');
            $table->text('message_to_business')->nullable();
            $table->boolean('restricted')->default(false);
            $table->unsignedBigInteger('identity_conflict_id')->nullable();
            $table->unsignedInteger('opened_by')->nullable();
            $table->foreign('opened_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status']);
            $table->index('status');
        });

        Schema::create('business_investigation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_investigation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 32);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_investigation_id', 'id']);
        });

        Schema::create('business_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('appellant_id')->nullable();
            $table->foreign('appellant_id')->references('id')->on('users')->nullOnDelete();
            // investigation | verification
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            // Who made the decision being appealed: they may not decide the appeal.
            $table->unsignedInteger('original_decider')->nullable();
            $table->text('body');
            $table->string('status', 8)->default('open'); // open | upheld | denied
            $table->unsignedInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id']); // one appeal per decision
            $table->index(['team_id', 'status']);
            $table->index('status');
        });

        Schema::table('team_profiles', function (Blueprint $table) {
            // Set while an investigation restricts the business; cleared when it is lifted.
            $table->timestamp('restricted_at')->nullable()->after('accepts_enquiries');
            $table->index('restricted_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropIndex(['restricted_at']);
            $table->dropColumn('restricted_at');
        });
        Schema::dropIfExists('business_appeals');
        Schema::dropIfExists('business_investigation_events');
        Schema::dropIfExists('business_investigations');
        Schema::dropIfExists('business_reports');
    }
};
