<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-63 phase 3: when the customer accepted the business's terms for this booking (null when the business had none). Guarded and reversible. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'terms_accepted_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('terms_accepted_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'terms_accepted_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('terms_accepted_at');
            });
        }
    }
};
