<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company invitations get a life (R-49, finding L-09): 14 days from when they are sent or sent again. An invitation that
 * exists when this runs was sent without an end date, so it gets a full 14 days from now, not a date in the past.
 * Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_invitations') || Schema::hasColumn('team_invitations', 'expires_at')) {
            return;
        }

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->index();
        });

        DB::table('team_invitations')->whereNull('expires_at')->update(['expires_at' => now()->addDays((int) config('licensing.invitation_days', 14))]);
    }

    public function down(): void
    {
        if (Schema::hasTable('team_invitations') && Schema::hasColumn('team_invitations', 'expires_at')) {
            Schema::table('team_invitations', function (Blueprint $table) {
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            });
        }
    }
};
