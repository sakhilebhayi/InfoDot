<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A person reporting a question, answer or solution to the moderators. One open report per person per item. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->morphs('reportable');
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 32); // spam | duplicate | low_quality | harmful | other
            $table->text('detail')->nullable();
            $table->string('status', 16)->default('open'); // open | actioned | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique(['reportable_type', 'reportable_id', 'reporter_id', 'status'], 'one_open_report_per_person_per_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
