<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R-61: the features inside each platform that InfoDot can switch off on its own, without switching the platform off.
 * `state` is an administrative availability switch and `kind` is only a label for whether the feature is free or paid; they are
 * separate conditions and neither changes the other. A feature a platform does not list here is simply available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_features', function (Blueprint $table) {
            $table->id();
            $table->string('platform_key', 40);
            $table->string('key', 60);
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->string('kind', 12)->default('free');       // free | paid
            $table->string('state', 20)->default('available'); // available | disabled | development | retired
            $table->timestamps();

            $table->unique(['platform_key', 'key']);
        });

        // The one feature that exists in code today and is wired to this switch (Dot.Tasks AI task breakdown). Others are added from the admin centre
        // as a platform starts honouring them.
        DB::table('platform_features')->insert([
            'platform_key' => 'tasks', 'key' => 'ai_breakdown', 'name' => 'AI task breakdown',
            'description' => 'Splits a task into smaller steps with a language model. It has a running cost.',
            'kind' => 'free', 'state' => 'available', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_features');
    }
};
