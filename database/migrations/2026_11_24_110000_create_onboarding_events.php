<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-59 Phase 1B: each onboarding step is written once, at the moment it happens: that is what makes abandonment measurable.
 * `scope` is the business the step belongs to (0 for a step about the account itself), so a person is counted once per step and business.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('scope')->default(0);
            $table->string('step', 32);
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->unique(['user_id', 'scope', 'step']);
            $table->index(['step', 'occurred_at']);
        });

        Schema::table('team_profiles', function (Blueprint $table) {
            $table->json('objectives')->nullable();
            $table->string('stage', 20)->nullable();
            $table->string('size_band', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropColumn(['objectives', 'stage', 'size_band']);
        });

        Schema::dropIfExists('onboarding_events');
    }
};
