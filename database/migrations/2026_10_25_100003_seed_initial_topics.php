<?php

use App\Support\Knowledge\TopicCatalogue;
use Illuminate\Database\Migrations\Migration;

/**
 * The starting list of topics (see TopicCatalogue). Add-only and keyed by slug, so a re-run does nothing, and topics a
 * moderator has since renamed or retired are not touched. Nothing is tagged: existing questions and solutions stay
 * untagged until a moderator says what they are about.
 */
return new class extends Migration
{
    public function up(): void
    {
        TopicCatalogue::seed();
    }

    public function down(): void
    {
        // Topics may now be in use and carry moderators' edits; rolling back must not delete them.
    }
};
