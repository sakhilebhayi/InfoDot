<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-16 phase 1: "I tried this and it works". One row per person per solution — a statement that they implemented it
 * in a real situation and it worked, kept apart from likes and views on purpose. The feedback columns are optional
 * context the person may add afterwards; they are never required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solution_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solution_id');
            $table->foreignId('user_id');
            $table->string('business_industry', 100)->nullable();
            $table->string('applied_part', 255)->nullable();
            $table->string('results', 1000)->nullable();
            $table->string('timeframe', 100)->nullable();
            $table->boolean('recommend')->nullable();
            $table->timestamps();

            $table->unique(['solution_id', 'user_id'], 'solution_confirmations_once');
            $table->index('user_id', 'solution_confirmations_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_confirmations');
    }
};
