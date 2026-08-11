<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlatformAdministratorSeeder extends Seeder
{
    public function __construct(private readonly PlatformAdministrator $platformAdministrator) {}

    public function run(): void
    {
        $name = $this->requiredEnvironmentValue('PLATFORM_ADMIN_NAME');
        $email = $this->requiredEnvironmentValue('PLATFORM_ADMIN_EMAIL');
        $password = $this->requiredEnvironmentValue('PLATFORM_ADMIN_PASSWORD', trimValue: false);

        DB::transaction(function () use ($name, $email, $password): void {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'tenant_id' => null,
                    'is_active' => true,
                    'lock_version' => 1,
                ]);

                $this->platformAdministrator->assign($user);

                return;
            }

            if (! $this->platformAdministrator->hasProtectedRole($user)) {
                throw new RuntimeException(
                    'A user already exists for PLATFORM_ADMIN_EMAIL and is not an active protected Administrator.'
                );
            }
        });
    }

    private function requiredEnvironmentValue(string $key, bool $trimValue = true): string
    {
        $value = Env::get($key);

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Environment variable [{$key}] must have a non-empty installation-specific value.");
        }

        return $trimValue ? trim($value) : $value;
    }
}
