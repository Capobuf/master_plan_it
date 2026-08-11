<?php

namespace Database\Factories;

use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('tenant-####??'),
            'state' => TenantState::Active,
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
            'budget_basis' => BudgetBasis::Net,
            'attachment_quota_bytes' => '2147483648',
            'deletion_reason_required' => false,
            'lock_version' => 1,
        ];
    }
}
