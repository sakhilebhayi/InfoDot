<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-62 phase 4: a shareable link a company can hand out to bring people in. Only a hash of the token is stored (the link is shown once),
 * every link has an end date, a role (never above member) and an optional limit on how many people may use it, and it can be revoked.
 * Guarded and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_invite_links')) {
            return;
        }

        Schema::create('team_invite_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedInteger('created_by')->nullable();   // users.id is an unsigned INT
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('role', 32)->default('member');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invite_links');
    }
};
