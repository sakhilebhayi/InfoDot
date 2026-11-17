<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-54 phase 1a: what the central sign-in and registration pages look like for each platform. One row per registered
 * platform (1:1 on dot_platforms, the table that already holds the OIDC client id and the exact redirect URIs), kept
 * separate from it so the identity record never carries presentation and presentation never carries a secret.
 * Additive: nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dot_platform_id')->unique()->constrained('dot_platforms')->cascadeOnDelete();
            $table->string('display_name', 80)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('mode', 5)->default('light'); // light | dark
            $table->string('background_path', 255)->nullable();
            $table->string('hero_path', 255)->nullable();
            $table->string('hero_alt', 200)->nullable();
            $table->string('headline_login', 120)->nullable();
            $table->string('headline_register', 120)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('promo_text', 200)->nullable();
            $table->string('cta_login', 40)->nullable();
            $table->string('cta_register', 40)->nullable();
            $table->json('approved_redirect_domains')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            // users.id is a legacy unsigned INT (increments), so the referencing column is an unsigned INT too (MySQL refuses a bigint).
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_branding');
    }
};
