<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-33 Phase 4b: discovery extras and business analytics.
 *  - Collections: a person's private lists of businesses (suppliers, contractors, to contact later).
 *  - Featured placements: granted under published rules, recorded with who granted them and why;
 *    never confused with verification or quality.
 *  - Analytics: daily counts only — no visitor, IP or account is stored with them. Search terms
 *    are the parsed words of a search, counted per business per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('business_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();
            $table->unique(['business_collection_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::create('business_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // requested | active | declined | ended
            $table->string('status', 10);
            $table->string('basis', 12)->default('editorial');
            $table->text('reason')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_at']);
            $table->index(['team_id', 'status']);
        });

        Schema::create('business_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            // view | search | call | email | website | map | service:{id}
            $table->string('metric', 24);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['team_id', 'day', 'metric']);
        });

        Schema::create('business_search_terms_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('term', 80);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['team_id', 'day', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_search_terms_daily');
        Schema::dropIfExists('business_stats_daily');
        Schema::dropIfExists('business_features');
        Schema::dropIfExists('business_collection_items');
        Schema::dropIfExists('business_collections');
    }
};
