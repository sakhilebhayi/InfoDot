<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-59 Phase 1E: where a company is invoiced and its VAT number, for seat invoices. Private to the company; never shown publicly. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->string('billing_country', 60)->nullable();
            $table->string('vat_number', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('team_profiles', function (Blueprint $table) {
            $table->dropColumn(['billing_country', 'vat_number']);
        });
    }
};
