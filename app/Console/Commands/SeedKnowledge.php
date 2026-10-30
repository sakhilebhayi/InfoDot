<?php

namespace App\Console\Commands;

use App\Services\Knowledge\KnowledgeSeeder;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * R-14: publishes the curated business-knowledge entries in database/knowledge/ under the official InfoDot account.
 * Validation is fail-closed: one bad file and nothing at all is written. Safe to run again — entries update in place
 * by their key. Run with --dry first on the host.
 */
class SeedKnowledge extends Command
{
    protected $signature = 'knowledge:seed
        {--dry : Parse and validate only; write nothing}
        {--dir= : Read entries from this folder instead of database/knowledge}
        {--force : Publish a new entry even when a community solution already has the same title}';

    protected $description = 'Publish the curated library entries in database/knowledge under the official InfoDot account (R-14)';

    public function handle(KnowledgeSeeder $seeder): int
    {
        $dir = (string) ($this->option('dir') ?: database_path('knowledge'));

        if (! is_dir($dir)) {
            $this->error('No such folder: '.$dir);

            return self::FAILURE;
        }

        $files = [];
        foreach (Finder::create()->files()->in($dir)->name('*.md')->sortByName() as $file) {
            $files[] = $file->getRealPath();
        }

        if ($files === []) {
            $this->warn('No entry files (*.md) under '.$dir.'.');

            return self::SUCCESS;
        }

        $errors = [];
        $entries = $seeder->parseAll($files, $errors);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error(count($errors).' problem(s). Nothing was written.');

            return self::FAILURE;
        }

        if ($this->option('dry')) {
            foreach ($entries as $entry) {
                $this->line(sprintf('ok  %-40s %s (%d steps, topics: %s)', $entry['key'], $entry['title'], count($entry['steps']), implode(', ', $entry['topics'])));
            }
            $this->info(count($entries).' entries parse and validate. Nothing was written (--dry).');

            return self::SUCCESS;
        }

        $official = $seeder->officialAccount();
        $skipped = 0;

        foreach ($entries as $entry) {
            $outcome = $seeder->publish($entry, $official, (bool) $this->option('force'));
            $skipped += str_starts_with($outcome, 'skipped') ? 1 : 0;
            $this->line(sprintf('%-40s %s', $entry['key'], $outcome));
        }

        $this->info(sprintf('%d entries processed as %s (#%d), %d skipped.', count($entries), $official->name, $official->id, $skipped));

        return self::SUCCESS;
    }
}
