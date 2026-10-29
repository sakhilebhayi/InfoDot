<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Host agreement acceptance and a mirror of the chargeback debts Dot.Billing tells us about (R-13). Additive and guarded. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seminar_host_agreements')) {
            Schema::create('seminar_host_agreements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('version', 24);
                $table->timestamp('accepted_at');
                $table->unique(['user_id', 'version']);
            });
        }

        if (! Schema::hasTable('seminar_host_debts')) {
            Schema::create('seminar_host_debts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('billing_debt_id')->unique();
                $table->string('ticket_ref', 64)->nullable();
                $table->string('currency', 3)->default('ZAR');
                $table->unsignedInteger('amount_cents');
                $table->unsignedInteger('recovered_cents')->default(0);
                $table->unsignedInteger('remaining_cents')->default(0);
                $table->string('status', 12)->default('open');       // open | cleared | written_off
                $table->string('note', 500)->nullable();             // the moderator's note when written off
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_host_debts');
        Schema::dropIfExists('seminar_host_agreements');
    }
};
