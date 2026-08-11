<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'audit_retention_months' => 24,
                'lock_version' => 1,
            ],
        );
    }
}
