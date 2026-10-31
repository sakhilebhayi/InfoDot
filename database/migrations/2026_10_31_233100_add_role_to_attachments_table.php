<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-29: business media reuses the R-15 attachments pipeline. `role` marks what a row is to its
 * team (logo | cover | gallery; null = an ordinary content attachment), and `superseded_at`
 * keeps replaced branding on the record instead of deleting it (§3 image history — later the
 * raw material impersonation review compares against).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('role', 16)->nullable()->after('kind');
            $table->timestamp('superseded_at')->nullable()->after('expires_at');
            $table->index(['attachable_type', 'attachable_id', 'role', 'superseded_at'], 'attachments_media_role');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex('attachments_media_role');
            $table->dropColumn(['role', 'superseded_at']);
        });
    }
};
