<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-29 Part A: the business profile behind a company's public identity page. One row per team,
 * public-facing fields only — verification evidence and identity records live in their own
 * tables (phases 2–3), never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('trading_name', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('industry', 80)->nullable();
            $table->string('category', 80)->nullable();
            $table->text('products_services')->nullable();
            $table->string('location', 160)->nullable();
            $table->string('regions', 160)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('registration_number', 60)->nullable();
            $table->string('registration_country', 60)->nullable();
            $table->date('established_on')->nullable();
            $table->string('size', 40)->nullable();
            $table->json('links')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_profiles');
    }
};
