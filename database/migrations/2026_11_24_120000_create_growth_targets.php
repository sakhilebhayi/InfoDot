<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-59 Phase 1D: planning targets for the Growth Centre. They are kept apart from measured figures and never mixed into them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_targets', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 40)->unique();
            $table->unsignedBigInteger('value');
            $table->date('target_date')->nullable();
            $table->unsignedInteger('set_by')->nullable();
            $table->timestamps();

            $table->foreign('set_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_targets');
    }
};
