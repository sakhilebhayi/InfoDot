<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * R-33 Phase 1: the business directory. A visibility setting on the profile (public, members,
 * private), a central list of categories chosen many-to-many, structured services, and the
 * business type. Profiles already filled in stay visible to InfoDot users ("members"), which is
 * what their owners were told in R-29 Phase 1; every other business starts private.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'Mining and natural resources', 'Construction and engineering', 'Technology and software', 'Professional services',
        'Finance and accounting', 'Transport and logistics', 'Manufacturing', 'Agriculture and farming', 'Retail and wholesale',
        'Healthcare', 'Education and training', 'Hospitality and tourism', 'Marketing and communications', 'Legal services',
        'Security', 'Property and real estate', 'Consulting', 'Maintenance and technical services', 'Other business services',
    ];

    public function up(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            // public | members | private
            $table->string('visibility', 10)->default('private')->after('team_id');
            $table->string('business_type', 24)->nullable()->after('category');
            $table->index('visibility');
        });

        DB::table('team_profiles')
            ->where(fn ($q) => $q->whereNotNull('description')->orWhereNotNull('industry')->orWhereNotNull('products_services')->orWhereNotNull('website'))
            ->update(['visibility' => 'members']);

        Schema::create('business_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('business_categories')->insert(array_map(fn ($name, $i) => [
            'slug' => Str::slug($name), 'name' => $name, 'sort' => $i, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], self::CATEGORIES, array_keys(self::CATEGORIES)));

        Schema::create('business_category_team', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['team_id', 'business_category_id']);
            $table->index('business_category_id');
        });

        Schema::create('business_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 600)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['team_id', 'sort']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_services');
        Schema::dropIfExists('business_category_team');
        Schema::dropIfExists('business_categories');
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropColumn(['visibility', 'business_type']);
        });
    }
};
