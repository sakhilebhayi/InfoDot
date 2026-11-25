<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-62 phase 2: one row for every attempt to describe a company, whichever way the details arrive (typed by hand, read from a website, read from a
 * document). It keeps what each source offered, how sure it was, where two sources disagreed and what the person confirmed, so a later import
 * never silently overwrites something they confirmed. `team_profiles.field_sources` records, for each captured field of a company, where the value
 * came from and when it was confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_imports', function (Blueprint $table) {
            $table->id();
            // users.id is a legacy unsigned INT, so the referencing column must be too (MySQL refuses a bigint foreign key to it).
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('source', 12);                    // manual | website | document
            $table->string('status', 12)->default('draft');   // draft | confirmed | abandoned
            $table->string('source_ref', 255)->nullable();    // a host for a website, a private file path for a document
            $table->json('extracted')->nullable();            // field => {value, confidence, source}
            $table->json('conflicts')->nullable();            // field => {values: [{value, confidence, source}, ...]}
            $table->json('confirmed')->nullable();            // what the person confirmed
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['source', 'status']);
            $table->index('expires_at');
        });

        Schema::table('team_profiles', function (Blueprint $table) {
            $table->json('field_sources')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropColumn('field_sources');
        });

        Schema::dropIfExists('company_profile_imports');
    }
};
