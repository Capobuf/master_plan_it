<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use RuntimeException;

final class SeedDemoDataCommand extends Command
{
    protected $signature = 'demo:seed';
    protected $description = 'Create or update the isolated deterministic demo tenant data set';

    public function handle(DemoDataSeeder $seeder): int
    {
        if (app()->environment('production')) { throw new RuntimeException('Demo data cannot be seeded in production.'); }
        $seeder->run();
        $this->info('Demo tenant data seeded.');
        return self::SUCCESS;
    }
}
