<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-59 Phase 1A: one row per account saying where the person came from. No IP address, no user agent, no device data:
 * only a referral code, campaign tag, the referring host and the page they landed on, kept for as long as the account exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acquisition_touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('channel', 24)->index();
            $table->string('referral_code', 32)->nullable()->index();
            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 80)->nullable()->index();
            $table->string('utm_term', 80)->nullable();
            $table->string('utm_content', 80)->nullable();
            $table->string('referrer_host', 120)->nullable();
            $table->string('landing_path', 190)->nullable();
            $table->string('platform_slug', 40)->nullable()->index();
            $table->timestamp('touched_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_touches');
    }
};
