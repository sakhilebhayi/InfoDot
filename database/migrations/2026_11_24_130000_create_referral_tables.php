<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** R-59 Phase 1C: referral codes, referrals, rewards, tiers and an append-only trail. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('referral_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('referrals_required')->unique();
            $table->unsignedInteger('points');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained('referral_codes')->cascadeOnDelete();
            $table->unsignedBigInteger('referrer_team_id')->index();
            // users.id is a legacy unsigned INT.
            $table->unsignedInteger('referred_user_id')->unique();
            // registered | verified | activated | reversed | refused
            $table->string('state', 16)->index();
            $table->string('reason', 80)->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->foreign('referred_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_team_id')->index();
            $table->foreignId('tier_id')->constrained('referral_tiers');
            $table->unsignedInteger('points');
            // held | due | sent | reversed
            $table->string('status', 12)->index();
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('release_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_id')->nullable()->index();
            $table->unsignedBigInteger('reward_id')->nullable();
            $table->string('type', 40);
            $table->string('reason', 160)->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Starting values (decision D7): editable by an administrator, never changed by this migration again.
        $now = now();
        foreach ([[5, 100], [20, 500], [100, 3000], [500, 20000]] as [$required, $points]) {
            DB::table('referral_tiers')->insert(['referrals_required' => $required, 'points' => $points, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['referral_events', 'referral_rewards', 'referrals', 'referral_tiers', 'referral_codes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
