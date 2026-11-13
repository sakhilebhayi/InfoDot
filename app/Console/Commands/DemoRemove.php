<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoRegistry;
use App\Services\Demo\DemoRemover;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * R-49: takes the demonstration content off the site again: exactly the records on the demonstration register,
 * their files, and the rows that cannot exist without them. It stops, changing nothing, when real accounts have
 * added to the demonstration content, unless told to remove that too.
 */
class DemoRemove extends Command
{
    protected $signature = 'demo:remove
        {--with-attached : Also remove what real accounts added to demonstration content (their answers, comments or enquiries on it)}
        {--force : Required when the application is in production}';

    protected $description = 'Remove the demonstration content and its files, and nothing else (R-49)';

    public function handle(DemoRemover $remover, DemoRegistry $registry): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('This is production. Demonstration content is removed here only with --force. Nothing was changed.');

            return self::FAILURE;
        }

        if (! $registry->installed()) {
            $this->info('No demonstration content is installed. Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $removed = $remover->remove((bool) $this->option('with-attached'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('Look at it with demo:status. To remove it together with the demonstration content: php artisan demo:remove --with-attached.');

            return self::FAILURE;
        }

        $this->info('Demonstration content removed.');
        $this->table(['Table', 'Rows removed'], collect($removed)->map(fn (int $n, string $table) => [$table, $n])->values()->all());

        return self::SUCCESS;
    }
}
