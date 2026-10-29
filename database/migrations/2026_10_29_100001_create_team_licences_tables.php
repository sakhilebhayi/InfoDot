<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seat licensing for companies (R-13). `team_licences` holds one row per company: how many seats it has bought beyond the included ones and
 * the state of its billing. `team_licence_events` is the audit trail of every change to who holds or could hold a seat. Both are additive and
 * guarded so a re-run does nothing twice. No row is needed for a company to work: a missing licence means "the included seats, free".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_licences')) {
            Schema::create('team_licences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->unique();
                $table->unsignedSmallInteger('included_seats')->nullable(); // null: the configured default
                $table->unsignedSmallInteger('purchased_seats')->default(0);
                $table->string('billing_status', 16)->default('free');      // free | active | past_due | cancelled
                $table->string('billing_ref', 80)->nullable();              // the billing system's subscription reference
                $table->timestamp('grace_until')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_licence_events')) {
            Schema::create('team_licence_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->index();
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('type', 32);        // invited | joined | left | invitation_cancelled | blocked | capacity_changed
                $table->string('subject', 120)->nullable(); // an email or a note; never a password or token
                $table->json('details')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['team_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_licence_events');
        Schema::dropIfExists('team_licences');
    }
};
