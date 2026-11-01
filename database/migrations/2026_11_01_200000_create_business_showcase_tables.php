<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-33 Phase 2: the profile showcase. Services gain a kind (service or product) and a price note
 * the business chooses to publish; portfolio projects, locations and announcements become rows of
 * their own; the profile records which contact details are public and the industries it serves;
 * and every change to the business's public presence is written to an audit trail.
 *
 * Pictures and brochures are attachments bound to the team (R-29 media), so the permission rule
 * for who may see them is the business page's own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_services', function (Blueprint $table) {
            $table->string('kind', 10)->default('service')->after('team_id'); // service | product
            $table->string('price_note', 120)->nullable()->after('description');
        });

        Schema::table('team_profiles', function (Blueprint $table) {
            // Which contact details show on the business page. Null means all of them, as before.
            $table->json('public_contact')->nullable()->after('links');
            $table->string('industries_served', 300)->nullable()->after('products_services');
        });

        Schema::create('business_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('title', 140);
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('location', 120)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['team_id', 'sort']);
        });

        Schema::create('business_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('address', 255)->nullable();
            $table->string('city', 80);
            $table->string('province', 80)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('country', 60)->nullable();
            // The street address is the business's to publish; off, only town and province show.
            $table->boolean('show_address')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['team_id', 'sort']);
            $table->index('city');
        });

        Schema::create('business_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('title', 140);
            $table->text('body');
            $table->timestamp('published_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'published_at']);
        });

        Schema::create('business_profile_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('detail', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['team_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profile_events');
        Schema::dropIfExists('business_announcements');
        Schema::dropIfExists('business_locations');
        Schema::dropIfExists('business_projects');

        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropColumn(['public_contact', 'industries_served']);
        });

        Schema::table('business_services', function (Blueprint $table) {
            $table->dropColumn(['kind', 'price_note']);
        });
    }
};
