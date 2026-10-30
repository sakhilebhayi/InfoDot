<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-20 §2: the central platform registry. Every Dot platform must be registered and active here
 * before the identity provider will authenticate for it; the row mirrors the platform's Passport
 * client and carries the operational metadata the brief requires (environment, status, health).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dot_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain');
            $table->string('environment')->default('production');
            $table->string('oauth_client_id', 100)->unique();
            $table->json('redirect_uris');
            $table->json('post_logout_redirect_uris')->nullable();
            $table->string('backchannel_logout_uri')->nullable();
            $table->json('scopes');
            $table->string('status', 20)->default('active'); // pending | active | suspended
            $table->timestamp('last_authenticated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sso_platform_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dot_platform_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_issued_at');
            $table->timestamps();
            $table->unique(['user_id', 'dot_platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_platform_sessions');
        Schema::dropIfExists('dot_platforms');
    }
};
