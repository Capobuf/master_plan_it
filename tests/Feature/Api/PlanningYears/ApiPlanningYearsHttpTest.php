<?php

namespace Tests\Feature\Api\PlanningYears;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ApiPlanningYearsHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_planning_year_calendar_boundaries_and_lifecycle_are_api_resources(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')->assertOk();

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/planning-years', [
            'year_label' => 2032,
        ])->assertCreated()
            ->assertJsonPath('data.year_label', 2032)
            ->assertJsonPath('data.start_date', '2032-01-01')
            ->assertJsonPath('data.end_date', '2032-12-31');
        $id = (int) $created->json('data.id');

        $this->getJson('/api/v1/planning-years?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'links']);

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/planning-years/'.$id.'/deactivate', [
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.active', false);

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/planning-years/'.$id.'/reactivate', [
            'lock_version' => 2,
        ])->assertOk()->assertJsonPath('data.active', true);
    }
}
