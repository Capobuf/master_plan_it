<?php

namespace Database\Seeders;

use App\Console\Commands\TestResetGreenfield;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TestResetGreenfield::DEMO_SEED_BINDING)) {
            return;
        }
        if (! app()->environment('testing')) {
            throw new RuntimeException('The Greenfield demo seed is restricted to the testing environment.');
        }

        $this->call([
            PlatformSettingSeeder::class,
            PermissionCatalogueSeeder::class,
        ]);

        $administrator = User::query()->firstOrCreate(
            ['email' => 'slice-023-admin@example.test'],
            [
                'name' => 'Slice 023 Test Administrator',
                'password' => 'slice-023-test-only',
                'tenant_id' => null,
                'is_active' => true,
                'lock_version' => 1,
            ],
        );
        app(PlatformAdministrator::class)->assign($administrator);

        $this->call(DemoDataSeeder::class);
    }
}
