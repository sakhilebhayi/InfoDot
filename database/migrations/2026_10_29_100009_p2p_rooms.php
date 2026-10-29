<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Small peer-to-peer seminar rooms (R-13): how a seminar is sent, who is connected to whom, the connection messages, and the room chat. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            if (! Schema::hasColumn('seminars', 'p2p_quality')) {
                $table->string('p2p_quality', 8)->nullable();
                $table->unsignedInteger('p2p_uplink_kbps')->nullable();   // what the host's upload test measured when they set the seminar up
            }
        });

        if (! Schema::hasTable('p2p_peers')) {
            Schema::create('p2p_peers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seminar_id');
                $table->unsignedBigInteger('user_id');
                $table->string('peer_id', 36)->unique();
                $table->string('role', 8);                       // host | viewer
                $table->string('parent_peer_id', 36)->nullable()->index();
                $table->unsignedTinyInteger('depth')->default(0);
                $table->boolean('can_relay')->default(false);
                $table->string('status', 12)->default('connecting'); // connecting | live | gone | removed
                $table->timestamp('last_seen')->nullable();
                $table->timestamps();

                $table->unique(['seminar_id', 'user_id']);
                $table->index(['seminar_id', 'status']);
            });
        }

        if (! Schema::hasTable('p2p_signals')) {
            Schema::create('p2p_signals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seminar_id')->index();
                $table->string('from_peer', 36);
                $table->string('to_peer', 36)->index();
                $table->string('type', 8);                       // hello | offer | answer | ice
                $table->text('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('p2p_chat')) {
            Schema::create('p2p_chat', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seminar_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->string('name', 60);
                $table->string('body', 300);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_chat');
        Schema::dropIfExists('p2p_signals');
        Schema::dropIfExists('p2p_peers');
    }
};
