<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-29 Part C: protected identities of verified businesses, the identity conflicts found against
 * them, and relationships (branch, subsidiary, franchise…) a verified business has confirmed.
 * Registration numbers appear here only as hashes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verified_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_verification_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->string('name_key', 160)->nullable();
            $table->string('name_skeleton', 160)->nullable();
            $table->string('trading_key', 160)->nullable();
            $table->string('trading_skeleton', 160)->nullable();
            $table->string('registration_hash', 64)->nullable();
            $table->string('registration_country', 60)->nullable();
            $table->string('website_host', 190)->nullable();
            $table->string('email_domain', 190)->nullable();
            $table->string('logo_hash', 16)->nullable();
            $table->string('cover_hash', 16)->nullable();
            $table->timestamps();
            $table->index(['active', 'registration_hash']);
            $table->index(['active', 'website_host']);
            $table->index(['team_id', 'active']);
        });

        Schema::create('identity_conflicts', function (Blueprint $table) {
            $table->id();
            // The account whose details match (never called an impersonator by the system).
            $table->foreignId('subject_team_id')->constrained('teams')->cascadeOnDelete();
            // The verified business it matches.
            $table->foreignId('protected_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('verified_identity_id')->nullable()->constrained()->nullOnDelete();
            $table->json('signals');
            $table->unsignedSmallInteger('score');
            // low | medium | high | critical
            $table->string('level', 10);
            // open | dismissed | related | escalated
            $table->string('status', 10)->default('open');
            $table->string('trigger', 24);
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'level']);
            $table->index(['subject_team_id', 'status']);
            $table->index(['protected_team_id', 'status']);
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('business_relationships', function (Blueprint $table) {
            $table->id();
            // The account claiming the relationship…
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // …with this verified business, which confirms or rejects it.
            $table->foreignId('parent_team_id')->constrained('teams')->cascadeOnDelete();
            // branch | subsidiary | franchise | distributor | representative
            $table->string('kind', 16);
            // pending | confirmed | rejected | ended
            $table->string('status', 10)->default('pending');
            $table->text('note')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'parent_team_id', 'status']);
            $table->index(['parent_team_id', 'status']);
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_relationships');
        Schema::dropIfExists('identity_conflicts');
        Schema::dropIfExists('verified_identities');
    }
};
