<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The points outbox kept events about one contribution in order by searching every earlier unsent payload for the contribution's reference
 * with LIKE: a scan per row, per minute, and a long outage made it quadratic. The reference gets its own indexed column. Unsent rows are
 * filled in here; rows the code writes from now on carry it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reward_outbox') || Schema::hasColumn('reward_outbox', 'contribution_ref')) {
            return;
        }

        Schema::table('reward_outbox', function (Blueprint $table) {
            $table->string('contribution_ref', 191)->nullable();
            $table->index(['contribution_ref', 'sent_at', 'failed_at'], 'reward_outbox_ref_state');
        });

        DB::table('reward_outbox')->whereNull('sent_at')->whereNull('failed_at')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $ref = json_decode((string) $row->payload, true)['contribution_ref'] ?? null;

                if (is_string($ref)) {
                    DB::table('reward_outbox')->where('id', $row->id)->update(['contribution_ref' => mb_substr($ref, 0, 191)]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('reward_outbox', 'contribution_ref')) {
            Schema::table('reward_outbox', function (Blueprint $table) {
                $table->dropIndex('reward_outbox_ref_state');
                $table->dropColumn('contribution_ref');
            });
        }
    }
};
