<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-49: the register of demonstration content. One row per record that `demo:install` (or a demonstration account,
 * later) created: which model, which key. It is the only mark a demonstration record carries, so no domain table
 * gains a column, the "Demonstration" label reads from here, and `demo:remove` deletes exactly what is listed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_records')) {
            return;
        }

        Schema::create('demo_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 120); // the model class
            $table->string('record_id', 64);    // its key (a string, so models keyed by uuid fit too)
            $table->timestamp('created_at')->nullable();

            $table->unique(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_records');
    }
};
