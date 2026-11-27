<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner request (2026-11-27, R-75 review): two directory listings read wrong to a visitor.
 *
 * - "BluPin AI & Coding Institue" is a typo for "Institute" (the owner's own business). Only the exact misspelling is changed,
 *   on the company name and on the listing's trading name.
 * - InfoDot's own listing showed the business type "Public entity", which it is not. Its true legal form is not recorded
 *   anywhere this code can check, so the type is cleared (the page then simply does not show one) rather than guessed.
 *
 * Exact-match updates only: if either value was already edited by hand, nothing is touched. down() puts back exactly what was
 * corrected.
 */
return new class extends Migration
{
    private const TYPO = 'BluPin AI & Coding Institue';

    private const FIXED = 'BluPin AI & Coding Institute';

    public function up(): void
    {
        DB::table('teams')->where('name', self::TYPO)->update(['name' => self::FIXED]);
        DB::table('team_profiles')->where('trading_name', self::TYPO)->update(['trading_name' => self::FIXED]);

        DB::table('team_profiles')
            ->where('business_type', 'public_entity')
            ->whereIn('team_id', DB::table('teams')->where('name', 'InfoDot')->select('id'))
            ->update(['business_type' => null]);
    }

    public function down(): void
    {
        DB::table('teams')->where('name', self::FIXED)->update(['name' => self::TYPO]);
        DB::table('team_profiles')->where('trading_name', self::FIXED)->update(['trading_name' => self::TYPO]);

        DB::table('team_profiles')
            ->whereNull('business_type')
            ->whereIn('team_id', DB::table('teams')->where('name', 'InfoDot')->select('id'))
            ->update(['business_type' => 'public_entity']);
    }
};
