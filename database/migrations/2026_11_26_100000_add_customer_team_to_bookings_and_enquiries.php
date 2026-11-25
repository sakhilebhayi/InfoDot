<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-66 phase 2: a booking or a message sent by a company member can say which of their companies it is for, so both businesses see it as
 * business to business. Null means the person acted for themselves. A company that is deleted leaves the booking or message in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'enquiries'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'customer_team_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('customer_team_id')->nullable()->constrained('teams')->nullOnDelete();
                    $t->index('customer_team_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'enquiries'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'customer_team_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['customer_team_id']);
                    $t->dropIndex(['customer_team_id']);
                    $t->dropColumn('customer_team_id');
                });
            }
        }
    }
};
