<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Demo\DemoInstaller;
use App\Services\Demo\DemoRegistry;
use App\Services\Demo\DemoRemover;
use Illuminate\Console\Command;

/**
 * R-49: says whether demonstration content is on the site, what it consists of, and whether anything a real
 * account wrote is attached to it. Reads only.
 */
class DemoStatus extends Command
{
    protected $signature = 'demo:status';

    protected $description = 'Show what demonstration content is installed (R-49)';

    public function handle(DemoInstaller $installer, DemoRemover $remover, DemoRegistry $registry): int
    {
        if (! $registry->installed()) {
            $this->info('No demonstration content is installed.');

            return self::SUCCESS;
        }

        $this->info('Demonstration content is installed.');
        $this->table(['Kind', 'Records'], collect($installer->counts())->map(fn (int $n, string $kind) => [$kind, $n])->values()->all());

        $this->line('Accounts: '.User::whereIn('id', $registry->ids(User::class))->orderBy('id')->pluck('email')->implode(', '));
        foreach ($installer->companies() as $company) {
            $this->line('Business page: '.route('business.show', $company));
        }

        $attached = $remover->attached();
        if ($attached === []) {
            $this->line('Nothing a real account wrote is attached to it: demo:remove will take all of it away.');
        } else {
            $this->warn('Real accounts have added to it: '.collect($attached)->map(fn (int $n, string $table) => $n.' in '.$table)->implode(', ').'. demo:remove will stop unless run with --with-attached.');
        }

        return self::SUCCESS;
    }
}
