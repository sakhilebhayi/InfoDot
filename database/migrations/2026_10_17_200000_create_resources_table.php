<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The resource registry: stable references only. A row says "this object, on this
 * platform, belongs to this company, and opens at this path". It holds no names,
 * titles or content, so it cannot become a second copy of anyone's data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resources')) {
            return;
        }

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('platform', 40);
            $table->string('platform_object_uuid', 64);
            $table->string('path', 255);
            $table->timestamps();

            $table->unique(['platform', 'platform_object_uuid']);
            $table->index(['team_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
