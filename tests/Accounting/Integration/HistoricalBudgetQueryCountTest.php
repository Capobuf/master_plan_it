<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class HistoricalBudgetQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_query_count_is_constant_as_the_annual_expense_set_grows(): void
    {
        $single = $this->measure(1);
        $many = $this->measure(20);

        $this->assertSame($single['queries'], $many['queries']);
        $this->assertLessThanOrEqual(10, $many['queries']);
        $this->assertSame(1, $single['expenses']);
        $this->assertSame(20, $many['expenses']);
    }

    /** @return array{queries: int, expenses: int} */
    private function measure(int $expenseCount): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        for ($index = 0; $index < $expenseCount; $index++) {
            $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
            $row = ExpenseRow::factory()->for($expense)->create([
                'tenant_id' => $tenant->getKey(),
                'type' => ExpenseType::Estimate,
                'spend_date' => null,
            ]);
            $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        }
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $result = app(HistoricalAnnualBudgetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            '2026-03-01T10:30:00Z',
        );
        $queries = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return ['queries' => $queries, 'expenses' => count($result['expenses'])];
    }
}
