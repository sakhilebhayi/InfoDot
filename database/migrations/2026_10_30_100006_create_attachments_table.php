<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-15: one attachments table serves questions, solutions, solution steps, answers and comments. A row starts unbound
 * (owner-only, expires_at set); submitting the form binds it to its content and clears the expiry. Files live on the
 * private local disk and are only ever served through the permission-checked controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id');
            $table->foreignId('team_id')->nullable();
            $table->string('attachable_type')->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->string('kind', 10); // image | pdf | video
            $table->string('path', 255);
            $table->string('thumb_path', 255)->nullable();
            $table->string('name', 160); // the sanitised original file name, for display and downloads
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('caption', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 12)->default('ready'); // ready | failed (processing is best-effort and after the fact)
            $table->longText('extracted_text')->nullable(); // searchable text pulled from PDFs (phase 5)
            $table->json('meta')->nullable(); // width/height/pages where known
            $table->timestamp('expires_at')->nullable(); // set while unbound; prune deletes expired orphans
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id', 'sort_order'], 'attachments_attachable');
            $table->index(['user_id', 'created_at'], 'attachments_owner');
            $table->index('expires_at', 'attachments_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
