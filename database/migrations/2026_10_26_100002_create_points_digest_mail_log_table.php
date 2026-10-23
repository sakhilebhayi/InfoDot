<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per points digest InfoDot has already emailed. It is written in the same transaction as the send, so if the
 * result report to the points service fails afterwards, the next run finds the row, reports "delivered" and does not
 * send a second email. digest_id is the points service's id (text). The unique (user, period, period_start) key is the
 * second guard: one digest per person per period, even if the service ever listed a second id for the same period.
 * users.id is INT UNSIGNED, so user_id must be too (MySQL refuses a bigint foreign key).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('points_digest_mail_log')) {
            return;
        }

        Schema::create('points_digest_mail_log', function (Blueprint $table) {
            $table->id();
            $table->string('digest_id', 64)->unique();
            $table->unsignedInteger('user_id');
            $table->string('period', 8);
            $table->date('period_start');
            $table->timestamp('sent_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'period', 'period_start'], 'points_digest_one_per_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_digest_mail_log');
    }
};
