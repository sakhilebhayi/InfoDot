<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the person wants to see "questions you might be able to answer" on their Dot Points page. Off until they
 * switch it on. Additive and guarded so a re-run does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'topic_suggestions')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('topic_suggestions')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'topic_suggestions')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('topic_suggestions');
        });
    }
};
