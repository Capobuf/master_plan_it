<?php

namespace Tests\Feature\Expenses;

use App\Domain\Expenses\Data\ExpenseDetail;
use App\Domain\Expenses\Data\ExpenseRegisterFilterData;
use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Expenses\Queries\ExpenseDetailQuery;
use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpenseRegisterTest extends TestCase
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

    public function test_register_is_a_deterministic_scalar_projection_with_pagination_and_year_options(): void
    {
        [$tenant, $actor, $context] = $this->contextWithView();
        $year2026 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $year2027 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027, 'active' => false]);

        foreach (['Charlie', 'Alpha', 'Bravo'] as $title) {
            $expense = Expense::factory()->for($tenant)->create([
                'planning_year_id' => $year2026->getKey(),
                'title' => $title,
            ]);
            ExpenseRow::factory()->for($expense)->create([
                'net_amount' => '10.00',
                'vat_amount' => '2.20',
                'gross_amount' => '12.20',
            ]);
        }

        $query = app(ExpenseRegisterQuery::class);
        $filters = new ExpenseRegisterFilterData((int) $year2026->getKey());
        $first = $query->paginate($actor, $context, $filters, page: 1, perPage: 2);
        $second = $query->paginate($actor, $context, $filters, page: 2, perPage: 2);

        $this->assertContainsOnlyInstancesOf(ExpenseRegisterRow::class, $first->items());
        $this->assertSame(['Alpha', 'Bravo'], array_map(fn (ExpenseRegisterRow $row): string => $row->title, $first->items()));
        $this->assertSame(['Charlie'], array_map(fn (ExpenseRegisterRow $row): string => $row->title, $second->items()));
        $this->assertSame(3, $first->total());
        $this->assertSame(2, $first->lastPage());
        $this->assertSame([
            ['id' => (int) $year2026->getKey(), 'label' => 2026, 'active' => true],
            ['id' => (int) $year2027->getKey(), 'label' => 2027, 'active' => false],
        ], $query->yearOptions($actor, $context));
    }

    public function test_detail_returns_current_scalar_rows_and_safe_not_found_for_foreign_or_deleted_ids(): void
    {
        [$tenant, $actor, $context] = $this->contextWithView();
        $expense = Expense::factory()->for($tenant)->create(['title' => 'Visible detail']);
        $second = ExpenseRow::factory()->for($expense)->create([
            'position' => 2,
            'description' => 'Second row',
            'net_amount' => '20.00',
            'vat_amount' => '4.40',
            'gross_amount' => '24.40',
        ]);
        $first = ExpenseRow::factory()->for($expense)->create([
            'position' => 1,
            'description' => 'First row',
            'net_amount' => '10.00',
            'vat_amount' => '2.20',
            'gross_amount' => '12.20',
        ]);
        ExpenseRow::factory()->for($expense)->create(['position' => 3])->delete();
        $expense->forceFill(['current_planning_row_id' => $first->getKey()])->saveQuietly();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $detail = app(ExpenseDetailQuery::class)->find($actor, $context, (int) $expense->getKey());
        $sql = strtolower(implode('\n', array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();

        $this->assertInstanceOf(ExpenseDetail::class, $detail);
        $this->assertSame('Visible detail', $detail->title);
        $this->assertSame(['First row', 'Second row'], array_column($detail->rows, 'description'));
        $this->assertSame('10.00', $detail->totals['current_planning']['net']);
        $this->assertSame('2.20', $detail->totals['current_planning']['vat']);
        $this->assertSame('12.20', $detail->totals['current_planning']['gross']);
        $this->assertSame('0.00', $detail->totals['actual']['official']);
        foreach (['versions', 'revision_batches', 'revision_batch_items', 'attachment'] as $futureTable) {
            $this->assertStringNotContainsString($futureTable, $sql);
        }

        $foreign = Expense::factory()->for(Tenant::factory())->create();
        $deleted = Expense::factory()->for($tenant)->create();
        $deleted->delete();

        foreach ([$foreign->getKey(), $deleted->getKey(), 999999] as $hiddenId) {
            try {
                app(ExpenseDetailQuery::class)->find($actor, $context, (int) $hiddenId);
                $this->fail('A protected Expense identifier was disclosed.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_detail_authorizes_collection_before_any_id_dependent_lookup(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $context = new TenantContext($tenant, $actor);
        $expense = Expense::factory()->for($tenant)->create();
        $foreign = Expense::factory()->for(Tenant::factory())->create();
        $deleted = Expense::factory()->for($tenant)->create();
        $deleted->delete();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        foreach ([$expense->getKey(), $foreign->getKey(), $deleted->getKey(), 999999] as $protectedId) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            try {
                app(ExpenseDetailQuery::class)->find($actor, $context, (int) $protectedId);
                $this->fail('Expense detail performed an ID-dependent read without expense.view.');
            } catch (AuthorizationException $exception) {
                $this->assertSame('PERMISSION_DENIED', $exception->getMessage());
            }

            $sql = strtolower(implode('\n', array_column(DB::getQueryLog(), 'query')));
            DB::disableQueryLog();
            $this->assertStringNotContainsString('from `expenses`', $sql);
            $this->assertStringNotContainsString('from `expense_rows`', $sql);
        }
    }

    public function test_register_requires_the_exact_view_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $context = new TenantContext($tenant, $actor);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('PERMISSION_DENIED');

        app(ExpenseRegisterQuery::class)->paginate(
            $actor,
            $context,
            new ExpenseRegisterFilterData(1),
            page: 1,
            perPage: 15,
        );
    }

    /** @return array{Tenant, User, TenantContext} */
    private function contextWithView(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Expense register '.str()->uuid(),
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

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }
}
