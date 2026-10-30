<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-16 phase 2. Mentions: a person or business pointed at on a question, answer or comment, resolved to a real
 * platform identity (one row per identity per item, so repeated @s never multiply notifications). Recommendations:
 * an existing solution offered as (part of) an answer — a reference to the original, never a copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->string('mentionable_type');
            $table->unsignedBigInteger('mentionable_id');
            $table->foreignId('mentioner_id');
            $table->foreignId('mentioned_user_id')->nullable();
            $table->foreignId('mentioned_team_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['mentionable_type', 'mentionable_id', 'mentioned_user_id'], 'mentions_user_once');
            $table->unique(['mentionable_type', 'mentionable_id', 'mentioned_team_id'], 'mentions_team_once');
            $table->index(['mentioned_user_id'], 'mentions_person');
            $table->index(['mentioned_team_id'], 'mentions_business');
        });

        Schema::create('solution_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id');
            $table->foreignId('solution_id');
            $table->foreignId('recommender_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['answer_id', 'solution_id'], 'recommendations_once');
            $table->index('solution_id', 'recommendations_solution');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_recommendations');
        Schema::dropIfExists('mentions');
    }
};
