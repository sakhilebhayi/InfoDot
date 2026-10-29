<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `editor` is now `member` (R-13). Same permissions, new name; only the role label on existing rows changes. Nobody gains or loses
 * access, nobody is removed. Other platforms keep receiving `editor` on the wire for both manager and member (see App\Support\TeamRoles),
 * so they see no change either. Safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['team_user', 'team_invitations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'role')) {
                DB::table($table)->where('role', 'editor')->update(['role' => 'member']);
            }
        }
    }

    public function down(): void
    {
        // Not reversed: `member` rows created since cannot be told apart from migrated ones, and `editor` is still understood as a member.
    }
};
