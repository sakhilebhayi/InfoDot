<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small curated list of topics, and the two links that put questions and solutions under them. Only moderators
 * change the list. Topics are never deleted while anything uses them; a retired topic is switched off instead
 * (`active`), so old content keeps its label and new content cannot choose it.
 *
 * questions.id and solutions.id are INT UNSIGNED (`increments`), so the keys that point at them must be too: a
 * bigint foreign-id column is refused by MySQL although SQLite accepts it. Every create is guarded so a re-run after a
 * half-finished deploy does nothing twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name', 80);
                $table->string('description', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('question_topic')) {
            Schema::create('question_topic', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('question_id');
                $table->unsignedBigInteger('topic_id');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
                $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
                $table->unique(['question_id', 'topic_id']);
                $table->index('topic_id');
            });
        }

        if (! Schema::hasTable('solution_topic')) {
            Schema::create('solution_topic', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('solution_id');
                $table->unsignedBigInteger('topic_id');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('solution_id')->references('id')->on('solutions')->cascadeOnDelete();
                $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
                $table->unique(['solution_id', 'topic_id']);
                $table->index('topic_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_topic');
        Schema::dropIfExists('question_topic');
        Schema::dropIfExists('topics');
    }
};
