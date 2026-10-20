<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formal answers to a question (replies in a discussion stay comments). An answer can be accepted as the
 * solution by the person who asked or by a moderator, never by its own author, and a question has at most one
 * accepted answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('body_hash', 64);
            $table->string('status', 16)->default('published'); // published | flagged | removed
            $table->boolean('is_self_answer')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['question_id', 'accepted_at']);
            $table->unique(['question_id', 'user_id', 'body_hash']); // the same answer twice
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
