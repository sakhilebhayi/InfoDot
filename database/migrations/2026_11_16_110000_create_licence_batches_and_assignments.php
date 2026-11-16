<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-53 phase 2: paid licences are bought in batches (one purchase = one batch = one Paystack subscription with its own renewal date, owner
 * decision D4) and each person's licence state is tracked per company. Additive and guarded; nothing is dropped. A company with no rows keeps
 * working: capacity is the free allowance plus the batches that are paid for right now, and a member with no assignment row is licensed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licence_batches')) {
            Schema::create('licence_batches', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->unsignedSmallInteger('quantity');
                $table->string('interval', 5);                         // month | year
                $table->unsignedBigInteger('unit_price_minor');         // ex VAT, informational: total_minor is what is charged
                $table->unsignedBigInteger('total_minor');              // ex VAT, set by InfoDot from configuration, never from a browser
                $table->string('currency', 3);
                $table->string('status', 12)->default('pending');       // pending | active | grace | ended | refunded
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->timestamp('grace_until')->nullable();
                $table->timestamp('cancelled_at')->nullable();          // cancellation requested or confirmed: the batch ends at period_end
                $table->unsignedSmallInteger('quantity_next')->nullable(); // a reduction the owner scheduled, applied when the subscription renews
                $table->string('payment_ref', 120)->nullable();         // the payment provider's reference, from Finance's confirmation
                $table->timestamp('last_event_at')->nullable();         // when the newest confirmation applied to this batch says it happened
                $table->json('price_snapshot')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['team_id', 'status']);
            });
        }

        if (! Schema::hasTable('licence_assignments')) {
            Schema::create('licence_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->unsignedInteger('user_id');
                $table->foreignId('batch_id')->nullable()->constrained('licence_batches')->nullOnDelete();
                $table->string('state', 10)->default('active');         // active | reserved | lapsed | released
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('released_at')->nullable();           // when it was lapsed or released
                $table->timestamps();

                $table->unique(['team_id', 'user_id']);
                $table->index(['team_id', 'state']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_assignments');
        Schema::dropIfExists('licence_batches');
    }
};
