<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-29 Part B: business verification. An application per attempt (`business_verifications`,
 * sensitive fields encrypted in the model, never on the public profile) and an append-only
 * history of everything that happened to it (`verification_events`). Evidence documents are
 * private attachments bound to the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // users.id is INT UNSIGNED (`increments`), so user references are too.
            $table->unsignedInteger('applicant_id')->nullable();
            // pending | under_review | info_required | verified | rejected | suspended | revoked | expired | withdrawn
            $table->string('status', 16)->default('pending');
            // registered_company | sole_proprietor | other_entity
            $table->string('category', 20);
            $table->string('registered_name', 160);
            $table->string('trading_name', 160)->nullable();
            $table->text('registration_number')->nullable(); // encrypted
            $table->string('registration_country', 60);
            $table->string('website', 255)->nullable();
            $table->string('official_email', 255)->nullable();
            $table->timestamp('official_email_confirmed_at')->nullable();
            $table->string('email_code_hash', 255)->nullable();
            $table->timestamp('email_code_expires_at')->nullable();
            $table->unsignedTinyInteger('email_code_attempts')->default(0);
            $table->text('phone')->nullable(); // encrypted
            $table->text('address')->nullable(); // encrypted
            $table->string('applicant_role', 80);
            $table->timestamp('declared_at');
            // What the reviewer actually confirmed — the badge panel lists exactly these.
            $table->json('checks')->nullable();
            $table->text('message_to_applicant')->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('renewal_reminded_at')->nullable();
            // The identity as verified, to notice later changes that need a fresh look.
            $table->json('identity_snapshot')->nullable();
            $table->timestamp('evidence_purged_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->foreign('applicant_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_verification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('event', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            // Reviewer-only working note; never shown to the business.
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['business_verification_id', 'created_at']);
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_events');
        Schema::dropIfExists('business_verifications');
    }
};
