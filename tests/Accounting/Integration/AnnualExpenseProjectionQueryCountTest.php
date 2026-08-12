<?php

namespace Tests\Accounting\Integration;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnnualExpenseProjectionQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dataset_query_count_is_constant_as_expenses_and_rows_grow(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'Query-count contract is MySQL-only.');
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $context = new TenantContext($tenant, $actor);
        $this->createExpense($tenant, $year, $center);

        $small = $this->queryCount(fn () => app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey()));
        foreach (range(1, 12) as $_) {
            $this->createExpense($tenant, $year, $center);
        }
        $large = $this->queryCount(fn () => app(EconomicDatasetQuery::class)->execute($actor, $context, (int) $year->getKey()));

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(3, $large);
    }

    private function createExpense(Tenant $tenant, PlanningYear $year, CostCenter $center): void
    {
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Estimate,
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
    }

    private function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
