<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `uuid` is the person's stable identity across the ecosystem (events and the points ledger use it, never the
 * numeric id or the email). `is_moderator` marks people who may accept answers, verify solutions and handle
 * reports; it is granted by an administrator, never self-assigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('users', 'is_moderator')) {
                $table->boolean('is_moderator')->default(false)->after('email');
            }
        });

        DB::table('users')->whereNull('uuid')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'is_moderator']);
        });
    }
};
