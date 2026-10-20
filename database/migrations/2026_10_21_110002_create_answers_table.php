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
        // The first production deploy created this table half-way (MySQL refused the foreign key below and DDL cannot
        // roll back); it holds no data, so start clean.
        Schema::dropIfExists('answers');

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // questions.id and users.id are INT UNSIGNED (`increments`), so these must be too: a bigint foreignId()
            // is refused by MySQL ("Foreign key constraint is incorrectly formed") although SQLite accepts it.
            $table->unsignedInteger('question_id');
            $table->unsignedInteger('user_id');
            $table->text('body');
            $table->string('body_hash', 64);
            $table->string('status', 16)->default('published'); // published | flagged | removed
            $table->boolean('is_self_answer')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedInteger('accepted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('accepted_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['question_id', 'accepted_at']);
            $table->unique(['question_id', 'user_id', 'body_hash']); // the same answer twice
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
