<?php

namespace App\Console\Commands;

use App\Services\Platforms\CatalogueSync;
use Illuminate\Console\Command;

/**
 * Gives every platform in the registry that a person can see its catalogue entry (R-50). Safe to run at any time:
 * it only adds what is missing and never touches an entry a platform administrator may have edited.
 */
class PlatformsSync extends Command
{
    protected $signature = 'platforms:sync';

    protected $description = 'Add a catalogue entry for every visible platform that has none (never overwrites an existing entry)';

    public function handle(CatalogueSync $sync): int
    {
        $added = $sync->sync();

        $this->info($added === [] ? 'Every visible platform already has a catalogue entry.' : 'Added '.count($added).' catalogue entr'.(count($added) === 1 ? 'y' : 'ies').': '.implode(', ', $added).'.');

        return self::SUCCESS;
    }
}
