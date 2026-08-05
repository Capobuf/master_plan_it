<?php

namespace Tests\Livewire\Expenses;

use App\Domain\Expenses\Queries\ExpenseRegisterQuery;
use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Livewire\Expenses\ExpenseIndex;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\LivewireManager;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpenseRegisterPageTest extends TestCase
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

    public function test_operational_register_renders_context_year_filter_exact_totals_and_shared_states(): void
    {
        [$tenant, $actor, $context] = $this->contextWithView();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'title' => 'Office services',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
        ]);

        $this->actingAs($actor)
            ->get(route('operational.expenses.index'))
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee('Office services')
            ->assertSee('100.00')
            ->assertSee('22.00')
            ->assertSee('122.00')
            ->assertSee('aria-busy', false)
            ->assertSee('wire:loading', false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertDontSee('attachment')
            ->assertDontSee('revision history');

        $this->actingAs($actor)
            ->get(route('operational.expenses.show', ['expense' => $expense->getKey()]))
            ->assertOk()
            ->assertSee('Office services')
            ->assertSee('100.00')
            ->assertSee('22.00')
            ->assertSee('122.00');

        $this->app->instance(TenantContext::class, $context);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $this->actingAs($actor);
        app(LivewireManager::class)->test(new ExpenseIndex)
            ->set('planningYearId', (int) $year->getKey())
            ->call('applyFilters')
            ->assertSet('totals.net', '100.00')
            ->assertSee('Office services');
    }

    public function test_empty_register_keeps_valid_zero_totals_and_has_no_dead_create_link(): void
    {
        [$tenant, $actor, $context] = $this->contextWithView();
        $this->app->instance(TenantContext::class, $context);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        $this->actingAs($actor);
        app(LivewireManager::class)->test(new ExpenseIndex)
            ->assertSee('0.00')
            ->assertSee('Nessuna spesa corrente')
            ->assertDontSee('Create expense')
            ->assertDontSee('/operational/expenses/create');
    }

    public function test_zero_row_detail_has_exact_zero_totals_and_a_guided_empty_state(): void
    {
        [$tenant, $actor] = $this->contextWithView();
        $expense = Expense::factory()->for($tenant)->create(['title' => 'Header awaiting rows']);

        $this->actingAs($actor)
            ->get(route('operational.expenses.show', ['expense' => $expense->getKey()]))
            ->assertOk()
            ->assertSee('Header awaiting rows')
            ->assertSee('0.00')
            ->assertSee('Nessuna riga corrente')
            ->assertSee('Torna al registro spese')
            ->assertSee('data-state="empty"', false)
            ->assertDontSee('<table', false);
    }

    public function test_permission_aware_navigation_and_home_module_state_are_reachable_and_accurate(): void
    {
        $expenseOnlyTenant = Tenant::factory()->create();
        $expenseOnly = User::factory()->for($expenseOnlyTenant)->create();
        $this->grantAbilities($expenseOnly, $expenseOnlyTenant, ['expense.view']);

        $this->actingAs($expenseOnly)
            ->get(route('operational.expenses.index'))
            ->assertOk()
            ->assertSee('href="'.route('operational.expenses.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('href="'.route('operational.index').'"', false);

        $fullTenant = Tenant::factory()->create();
        $fullActor = User::factory()->for($fullTenant)->create();
        $this->grantAbilities($fullActor, $fullTenant, ['dashboard.view', 'expense.view']);

        $this->actingAs($fullActor)
            ->get(route('operational.index'))
            ->assertOk()
            ->assertSee('href="'.route('operational.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Registro spese correnti')
            ->assertSee('href="'.route('operational.expenses.index').'"', false)
            ->assertDontSee('Nessun modulo operativo disponibile');

        $dashboardTenant = Tenant::factory()->create();
        $dashboardOnly = User::factory()->for($dashboardTenant)->create();
        $this->grantAbilities($dashboardOnly, $dashboardTenant, ['dashboard.view']);

        $this->actingAs($dashboardOnly)
            ->get(route('operational.index'))
            ->assertOk()
            ->assertSee('Nessun modulo operativo disponibile')
            ->assertDontSee('href="'.route('operational.expenses.index').'"', false);
    }

    public function test_platform_administrator_can_open_the_operational_workspace_for_the_selected_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        $this->withSession([EnterTenantContext::SESSION_KEY => (int) $tenant->getKey()])
            ->actingAs($administrator)
            ->get(route('operational.index'))
            ->assertOk()
            ->assertSee('Registro spese correnti');

        $this->withSession([EnterTenantContext::SESSION_KEY => (int) $tenant->getKey()])
            ->actingAs($administrator)
            ->get(route('operational.expenses.index'))
            ->assertOk()
            ->assertSee('Registro spese');
    }

    public function test_runtime_denied_and_unexpected_states_are_safe_and_correlated(): void
    {
        $deniedTenant = Tenant::factory()->create();
        $deniedActor = User::factory()->for($deniedTenant)->create();
        $deniedContext = new TenantContext($deniedTenant, $deniedActor);
        $this->app->instance(TenantContext::class, $deniedContext);
        app(PermissionRegistrar::class)->setPermissionsTeamId($deniedTenant->getKey());
        $this->actingAs($deniedActor);

        app(LivewireManager::class)->test(new ExpenseIndex)
            ->assertSet('errorCode', 'PERMISSION_DENIED')
            ->assertSet('correlationId', null)
            ->assertSee('This item is unavailable.');

        [$tenant, $actor, $context] = $this->contextWithView();
        request()->attributes->remove(CorrelationId::class);
        $this->app->instance(TenantContext::class, $context);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $this->app->bind(ExpenseRegisterQuery::class, static function (): never {
            throw new RuntimeException('protected internal detail');
        });
        $this->actingAs($actor);

        app(LivewireManager::class)->test(new ExpenseIndex)
            ->assertSet('errorCode', 'UNEXPECTED_ERROR')
            ->assertSet('correlationId', fn (mixed $value): bool => is_string($value) && str()->isUuid($value, 4))
            ->assertSee('Something went wrong. Please try again.')
            ->assertSee('Correlation ID:')
            ->assertDontSee('protected internal detail');
    }

    public function test_every_register_control_locks_during_any_livewire_request(): void
    {
        $view = (string) file_get_contents(resource_path('views/livewire/expenses/expense-index.blade.php'));

        $this->assertStringContainsString('<fieldset wire:loading.attr="disabled"', $view);
        $this->assertMatchesRegularExpression('/<button[^>]+wire:click="previousPage"[^>]+wire:loading\.attr="disabled"/s', $view);
        $this->assertMatchesRegularExpression('/<button[^>]+wire:click="nextPage"[^>]+wire:loading\.attr="disabled"/s', $view);
        $this->assertMatchesRegularExpression('/wire:click="reloadRegister"[^>]+wire:loading\.attr="disabled"/s', $view);
        $this->assertStringNotContainsString('wire:loading.attr="disabled" wire:target=', $view);
        $this->assertStringContainsString('wire:loading.attr="aria-busy"', $view);
    }

    public function test_direct_routes_fail_closed_without_permission_and_for_foreign_expense_ids(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();

        $this->actingAs($actor)
            ->get('/operational/expenses')
            ->assertForbidden();

        $this->grantView($actor, $tenant);
        $foreign = Expense::factory()->for(Tenant::factory())->create(['title' => 'Protected foreign title']);

        $this->actingAs($actor)
            ->get('/operational/expenses/'.$foreign->getKey())
            ->assertNotFound()
            ->assertDontSee('Protected foreign title');
    }

    /** @return array{Tenant, User, TenantContext} */
    private function contextWithView(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $this->grantView($actor, $tenant);

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }

    private function grantView(User $actor, Tenant $tenant): void
    {
        $this->grantAbilities($actor, $tenant, ['expense.view']);
    }

    /** @param list<string> $abilities */
    private function grantAbilities(User $actor, Tenant $tenant, array $abilities): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Expense register page '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
        $registrar->forgetCachedPermissions();
    }
}
