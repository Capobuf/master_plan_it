<?php

namespace Tests\Accounting\Integration;

use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ActivateAnnualHistoryAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_tenantless_non_admin_mismatch_and_inactive_member_fail_before_activation(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $tenantless = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $member = $this->actor($tenant);

        $this->assertDenied(fn () => app(ActivateAnnualHistory::class)->execute(
            $tenantless,
            new TenantContext($tenant, $tenantless),
            $year,
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');
        $this->assertDenied(fn () => app(ActivateAnnualHistory::class)->execute(
            $member,
            new TenantContext($foreign, $member),
            $year,
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');

        $tenant->forceFill(['state' => TenantState::Inactive])->save();
        $this->assertDenied(fn () => app(ActivateAnnualHistory::class)->execute(
            $member->refresh(),
            new TenantContext($tenant->refresh(), $member->refresh()),
            $year,
            (string) str()->uuid(),
        ), 'TENANT_INACTIVE');
        $this->assertNull($year->fresh()->history_activated_at);
    }

    public function test_protected_administrator_can_activate_an_inactive_selected_tenant(): void
    {
        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $year = PlanningYear::factory()->for($tenant)->create();
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        $activated = app(ActivateAnnualHistory::class)->execute(
            $administrator,
            new TenantContext($tenant, $administrator),
            $year,
            (string) str()->uuid(),
        );

        $this->assertNotNull($activated->history_activated_at);
    }

    private function actor(Tenant $tenant): User
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Report history '.str()->uuid(), 'guard_name' => 'web',
        ]);
        $role->syncPermissions(['report.view']);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        try {
            $actor->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }

        return $actor;
    }

    /** @param callable(): mixed $operation */
    private function assertDenied(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Authorization unexpectedly succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
