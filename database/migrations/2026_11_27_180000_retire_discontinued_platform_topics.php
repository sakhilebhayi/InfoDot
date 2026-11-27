<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plug, Billing and Farms were discontinued (owner, 2026-11-13). Their per-platform topics were seeded earlier and still showed
 * in the public topic filter. They are switched off, not deleted: a topic's address stays stable and any content already filed
 * under one keeps its link, but no new content can be filed there and the filter no longer offers them.
 */
return new class extends Migration
{
    private const RETIRED = ['billing', 'farms', 'plug'];

    public function up(): void
    {
        DB::table('topics')->whereIn('slug', self::RETIRED)->update(['active' => false]);
    }

    public function down(): void
    {
        DB::table('topics')->whereIn('slug', self::RETIRED)->update(['active' => true]);
    }
};
