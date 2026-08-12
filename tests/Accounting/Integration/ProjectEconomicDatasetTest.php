<?php

namespace Tests\Accounting\Integration;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ProjectEconomicDatasetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_stage_change_never_reclassifies_selected_planning_or_actual_and_does_not_change_expenses(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $project = Project::factory()->for($tenant)->create(['stage' => ProjectStage::Idea]);
        $estimate = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey(), 'project_id' => $project->getKey()]);
        $planning = ExpenseRow::factory()->for($estimate)->create([
            'type' => 'estimate',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
        ]);
        $estimate->forceFill(['current_planning_row_id' => $planning->getKey()])->saveQuietly();
        $actual = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey(), 'project_id' => $project->getKey()]);
        ExpenseRow::factory()->for($actual)->create([
            'type' => 'actual',
            'confirmation_state' => 'to_confirm',
            'spend_date' => '2027-01-15',
            'net_amount' => '50.00',
            'vat_amount' => '11.00',
            'gross_amount' => '61.00',
        ]);
        $context = new TenantContext($tenant, $actor);

        $projectYear = fn () => app(EconomicEngine::class)->project(app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey()));
        $before = $projectYear();
        $this->assertSame('100.00', $before->currentPlanning->official);
        $this->assertSame('50.00', $before->actual->official);
        $this->assertCount(2, $before->expenses);

        $project->forceFill(['stage' => ProjectStage::Rejected, 'lock_version' => 2])->save();
        $after = $projectYear();
        $this->assertSame('100.00', $after->currentPlanning->official);
        $this->assertSame('50.00', $after->actual->official);
        $this->assertSame(1, $estimate->refresh()->lock_version);
        $this->assertSame(1, $actual->refresh()->lock_version);
    }
}
