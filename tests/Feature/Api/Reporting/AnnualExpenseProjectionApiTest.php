<?php

namespace Tests\Feature\Api\Reporting;

use Tests\TestCase;

final class AnnualExpenseProjectionApiTest extends TestCase
{
    public function test_target_reporting_and_dashboard_contracts_accept_planning_year_id_and_return_projection_totals(): void
    {
        $reportController = file_get_contents(base_path('app/Http/Controllers/Api/V1/EconomicReportController.php'));
        $dashboardController = file_get_contents(base_path('app/Http/Controllers/Api/V1/DashboardController.php'));
        $report = file_get_contents(base_path('app/Domain/Reporting/Queries/AnnualEconomicReportQuery.php'));
        $dashboard = file_get_contents(base_path('app/Domain/Reporting/Queries/TenantDashboardQuery.php'));

        $this->assertStringContainsString("query('planning_year_id')", $reportController);
        $this->assertStringContainsString("query('planning_year_id')", $dashboardController);
        foreach ([$report, $dashboard] as $contents) {
            $this->assertStringContainsString('current_planning', $contents);
            $this->assertStringContainsString('actual', $contents);
            $this->assertStringContainsString('AnnualEconomicProjection', $contents);
        }
    }
}
