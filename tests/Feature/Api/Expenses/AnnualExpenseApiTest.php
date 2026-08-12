<?php

namespace Tests\Feature\Api\Expenses;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class AnnualExpenseApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_target_expense_routes_use_planning_year_id_and_expose_preview_without_lifecycle_aliases(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/expenses.php'));
        $this->assertStringContainsString('preview', $routes);
        $this->assertStringNotContainsString('/close', $routes);
        $this->assertStringNotContainsString('/move', $routes);

        $controller = file_get_contents(base_path('app/Http/Controllers/Api/V1/ExpenseController.php'));
        $this->assertStringContainsString('planning_year_id', $controller);
        $this->assertStringNotContainsString('close(', $controller);
        $this->assertStringNotContainsString('move(', $controller);
    }

    public function test_create_and_both_preview_modes_reject_an_inactive_planning_year(): void
    {
        [$tenant, $user, $year, $center, $vendor] = $this->workspace(activeYear: false);
        $payload = $this->payload($year, $center, $vendor);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
        ]);
        $updatePayload = $this->payload($year, $center, $vendor, $expense, $row);
        $this->actingAs($user, 'web');

        foreach ([
            $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload),
            $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/preview', $payload),
            $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/preview', $updatePayload),
        ] as $response) {
            $response->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['fields' => ['planning_year_id']]]);
        }
    }

    public function test_update_preview_rejects_a_stale_root_without_mutation(): void
    {
        [$tenant, $user, $year, $center, $vendor] = $this->workspace();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Persisted title',
            'lock_version' => 4,
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'lock_version' => 3,
        ]);
        $payload = $this->payload($year, $center, $vendor, $expense, $row);
        $payload['lock_version'] = 3;
        $payload['title'] = 'Preview-only title';
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/preview', $payload)
            ->assertConflict()
            ->assertJsonPath('error.code', 'STALE_VERSION');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->getKey(),
            'title' => 'Persisted title',
            'lock_version' => 4,
        ]);
    }

    public function test_missing_and_foreign_row_ids_share_the_same_generic_validation_contract(): void
    {
        [$tenant, $user, $year, $center, $vendor] = $this->workspace();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        $ownedRow = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
        ]);
        $foreignTenant = Tenant::factory()->create();
        $foreignExpense = Expense::factory()->for($foreignTenant)->create();
        $foreignRow = ExpenseRow::factory()->for($foreignExpense)->create([
            'tenant_id' => $foreignTenant->getKey(),
        ]);
        $this->actingAs($user, 'web');

        $errors = [];
        foreach ([(int) $foreignRow->getKey(), 999999999] as $untrustedRowId) {
            $payload = $this->payload($year, $center, $vendor, $expense, $ownedRow);
            $payload['rows'][0]['id'] = $untrustedRowId;
            $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/preview', $payload)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['fields' => ['rows']]]);
            $errors[] = [
                'code' => $response->json('error.code'),
                'message' => $response->json('error.message'),
                'fields' => $response->json('error.fields'),
            ];
        }

        $this->assertSame($errors[0], $errors[1]);
    }

    public function test_generated_expense_missing_and_foreign_row_ids_share_the_same_generic_validation_contract(): void
    {
        [$tenant, $user, $year, $center, $vendor] = $this->workspace();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Generated expense contract',
            'active' => true,
            'lock_version' => 1,
        ]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'contract_id' => $contract->getKey(),
        ]);
        $ownedRow = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'source_key' => 'contract:'.$contract->getKey().':'.$year->getKey(),
        ]);
        $foreignTenant = Tenant::factory()->create();
        $foreignExpense = Expense::factory()->for($foreignTenant)->create();
        $foreignRow = ExpenseRow::factory()->for($foreignExpense)->create([
            'tenant_id' => $foreignTenant->getKey(),
        ]);
        $this->actingAs($user, 'web');

        $errors = [];
        foreach ([(int) $foreignRow->getKey(), 999999999] as $untrustedRowId) {
            $payload = $this->payload($year, $center, $vendor, $expense, $ownedRow);
            unset($payload['expense_id']);
            $payload['contract_id'] = $contract->getKey();
            $payload['rows'][0]['id'] = $untrustedRowId;
            $response = $this->withHeaders($this->csrfHeaders())
                ->putJson('/api/v1/expenses/'.$expense->getKey(), $payload)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['fields' => ['rows']]]);
            $errors[] = [
                'code' => $response->json('error.code'),
                'message' => $response->json('error.message'),
                'fields' => $response->json('error.fields'),
            ];
        }

        $this->assertSame($errors[0], $errors[1]);
        $this->assertDatabaseHas('expense_rows', [
            'id' => $ownedRow->getKey(),
            'expense_id' => $expense->getKey(),
        ]);
    }

    public function test_expense_reads_fail_closed_when_related_view_abilities_are_missing(): void
    {
        [$tenant, , $year, $center] = $this->workspace();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        $user = User::factory()->for($tenant)->create();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Expense-only reader '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['expense.view']);
        $user->assignRole($role);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses?planning_year_id='.$year->getKey())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
        $this->getJson('/api/v1/expenses/'.$expense->getKey().'?planning_year_id='.$year->getKey())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_existing_rows_require_an_explicit_vat_rate(): void
    {
        [$tenant, $user, $year, $center, $vendor] = $this->workspace();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
        ]);
        $payload = $this->payload($year, $center, $vendor, $expense, $row);
        unset($payload['rows'][0]['vat_rate']);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/preview', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['rows.0.vat_rate']]]);
    }

    public function test_protected_administrator_can_read_project_and_contract_dimensions_in_an_inactive_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'title' => 'Inactive Tenant contract',
            'active' => true,
            'lock_version' => 1,
        ]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'contract_id' => $contract->getKey(),
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
        ]);
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')
            ->assertOk();
        $tenant->update(['state' => 'inactive']);

        $this->getJson('/api/v1/expenses/'.$expense->getKey().'?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.project_id', $project->getKey())
            ->assertJsonPath('data.contract_id', $contract->getKey());
    }

    /** @return array{Tenant, User, PlanningYear, CostCenter, Vendor} */
    private function workspace(bool $activeYear = true): array
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['active' => $activeYear]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        return [$tenant, $user, $year, $center, $vendor];
    }

    /** @return array<string, mixed> */
    private function payload(
        PlanningYear $year,
        CostCenter $center,
        Vendor $vendor,
        ?Expense $expense = null,
        ?ExpenseRow $row = null,
    ): array {
        $payload = [
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => 'ordinary',
            'title' => 'Annual expense API',
            'notes' => null,
            'project_id' => null,
            'contract_id' => null,
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendor->getKey(),
                'type' => ExpenseType::Estimate->value,
                'is_current_planning' => true,
                'description' => 'Informative estimate',
                'notes' => null,
                'entered_amount' => '100.00',
                'amount_includes_vat' => false,
                'vat_rate' => '22.00',
                'spend_date' => null,
                'external_reference' => null,
            ]],
        ];

        if ($expense instanceof Expense && $row instanceof ExpenseRow) {
            $payload['expense_id'] = $expense->getKey();
            $payload['lock_version'] = $expense->lock_version;
            $payload['rows'][0]['id'] = $row->getKey();
            $payload['rows'][0]['lock_version'] = $row->lock_version;
        }

        return $payload;
    }
}
