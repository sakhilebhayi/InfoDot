<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoInstaller;
use App\Services\Demo\DemoRegistry;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * R-49: puts the labelled demonstration content on the site, so InfoDot can be shown working where little has been
 * written yet. Safe to run again (a second run changes nothing), needs no queue worker, and on production runs only
 * with --force. The accounts' passwords are generated here, printed once and stored nowhere but as hashes.
 */
class DemoInstall extends Command
{
    protected $signature = 'demo:install
        {--rotate : Issue new one-time passwords for the demonstration accounts}
        {--force : Required when the application is in production}';

    protected $description = 'Install the labelled demonstration content (two companies, four people, questions, solutions, an enquiry) (R-49)';

    public function handle(DemoInstaller $installer, DemoRegistry $registry): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->error('This is production. Demonstration content is installed here only with --force. Nothing was changed.');

            return self::FAILURE;
        }

        if ($registry->installed()) {
            if (! $this->option('rotate')) {
                $this->info('Demonstration content is already installed. Nothing was changed.');
                $this->line('New passwords: php artisan demo:install --rotate. Remove it: php artisan demo:remove.');

                return self::SUCCESS;
            }

            $this->passwords($installer->rotate(), 'New passwords. The old ones no longer work.');

            return self::SUCCESS;
        }

        try {
            $passwords = $installer->install();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Demonstration content installed. Every record is labelled "Demonstration" wherever it shows.');
        $this->table(['Kind', 'Records'], collect($installer->counts())->map(fn (int $n, string $kind) => [$kind, $n])->values()->all());

        foreach ($installer->companies() as $company) {
            $this->line('Business page: '.route('business.show', $company));
        }

        $this->passwords($passwords, 'Sign-in for the demonstration accounts.');

        return self::SUCCESS;
    }

    /** @param array<string, string> $passwords */
    private function passwords(array $passwords, string $heading): void
    {
        $this->newLine();
        $this->info($heading);
        $this->table(['Email', 'Password'], collect($passwords)->map(fn (string $password, string $email) => [$email, $password])->values()->all());
        $this->warn('These passwords are shown once and stored nowhere. Lost them? Run demo:install --rotate.');
    }
}
