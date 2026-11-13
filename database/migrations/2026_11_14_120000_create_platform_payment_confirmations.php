<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-50 step 3: the record of every payment event Dot.Finance has confirmed with a valid signature, and a written
 * reason on each catalogue change.
 *
 * `platform_payment_confirmations` is the only evidence that a company paid for a platform: one row per event id
 * (so a repeated delivery is applied once), kept for good. An entitlement whose source is `paid` with no row here is
 * a fault that `ops:check` reports. `platform_catalogue_events.reason` holds why an administrator made a change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_payment_confirmations')) {
            Schema::create('platform_payment_confirmations', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 100)->unique();
                $table->string('event', 20); // paid | renewed | payment_failed | refunded | cancelled
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('platform_key', 40);
                $table->foreignId('plan_id')->nullable()->constrained('platform_plans')->nullOnDelete();
                $table->foreignId('bundle_id')->nullable()->constrained('platform_bundles')->nullOnDelete();
                $table->unsignedBigInteger('amount_minor')->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('reference', 120)->nullable();
                $table->timestamp('period_ends_at')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->string('outcome', 20); // applied | ignored
                $table->timestamp('created_at')->nullable();

                $table->index(['team_id', 'platform_key', 'id'], 'platform_payment_confirmations_lookup');
            });
        }

        if (Schema::hasTable('platform_catalogue_events') && ! Schema::hasColumn('platform_catalogue_events', 'reason')) {
            Schema::table('platform_catalogue_events', function (Blueprint $table) {
                $table->string('reason', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('platform_catalogue_events') && Schema::hasColumn('platform_catalogue_events', 'reason')) {
            Schema::table('platform_catalogue_events', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }

        Schema::dropIfExists('platform_payment_confirmations');
    }
};
