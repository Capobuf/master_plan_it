<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class TestResetGreenfield extends Command
{
    public const DEMO_SEED_BINDING = 'testing.greenfield-demo-seed';

    protected $signature = 'app:test-reset-greenfield {--seed : Seed disposable demonstration data}';

    protected $description = 'Rebuild only the explicitly allowed disposable Greenfield test database.';

    public function handle(): int
    {
        $configured = config('database.connections.'.config('database.default'));
        if (! is_array($configured)
            || ! app()->environment(['local', 'testing'])
            || ($configured['driver'] ?? null) !== 'mysql'
            || ($configured['host'] ?? null) !== 'mysql'
            || ($configured['database'] ?? null) !== 'master_plan_it_test') {
            $this->error('Refusing reset: local|testing, MySQL host mysql and master_plan_it_test are required.');

            return self::FAILURE;
        }
        $connection = DB::connection();
        $mode = (string) ($connection->selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode ?? '');
        $safe = str_contains($mode, 'STRICT_TRANS_TABLES');
        if (! $safe) {
            $this->error('Refusing reset: local|testing, MySQL host mysql, master_plan_it_test and STRICT_TRANS_TABLES are required.');

            return self::FAILURE;
        }
        $arguments = ['--force' => true];
        if ($this->option('seed')) {
            app()->instance(self::DEMO_SEED_BINDING, true);
            $arguments['--seed'] = true;
        }

        return $this->call('migrate:fresh', $arguments);
    }
}
