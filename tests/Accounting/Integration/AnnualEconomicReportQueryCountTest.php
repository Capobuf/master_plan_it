<?php

namespace Tests\Accounting\Integration;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Reporting\Queries\AnnualEconomicReportQuery;
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

final class AnnualEconomicReportQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_current_and_historical_report_query_counts_are_constant_as_rows_grow(): void
    {
        $small = $this->measure(1);
        $large = $this->measure(20);

        $this->assertSame($small['current'], $large['current']);
        $this->assertSame($small['historical'], $large['historical']);
        $this->assertSame(15, $large['current']);
        $this->assertSame(14, $large['historical']);
    }

    /** @return array{current: int, historical: int} */
    private function measure(int $count): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        foreach (range(1, $count) as $position) {
            $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
            $row = ExpenseRow::factory()->for($expense)->create([
                'tenant_id' => $tenant->getKey(), 'position' => $position,
                'type' => ExpenseType::Quote, 'spend_date' => null,
            ]);
            $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        }
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());

        return [
            'current' => $this->queries(fn () => app(AnnualEconomicReportQuery::class)->execute(
                $actor,
                $context,
                new EconomicReportFilterData((int) $year->getKey()),
            )),
            'historical' => $this->queries(fn () => app(AnnualEconomicReportQuery::class)->execute(
                $actor,
                $context,
                new EconomicReportFilterData((int) $year->getKey(), asOf: '2026-03-01T10:30:00Z'),
            )),
        ];
    }

    private function queries(callable $operation): int
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $operation();
        $count = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return $count;
    }
}
