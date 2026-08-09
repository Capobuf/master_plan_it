<?php

namespace Tests\Feature\Api\Budget;

use App\Models\PlanningYear;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class HistoricalBudgetApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_cutoff_before_activation_returns_the_stable_contract_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey().'&as_of=2026-03-01T09:59:59Z')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HISTORY_BEFORE_ACTIVATION');
    }

    public function test_historical_budget_is_tenant_scoped_and_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget?planning_year_id='.$foreignYear->getKey().'&as_of=2026-03-01')
            ->assertNotFound();
    }
}
