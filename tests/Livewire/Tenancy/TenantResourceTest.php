<?php

namespace Tests\Livewire\Tenancy;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Filament\Actions\EnterTenantAction;
use App\Filament\Components\TenantContextIndicator;
use App\Filament\Resources\Tenants\Pages\CreateTenant as CreateTenantPage;
use App\Filament\Resources\Tenants\Pages\EditTenant as EditTenantPage;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use DomainException;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Panel::make()->id('tenant-resource-test'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_policy_maps_only_the_exact_global_tenant_abilities_and_never_deletion(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $policy = app(TenantPolicy::class);

        $this->assertTrue($policy->viewAny($administrator));
        $this->assertTrue($policy->view($administrator, $tenant));
        $this->assertTrue($policy->create($administrator));
        $this->assertTrue($policy->update($administrator, $tenant));
        $this->assertTrue($policy->deactivate($administrator, $tenant));
        $this->assertTrue($policy->reactivate($administrator, $tenant));
        $this->assertFalse($policy->delete($administrator, $tenant));
        $this->assertFalse($policy->deleteAny($administrator));
        $this->assertFalse($policy->forceDelete($administrator, $tenant));
        $this->assertFalse($policy->restore($administrator, $tenant));

        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->assertFalse($policy->viewAny($tenantUser));
        $this->assertFalse($policy->create($tenantUser));
        $this->assertFalse($policy->update($tenantUser, $tenant));
        $this->assertFalse($policy->deactivate($tenantUser, $tenant));
        $this->assertFalse($policy->reactivate($tenantUser, $tenant));
    }

    public function test_global_tenant_query_and_list_page_are_administrator_only(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $this->actingAs($tenantUser);

        try {
            TenantResource::getEloquentQuery();
            $this->fail('A tenant user queried the global tenant registry.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This action is unauthorized.', $exception->getMessage());
        }

        try {
            $this->invokePageHandler(new ListTenants, 'authorizeAccess');
            $this->fail('A tenant user opened the global tenant list page.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This action is unauthorized.', $exception->getMessage());
        }

        $administrator = $this->administrator();
        $secondTenant = Tenant::factory()->create();
        $this->actingAs($administrator);

        $this->assertEqualsCanonicalizing(
            [$tenant->getKey(), $secondTenant->getKey()],
            TenantResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    public function test_create_and_edit_pages_route_all_six_fields_through_domain_actions(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator);

        $created = $this->invokePageHandler(new CreateTenantPage, 'handleRecordCreation', [
            'name' => 'Resource-created tenant',
            'code' => 'resource-created',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.000000',
        ]);

        $this->assertInstanceOf(Tenant::class, $created);
        $this->assertDatabaseHas('tenants', [
            'id' => $created->getKey(),
            'created_by_user_id' => $administrator->getKey(),
        ]);
        $this->assertSame(['Editor', 'Viewer'], Role::query()
            ->where('tenant_id', $created->getKey())
            ->orderBy('name')
            ->pluck('name')
            ->all());
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $created->getKey(),
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.created',
            'subject_type' => $created->getMorphClass(),
            'subject_id' => $created->getKey(),
        ]);

        $updated = $this->invokePageHandler(new EditTenantPage, 'handleRecordUpdate', $created, [
            'name' => 'Resource-updated tenant',
            'code' => 'resource-updated',
            'currency_code' => 'USD',
            'language_code' => 'en',
            'timezone' => 'UTC',
            'default_vat_rate' => '7.250000',
        ]);

        $this->assertInstanceOf(Tenant::class, $updated);
        $this->assertSame('Resource-updated tenant', $updated->name);
        $this->assertSame('resource-updated', $updated->code);
        $this->assertSame('USD', $updated->currency_code);
        $this->assertSame('en', $updated->language_code);
        $this->assertSame('UTC', $updated->timezone);
        $this->assertSame('7.250000', $updated->default_vat_rate);
        $this->assertSame(2, $updated->lock_version);
    }

    public function test_lifecycle_actions_delegate_deactivation_and_reactivation_to_the_domain_actions(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['code' => 'EXACT-CODE']);
        $this->actingAs($administrator);

        $table = TenantResource::table(Table::make(app(ListTenants::class)));
        $action = $table->getAction('deactivate');

        $this->assertNotNull($action);
        $this->assertTrue($action->isConfirmationRequired());

        $schema = $action->getSchema(Schema::make(app(ListTenants::class)));
        $this->assertNotNull($schema);
        $this->assertSame(['confirmation_token'], array_map(
            static fn ($component): string => $component->getName(),
            $schema->getComponents(),
        ));

        $handler = $action->getActionFunction();
        $this->assertNotNull($handler);

        try {
            $handler($tenant, ['confirmation_token' => 'exact-code']);
            $this->fail('A case-changed confirmation token deactivated the tenant.');
        } catch (DomainException $exception) {
            $this->assertSame('DESTRUCTIVE_CONFIRMATION_REQUIRED', $exception->getMessage());
        }

        $tenant->refresh();
        $this->assertSame(TenantState::Active, $tenant->state);

        $handler($tenant, ['confirmation_token' => 'EXACT-CODE']);

        $tenant->refresh();
        $this->assertSame(TenantState::Inactive, $tenant->state);

        $reactivate = $table->getAction('reactivate');
        $this->assertNotNull($reactivate);
        $reactivateHandler = $reactivate->getActionFunction();
        $this->assertNotNull($reactivateHandler);

        $reactivateHandler($tenant);

        $tenant->refresh();
        $this->assertSame(TenantState::Active, $tenant->state);
    }

    public function test_enter_tenant_action_delegates_with_the_request_session_correlation_and_identity(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $this->actingAs($administrator);

        $session = $this->app['session.store'];
        $session->forget('tenant_context.tenant_id');
        request()->setLaravelSession($session);
        $correlationId = app(CorrelationId::class)->value();
        $handler = EnterTenantAction::make()->getActionFunction();

        $this->assertNotNull($handler);
        $handler($tenant);

        $this->assertSame($tenant->getKey(), $session->get('tenant_context.tenant_id'));
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.context.entered',
            'correlation_id' => $correlationId,
            'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->getKey(),
        ]);
    }

    public function test_context_indicator_exposes_only_request_derived_tenant_display_data(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'name' => 'Context tenant',
            'code' => 'CTX-01',
            'state' => TenantState::Inactive,
        ]);
        $request = Request::create('/');
        $request->attributes->set(TenantContext::class, new TenantContext($tenant, $administrator));

        $indicator = TenantContextIndicator::fromRequest($request);

        $this->assertTrue($indicator->isSelected());
        $this->assertSame($tenant->getKey(), $indicator->tenantId());
        $this->assertSame('Context tenant', $indicator->tenantName());
        $this->assertSame('CTX-01', $indicator->tenantCode());
        $this->assertSame('inactive', $indicator->tenantState());
        $this->assertSame('Context tenant (CTX-01)', $indicator->label());
        $this->assertSame([
            'selected' => true,
            'tenant_id' => $tenant->getKey(),
            'name' => 'Context tenant',
            'code' => 'CTX-01',
            'state' => 'inactive',
            'label' => 'Context tenant (CTX-01)',
        ], $indicator->toArray());

        $emptyIndicator = TenantContextIndicator::fromRequest(Request::create('/'));
        $this->assertFalse($emptyIndicator->isSelected());
        $this->assertSame('No tenant selected', $emptyIndicator->label());
    }

    private function administrator(): User
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionCatalogueSeeder', '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    private function invokePageHandler(object $page, string $method, mixed ...$arguments): mixed
    {
        $handler = new ReflectionMethod($page, $method);

        return $handler->invoke($page, ...$arguments);
    }
}
