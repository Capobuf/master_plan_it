<?php

namespace Tests\Feature\Expenses;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ExpensePlanningLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dataset_uses_exactly_one_selected_planning_row_and_all_immediate_actuals(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
        $estimate = ExpenseRow::factory()->for($expense)->create([
            'position' => 1,
            'type' => ExpenseType::Estimate,
            'spend_date' => null,
            'net_amount' => '90.00',
            'vat_amount' => '19.80',
            'gross_amount' => '109.80',
        ]);
        $quote = ExpenseRow::factory()->for($expense)->create([
            'position' => 2,
            'type' => ExpenseType::Quote,
            'spend_date' => null,
            'net_amount' => '100.00',
        ]);
        $actual = ExpenseRow::factory()->for($expense)->create([
            'position' => 3,
            'type' => ExpenseType::Actual,
            'spend_date' => '2026-04-01',
            'net_amount' => '-20.00',
            'vat_amount' => '-4.40',
            'gross_amount' => '-24.40',
        ]);
        $expense->forceFill(['current_planning_row_id' => $quote->getKey()])->saveQuietly();

        $dataset = app(EconomicDatasetQuery::class)->execute($actor, new TenantContext($tenant, $actor), (int) $year->getKey());
        $this->assertSame([$estimate->getKey(), $quote->getKey(), $actual->getKey()], collect($dataset->lines)->pluck('rowId')->all());

        $projection = app(EconomicEngine::class)->project($dataset);
        $this->assertSame('100.00', $projection->currentPlanning->official);
        $this->assertSame('-20.00', $projection->actual->official);
    }
}
