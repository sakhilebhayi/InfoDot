<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid seminars (R-13). A ticket price on the seminar (null = free), the payment state of each registration, a host's payout account
 * (only Billing's id for it and the last four digits: never the account number), and a record of each Billing callback so a repeat is
 * applied once. Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            if (! Schema::hasColumn('seminars', 'price_cents')) {
                $table->unsignedInteger('price_cents')->nullable();
                $table->string('currency', 3)->nullable();
            }
        });

        Schema::table('seminar_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('seminar_registrations', 'payment_status')) {
                $table->string('payment_status', 16)->default('free');   // free | pending | paid | refunded
                $table->string('ticket_ref', 64)->nullable()->unique();
                $table->unsignedInteger('amount_cents')->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('checkout_url', 500)->nullable();
                $table->timestamp('hold_expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('refund_requested_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
            }
        });

        if (! Schema::hasTable('seminar_payout_accounts')) {
            Schema::create('seminar_payout_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->unsignedBigInteger('billing_account_id');
                $table->string('bank_code', 24);
                $table->string('account_last4', 4);
                $table->string('status', 12)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seminar_ticket_events')) {
            Schema::create('seminar_ticket_events', function (Blueprint $table) {
                $table->id();
                $table->string('event', 24);
                $table->string('ticket_ref', 64);
                $table->string('outcome', 16);
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['event', 'ticket_ref']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_ticket_events');
        Schema::dropIfExists('seminar_payout_accounts');
    }
};
