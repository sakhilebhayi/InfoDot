<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per ticket sale (R-49, finding L-03). A registration is a person's current place at a seminar and is reused when
 * they buy again after a refund, so it could not also be the record of each sale: the second purchase overwrote the first
 * one's reference, amount and payment date, which the monthly commission invoice reads. A sale is written when a payment is
 * confirmed and is never reused. Refund lines gain the sale they belong to.
 *
 * Guarded and reversible. The backfill makes one sale for every registration that holds a confirmed payment and points that
 * registration's refund lines at it. An earlier sale that was already overwritten on a row cannot be recovered.
 * users.id is INT UNSIGNED, so the buyer key is an unsigned integer with its own foreign key; it is set to null when the
 * account goes, which keeps the money record with nobody readable on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seminar_ticket_sales')) {
            Schema::create('seminar_ticket_sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('registration_id')->index();
                $table->unsignedBigInteger('seminar_id');
                $table->unsignedInteger('user_id')->nullable();
                $table->string('ticket_ref', 64)->unique();
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 3);
                $table->string('state', 20)->default('paid');       // paid | partially_refunded | refunded | chargeback
                $table->unsignedInteger('refunded_cents')->default(0);
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('refund_requested_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['seminar_id', 'paid_at']);
                $table->index('paid_at');
                $table->index(['state', 'refund_requested_at']);
            });
        }

        if (Schema::hasTable('seminar_refund_lines') && ! Schema::hasColumn('seminar_refund_lines', 'sale_id')) {
            Schema::table('seminar_refund_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('sale_id')->nullable()->index();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::hasTable('seminar_refund_lines') && Schema::hasColumn('seminar_refund_lines', 'sale_id')) {
            Schema::table('seminar_refund_lines', function (Blueprint $table) {
                $table->dropIndex(['sale_id']);
                $table->dropColumn('sale_id');
            });
        }

        Schema::dropIfExists('seminar_ticket_sales');
    }

    /** One sale per registration with a confirmed payment. Safe to run twice: a reference that already has a sale is left alone. */
    private function backfill(): void
    {
        if (! Schema::hasTable('seminar_registrations') || ! Schema::hasColumn('seminar_registrations', 'paid_at')) {
            return;
        }

        $states = ['paid', 'partially_refunded', 'refunded', 'chargeback'];
        $hasRefunds = Schema::hasColumn('seminar_registrations', 'refunded_cents');

        DB::table('seminar_registrations')->whereNotNull('paid_at')->whereNotNull('ticket_ref')->orderBy('id')
            ->chunkById(500, function ($registrations) use ($states, $hasRefunds) {
                foreach ($registrations as $r) {
                    if (DB::table('seminar_ticket_sales')->where('ticket_ref', $r->ticket_ref)->exists()) {
                        continue;
                    }

                    $saleId = DB::table('seminar_ticket_sales')->insertGetId([
                        'registration_id' => $r->id,
                        'seminar_id' => $r->seminar_id,
                        'user_id' => DB::table('users')->where('id', $r->user_id)->exists() ? $r->user_id : null,
                        'ticket_ref' => $r->ticket_ref,
                        'amount_cents' => (int) $r->amount_cents,
                        'currency' => $r->currency ?: 'ZAR',
                        'state' => in_array($r->payment_status, $states, true) ? $r->payment_status : 'paid',
                        'refunded_cents' => $hasRefunds ? (int) $r->refunded_cents : 0,
                        'paid_at' => $r->paid_at,
                        'refund_requested_at' => $r->refund_requested_at,
                        'refunded_at' => $r->refunded_at,
                        'created_at' => $r->paid_at,
                        'updated_at' => now(),
                    ]);

                    if (Schema::hasTable('seminar_refund_lines')) {
                        DB::table('seminar_refund_lines')->where('registration_id', $r->id)->whereNull('sale_id')->update(['sale_id' => $saleId]);
                    }
                }
            });
    }
};
