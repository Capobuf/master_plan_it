<?php

namespace Tests\Feature\Expenses;

use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Services\ExpenseAggregateValidator;
use App\Domain\Projects\Enums\ProjectStage;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ExpenseProjectRelationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_tenant_project_is_accepted_and_foreign_deleted_or_project_plus_contract_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->for($center)->create(['stage' => ProjectStage::Approved]);
        $row = new SaveExpenseRowData(null, 1, (int) $vendor->getKey(), ExpenseType::Estimate, 'Riga', null, null, '100.00', false, '22', false, null, '2026-01-01', null, null, null, null, null);
        $validator = app(ExpenseAggregateValidator::class);

        $valid = $validator->validate($tenant, new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, 'Spesa', null, (int) $project->getKey(), null, null), [$row]);
        $this->assertSame($project->getKey(), $valid['header']['project_id']);

        foreach ([
            Project::factory()->for(Tenant::factory()->create())->create(),
            tap(Project::factory()->for($tenant)->create(), fn (Project $deleted) => $deleted->delete()),
        ] as $invalidProject) {
            try {
                $validator->validate($tenant, new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, 'Spesa', null, (int) $invalidProject->getKey(), null, null), [$row]);
                $this->fail('Invalid Project was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('project_id', $exception->errors());
            }
        }

        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(), 'vendor_id' => $vendor->getKey(), 'cost_center_id' => $center->getKey(),
            'title' => 'Contract', 'active' => true, 'lock_version' => 1,
        ]);
        $this->expectException(ValidationException::class);
        $validator->validate($tenant, new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, 'Spesa', null, (int) $project->getKey(), (int) $contract->getKey(), null), [$row]);
    }
}
