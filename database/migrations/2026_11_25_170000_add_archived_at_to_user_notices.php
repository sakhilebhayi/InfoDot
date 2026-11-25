<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R-64 phase 6: a notice can be put away (archived) without being deleted, so the page can show what needs attention and keep the rest as history. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_notices', 'archived_at')) {
            Schema::table('user_notices', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('read_at');
            });
        }

        if (! Schema::hasIndex('user_notices', ['user_id', 'archived_at', 'read_at'])) {
            Schema::table('user_notices', function (Blueprint $table) {
                $table->index(['user_id', 'archived_at', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_notices', 'archived_at')) {
            Schema::table('user_notices', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'archived_at', 'read_at']);
                $table->dropColumn('archived_at');
            });
        }
    }
};
