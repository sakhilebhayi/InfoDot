<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds, disputes, seller details and commission invoices for paid seminars (R-13). Additive and guarded. The ticket-event record
 * gains a key so each partial refund is applied once instead of the second one being mistaken for a repeat of the first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminar_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('seminar_registrations', 'refunded_cents')) {
                $table->unsignedInteger('refunded_cents')->default(0);
                $table->unsignedSmallInteger('refund_seq')->default(0);
                $table->unsignedInteger('refund_pending_cents')->nullable();
                $table->string('dispute_status', 12)->nullable();     // open | won | lost | other
                $table->timestamp('disputed_at')->nullable();
            }
        });

        Schema::table('seminar_ticket_events', function (Blueprint $table) {
            if (! Schema::hasColumn('seminar_ticket_events', 'ref_key')) {
                $table->dropUnique(['event', 'ticket_ref']);
                $table->string('ref_key', 64)->default('');
                $table->unique(['event', 'ticket_ref', 'ref_key']);
            }
        });

        if (! Schema::hasTable('seminar_refund_lines')) {
            Schema::create('seminar_refund_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registration_id')->index();
                $table->unsignedBigInteger('seminar_id');
                $table->unsignedBigInteger('host_id')->index();
                $table->unsignedInteger('amount_cents');
                $table->string('reason', 16)->default('refund');   // refund | chargeback
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('seminar_seller_profiles')) {
            Schema::create('seminar_seller_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('trading_name', 100);
                $table->string('vat_number', 10)->nullable();
                $table->boolean('authorises_receipts')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seminar_commission_invoices')) {
            Schema::create('seminar_commission_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('host_id')->index();
                $table->char('period', 7);                           // YYYY-MM
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('seq');
                $table->string('number', 24)->unique();
                $table->string('kind', 12);                          // invoice | credit_note
                $table->integer('fee_cents');                        // commission kept, VAT included (negative for a credit note)
                $table->integer('vat_cents');
                $table->integer('excl_vat_cents');
                $table->string('currency', 3)->default('ZAR');
                $table->json('lines');                               // snapshot of what the number covers
                $table->json('issuer');                              // snapshot of InfoDot's details at issue time
                $table->timestamp('issued_at');
                $table->timestamps();

                $table->unique(['host_id', 'period']);
                $table->unique(['year', 'seq']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_commission_invoices');
        Schema::dropIfExists('seminar_seller_profiles');
        Schema::dropIfExists('seminar_refund_lines');
    }
};
