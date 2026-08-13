<?php

namespace Tests\Feature\Api\Budget;

use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetLifecycleApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_legacy_close_route_is_absent_and_preparation_is_unchanged(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/budget/'.$year->getKey().'/close', ['lock_version' => 1])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseHas('planning_years', [
            'id' => $year->getKey(),
            'budget_state' => 'preparation',
            'lock_version' => 1,
        ]);
        $this->assertFalse(class_exists('App\\Domain\\Budget\\Actions\\CloseAnnualBudget', false));
    }

    public function test_route_catalog_exposes_no_close_lifecycle_bridge(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/reporting.php'));

        self::assertIsString($routes);
        $this->assertStringNotContainsString('/close', $routes);
        $this->assertStringNotContainsString('BudgetLifecycleController', $routes);
    }
}
