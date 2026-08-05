<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreateCostCenter;
use App\Domain\MasterData\Actions\UpdateCostCenter;
use App\Domain\MasterData\Queries\CostCenterSelectorQuery;
use App\Domain\MasterData\Queries\CostCenterTreeQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CostCenterTreeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_create_and_move_enforce_same_tenant_acyclic_three_level_hierarchy(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.update');
        $create = app(CreateCostCenter::class);
        $root = $create->execute($actor, $context, 'Root', null, 'cc-root');
        $child = $create->execute($actor, $context, 'Child', $root, 'cc-child');
        $grandchild = $create->execute($actor, $context, 'Grandchild', $child, 'cc-grandchild');

        try {
            $create->execute($actor, $context, 'Level four', $grandchild, 'cc-level-four');
            $this->fail('A fourth cost-center level was created.');
        } catch (DomainException $exception) {
            $this->assertSame('COST_CENTER_DEPTH_EXCEEDED', $exception->getMessage());
        }

        try {
            app(UpdateCostCenter::class)->execute($actor, $context, $root, 'Root', $grandchild, 1, 'cc-cycle');
            $this->fail('A cost-center cycle was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('COST_CENTER_CYCLE', $exception->getMessage());
        }

        $foreign = CostCenter::factory()->for(Tenant::factory())->create(['name' => 'Foreign parent']);
        try {
            app(UpdateCostCenter::class)->execute($actor, $context, $child, 'Child', $foreign, 1, 'cc-foreign');
            $this->fail('A foreign parent was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('TENANT_RELATION_MISMATCH', $exception->getMessage());
        }

        $this->assertDatabaseHas('cost_centers', ['id' => $root->getKey(), 'parent_id' => null]);
        $this->assertDatabaseHas('cost_centers', ['id' => $child->getKey(), 'parent_id' => $root->getKey()]);
        $this->assertDatabaseHas('cost_centers', ['id' => $grandchild->getKey(), 'parent_id' => $child->getKey()]);
        $this->assertSame($tenant->getKey(), $root->tenant_id);
    }

    public function test_tree_and_selector_are_tenant_scoped_and_order_siblings_case_insensitively_then_by_id(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.view');
        $create = app(CreateCostCenter::class);
        $root = $create->execute($actor, $context, 'Root', null, 'tree-root');
        $beta = $create->execute($actor, $context, 'beta', $root, 'tree-beta');
        $alpha = $create->execute($actor, $context, 'Alpha', $root, 'tree-alpha');
        $zulu = $create->execute($actor, $context, 'Zulu', null, 'tree-zulu');
        $inactive = $create->execute($actor, $context, 'Inactive', null, 'tree-inactive');
        CostCenter::query()->whereKey($inactive->getKey())->update(['active' => false]);
        $foreignInactive = CostCenter::factory()->for(Tenant::factory())->create(['name' => 'Foreign', 'active' => false]);

        $tree = app(CostCenterTreeQuery::class)->forTenant($actor, $context);
        $this->assertSame([$inactive->getKey(), $root->getKey(), $zulu->getKey()], $tree->pluck('id')->all());
        $this->assertSame([$alpha->getKey(), $beta->getKey()], $tree->firstWhere('id', $root->getKey())->children->pluck('id')->all());
        $this->assertSame([$root->getKey(), $alpha->getKey(), $beta->getKey(), $zulu->getKey()], app(CostCenterSelectorQuery::class)
            ->forNewSelection($actor, $context)
            ->pluck('id')
            ->all());
        $this->assertSame([$inactive->getKey()], app(CostCenterSelectorQuery::class)
            ->forRecord($actor, $context, $inactive->getKey())
            ->where('active', false)
            ->pluck('id')
            ->all());
        $this->assertNotContains($foreignInactive->getKey(), app(CostCenterSelectorQuery::class)
            ->forRecord($actor, $context, $foreignInactive->getKey())
            ->pluck('id')
            ->all());
    }

    public function test_tree_and_selector_distinguish_an_inactive_persisted_tenant_from_missing_or_forged_context(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.view', 'cost-center.create');
        $tree = app(CostCenterTreeQuery::class);
        $selector = app(CostCenterSelectorQuery::class);
        Tenant::query()->whereKey($tenant->getKey())->update(['state' => TenantState::Inactive->value]);

        $this->assertAuthorizationCode(fn (): mixed => $tree->forTenant($actor, $context), 'TENANT_INACTIVE');
        $this->assertAuthorizationCode(fn (): mixed => $selector->forNewSelection($actor, $context), 'TENANT_INACTIVE');
        $this->assertAuthorizationCode(
            fn (): CostCenter => app(CreateCostCenter::class)->execute($actor, $context, 'Inactive tenant write', null, 'inactive-tenant-write'),
            'TENANT_INACTIVE',
        );

        $forged = $tenant->replicate();
        $forged->forceFill(['id' => $tenant->getKey() + 100000]);
        $forged->exists = true;
        $forgedContext = new TenantContext($forged, $actor);
        $this->assertAuthorizationCode(fn (): mixed => $tree->forTenant($actor, $forgedContext), 'TENANT_CONTEXT_REQUIRED');
        $this->assertAuthorizationCode(fn (): mixed => $selector->forNewSelection($actor, $forgedContext), 'TENANT_CONTEXT_REQUIRED');
        $this->assertAuthorizationCode(
            fn (): CostCenter => app(CreateCostCenter::class)->execute($actor, $forgedContext, 'Forged tenant write', null, 'forged-tenant-write'),
            'TENANT_CONTEXT_REQUIRED',
        );
    }

    public function test_move_rejects_a_resulting_fourth_level_from_the_targets_existing_subtree_without_side_effects(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.update');
        $create = app(CreateCostCenter::class);
        $destinationRoot = $create->execute($actor, $context, 'Destination root', null, 'move-depth-destination');
        $movingRoot = $create->execute($actor, $context, 'Moving root', null, 'move-depth-root');
        $child = $create->execute($actor, $context, 'Moving child', $movingRoot, 'move-depth-child');
        $create->execute($actor, $context, 'Moving grandchild', $child, 'move-depth-grandchild');
        $versionCount = Version::query()->count();
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        try {
            app(UpdateCostCenter::class)->execute(
                $actor,
                $context,
                $movingRoot,
                'Moving root',
                $destinationRoot,
                1,
                'move-depth-rejected',
            );
            $this->fail('A move producing level four through existing descendants was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('COST_CENTER_DEPTH_EXCEEDED', $exception->getMessage());
        }

        $this->assertDatabaseHas('cost_centers', [
            'id' => $movingRoot->getKey(),
            'parent_id' => null,
            'name' => 'Moving root',
            'lock_version' => 1,
        ]);
        $this->assertDatabaseCount('versions', $versionCount);
        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    /** @return array{Tenant, User, TenantContext} */
    private function context(string ...$abilities): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());

        foreach ($abilities as $ability) {
            $role = Role::query()->firstOrCreate([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cost center tree '.$ability,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($ability);
            $actor->assignRole($role);
        }
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }

    private function assertAuthorizationCode(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Expected authorization code [{$code}].");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }
}
