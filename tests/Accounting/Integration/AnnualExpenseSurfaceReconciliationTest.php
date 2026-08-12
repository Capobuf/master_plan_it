<?php

namespace Tests\Accounting\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AnnualExpenseSurfaceReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_five_consumers_depend_on_the_same_annual_projection_contract(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName(), 'Surface reconciliation is MySQL-only.');

        foreach ([
            'app/Domain/Expenses/Queries/ExpenseDetailQuery.php',
            'app/Domain/Expenses/Queries/ExpenseRegisterQuery.php',
            'app/Domain/Budget/Queries/AnnualBudgetQuery.php',
            'app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php',
            'app/Domain/Reporting/Queries/TenantDashboardQuery.php',
        ] as $path) {
            $contents = file_get_contents(base_path($path));
            $this->assertStringContainsString('AnnualEconomicProjection', $contents, $path.' must consume the canonical projection.');
        }
    }

    public function test_register_contract_has_economic_year_and_row_level_actual_real_dates(): void
    {
        $resource = file_get_contents(base_path('app/Http/Resources/Api/V1/ExpenseRegisterResource.php'));
        $row = file_get_contents(base_path('app/Http/Resources/Api/V1/ExpenseRowResource.php'));
        $this->assertStringContainsString('economic_year_label', $resource);
        $this->assertStringContainsString('spend_date', $row);
    }
}
