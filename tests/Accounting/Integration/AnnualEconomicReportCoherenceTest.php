<?php

namespace Tests\Accounting\Integration;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Reporting\Queries\AnnualEconomicReportQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AnnualEconomicReportCoherenceTest extends TestCase
{
    public function test_current_report_never_mixes_projection_and_later_committed_labels(): void
    {
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->create(['cost_center_id' => $center->getKey(), 'title' => 'Snapshot project']);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'project_id' => $project->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'vendor_id' => null,
            'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        config(['database.connections.budget_report_writer' => config('database.connections.mysql')]);
        DB::purge('budget_report_writer');
        $updated = false;
        DB::listen(function ($query) use (&$updated, $project): void {
            if ($updated || ! str_contains($query->sql, 'from `expense_rows`')) {
                return;
            }
            $updated = true;
            DB::connection('budget_report_writer')->table('projects')->where('id', $project->getKey())
                ->update(['title' => 'Concurrent project']);
        });

        try {
            $report = app(AnnualEconomicReportQuery::class)->execute(
                $actor,
                new TenantContext($tenant, $actor),
                new EconomicReportFilterData((int) $year->getKey(), groupBy: 'project'),
            );

            $this->assertTrue($updated);
            $this->assertSame('Snapshot project', $report['data'][0]['label']);
            $this->assertSame('Concurrent project', DB::connection('budget_report_writer')->table('projects')->where('id', $project->getKey())->value('title'));
        } finally {
            DB::connection('budget_report_writer')->disconnect();
            DB::table('expenses')->where('id', $expense->getKey())->update(['current_planning_row_id' => null]);
            DB::table('expense_rows')->where('id', $row->getKey())->delete();
            DB::table('expenses')->where('id', $expense->getKey())->delete();
            DB::table('projects')->where('id', $project->getKey())->delete();
            DB::table('cost_centers')->where('id', $center->getKey())->delete();
            DB::table('planning_years')->where('id', $year->getKey())->delete();
            DB::table('model_has_roles')->where('model_id', $actor->getKey())->delete();
            DB::table('users')->where('id', $actor->getKey())->delete();
            DB::table('tenants')->where('id', $tenant->getKey())->delete();
        }
    }
}
