<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-20 §11/§12: the local account is linked to the central identity by the immutable subject
 * (users.sso_sub = the id_token's `sub`), never by email. sso_revocations records subjects whose
 * central session ended (back-channel logout), so still-open local sessions are closed on their
 * next request whatever the session driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'sso_sub')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('sso_sub', 64)->nullable()->unique()->after('email');
            });
        }

        if (! Schema::hasTable('sso_revocations')) {
            Schema::create('sso_revocations', function (Blueprint $table) {
                $table->id();
                $table->string('sub', 64)->index();
                $table->timestamp('revoked_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_revocations');

        if (Schema::hasColumn('users', 'sso_sub')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sso_sub');
            });
        }
    }
};
