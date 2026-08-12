<?php

namespace Tests\Accounting\Integration;

use App\Domain\Expenses\Data\ExpenseRegisterFilterData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CurrentExpenseDatasetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionCatalogueSeeder::class)->run();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_current_dataset_uses_only_current_non_deleted_rows_for_one_tenant_and_year(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => BudgetBasis::Gross]);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $otherYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $actor = User::factory()->for($tenant)->create();
        $this->grantView($actor, $tenant);
        $context = new TenantContext($tenant, $actor);

        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'title' => 'Current exact expense',
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'position' => 1,
            'type' => ExpenseType::Quote,
            'spend_date' => null,
            'net_amount' => '100.10',
            'vat_amount' => '22.02',
            'gross_amount' => '122.12',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'position' => 2,
            'type' => ExpenseType::Actual,
            'spend_date' => '2027-01-05',
            'net_amount' => '-0.10',
            'vat_amount' => '-0.02',
            'gross_amount' => '-0.12',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'position' => 3,
            'net_amount' => '9000.00',
            'vat_amount' => '1980.00',
            'gross_amount' => '10980.00',
        ])->delete();

        $row->forceFill([
            'net_amount' => '101.10',
            'vat_amount' => '22.22',
            'gross_amount' => '123.32',
        ])->save();
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        $deletedExpense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'title' => 'Deleted expense',
        ]);
        ExpenseRow::factory()->for($deletedExpense)->create([
            'net_amount' => '8000.00',
            'vat_amount' => '1760.00',
            'gross_amount' => '9760.00',
        ]);
        $deletedExpense->delete();

        $futureExpense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $otherYear->getKey(),
            'title' => 'Other year',
        ]);
        ExpenseRow::factory()->for($futureExpense)->create([
            'net_amount' => '7000.00',
            'vat_amount' => '1540.00',
            'gross_amount' => '8540.00',
        ]);

        $otherTenant = Tenant::factory()->create();
        $foreignExpense = Expense::factory()->for($otherTenant)->create(['title' => 'Foreign expense']);
        ExpenseRow::factory()->for($foreignExpense)->create([
            'net_amount' => '6000.00',
            'vat_amount' => '1320.00',
            'gross_amount' => '7320.00',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $query = app(ExpenseRegisterQuery::class);
        $filters = new ExpenseRegisterFilterData(planningYearId: (int) $year->getKey());
        $page = $query->paginate($actor, $context, $filters, page: 1, perPage: 15);
        $totals = $query->totals($actor, $context, $filters);
        $sql = strtolower(implode('\n', array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();

        $this->assertSame(1, $page->total());
        $this->assertSame('Current exact expense', $page->items()[0]->title);
        $this->assertSame(2, $page->items()[0]->rowCount);
        $this->assertSame([
            'net' => '101.10',
            'vat' => '22.22',
            'gross' => '123.32',
            'official' => '123.32',
        ], $page->items()[0]->totals['current_planning']);
        $this->assertSame([
            'net' => '-0.10',
            'vat' => '-0.02',
            'gross' => '-0.12',
            'official' => '-0.12',
        ], $page->items()[0]->totals['actual']);
        $this->assertSame([
            'current_planning' => [
                'net' => '101.10',
                'vat' => '22.22',
                'gross' => '123.32',
                'official' => '123.32',
            ],
            'actual' => [
                'net' => '-0.10',
                'vat' => '-0.02',
                'gross' => '-0.12',
                'official' => '-0.12',
            ],
        ], $totals);
        $this->assertStringNotContainsString('versions', $sql);
        $this->assertStringNotContainsString('audit_events', $sql);
        $this->assertStringNotContainsString('revision_', $sql);
        $this->assertStringNotContainsString('attachment', $sql);
    }

    private function grantView(User $actor, Tenant $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Current expense dataset '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions([
            'expense.view',
            'planning-year.view',
            'cost-center.view',
            'vendor.view',
        ]);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }
}
