<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per expiry warning that InfoDot has already emailed about. It is written in the same transaction as the
 * send, so if the result report to the points service fails afterwards, the next run finds the row, reports
 * "delivered" and does not send a second email. warning_id is the points service's id (kept as text so a numeric or
 * uuid id both fit). users.id is INT UNSIGNED, so user_id must be too (MySQL refuses a bigint foreign key).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('points_expiry_mail_log')) {
            return;
        }

        Schema::create('points_expiry_mail_log', function (Blueprint $table) {
            $table->id();
            $table->string('warning_id', 64)->unique();
            $table->unsignedInteger('user_id');
            $table->timestamp('sent_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_expiry_mail_log');
    }
};
