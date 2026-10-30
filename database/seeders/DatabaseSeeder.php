<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // R-21: `php artisan migrate:fresh --seed` rebuilds the whole local baseline in one command.
        // The seeder itself refuses to run in production.
        $this->call(DemoSeeder::class);
    }
}
