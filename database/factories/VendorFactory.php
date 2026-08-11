<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->company(),
            'vat_number' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'active' => true,
            'lock_version' => 1,
        ];
    }
}
