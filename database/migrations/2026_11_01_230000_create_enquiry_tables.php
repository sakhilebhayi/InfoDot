<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-33 Phase 3: the InfoDot Inbox. A conversation (an enquiry) is between one person and a
 * business — the Team, not an employee. Messages are the customer's, the business's, the
 * business's internal notes, or recorded events (status, assignment); the customer only ever sees
 * the first two. Reads are kept per person so each side has its own unread state; a business can
 * block a person from opening new enquiries with it; and a business can switch enquiries off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('subject', 160);
            $table->string('category', 20);
            // new | open | in_progress | awaiting | resolved | closed
            $table->string('status', 12)->default('new');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_business_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->timestamp('follow_up_notified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'updated_at']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['assigned_to', 'status']);
            $table->index('follow_up_on');
        });

        Schema::create('enquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            // customer | business | note | event
            $table->string('kind', 10);
            $table->text('body');
            $table->char('body_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['enquiry_id', 'id']);
            $table->index(['user_id', 'body_hash']);
        });

        Schema::create('enquiry_reads', function (Blueprint $table) {
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['enquiry_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('enquiry_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('blocked_by')->nullable();
            $table->foreign('blocked_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::table('team_profiles', function (Blueprint $table) {
            $table->boolean('accepts_enquiries')->default(true)->after('public_contact');
        });
    }

    public function down(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropColumn('accepts_enquiries');
        });
        Schema::dropIfExists('enquiry_blocks');
        Schema::dropIfExists('enquiry_reads');
        Schema::dropIfExists('enquiry_messages');
        Schema::dropIfExists('enquiries');
    }
};
