<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications for the things that matter to a contributor: somebody answered your question, your answer was
 * accepted, your solution was verified, your work was added to the knowledge library. A row holds no text and no other
 * person's name: only what happened (kind), to which item (subject), and when. The words are written when the page is
 * opened, from what the viewer is allowed to see. (user_id, kind, ref) is unique so a repeated event is one notice.
 *
 * `users.notices_in_app` is the person's own switch. It is on by default because these are replies to their own work; they
 * can turn it off on the notifications page. users.id and questions.id and solutions.id are INT UNSIGNED, so the keys are too.
 * Every change is guarded so a re-run after a half-finished deploy does nothing twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_notices')) {
            Schema::create('user_notices', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('kind', 32);
                $table->string('subject_type', 16);
                $table->unsignedInteger('subject_id');
                $table->string('ref', 96);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['user_id', 'kind', 'ref']);
                $table->index(['user_id', 'read_at', 'id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'notices_in_app')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('notices_in_app')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notices');

        if (Schema::hasColumn('users', 'notices_in_app')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notices_in_app');
            });
        }
    }
};
