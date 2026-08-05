<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreatePlanningYear;
use App\Domain\MasterData\Actions\DeactivatePlanningYear;
use App\Domain\MasterData\Actions\ReactivatePlanningYear;
use App\Domain\MasterData\Data\CreatePlanningYearData;
use App\Domain\MasterData\Queries\PlanningYearListQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PlanningYearPolicy;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlanningYearTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_create_persists_one_active_tenant_scoped_calendar_year_and_audit_event(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.create');

        $planningYear = app(CreatePlanningYear::class)->execute(
            $actor,
            $context,
            new CreatePlanningYearData(2026),
            'planning-year-create-correlation',
        );

        $this->assertSame((int) $tenant->getKey(), (int) $planningYear->tenant_id);
        $this->assertSame(2026, $planningYear->year_label);
        $this->assertTrue($planningYear->active);
        $this->assertSame(1, $planningYear->lock_version);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $actor->getKey(),
            'event_type' => 'planning-year.created',
            'subject_type' => $planningYear->getMorphClass(),
            'subject_id' => $planningYear->getKey(),
            'correlation_id' => 'planning-year-create-correlation',
        ]);
    }

    public function test_create_rejects_duplicate_calendar_year_only_inside_the_same_tenant(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.create');
        app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2026), 'first');

        try {
            app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2026), 'duplicate');
            $this->fail('A duplicate tenant calendar year was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('year_label', $exception->errors());
        }

        $otherTenant = Tenant::factory()->create();
        $otherActor = User::factory()->create(['tenant_id' => $otherTenant->getKey()]);
        $this->grant($otherActor, $otherTenant, 'planning-year.create');
        $otherPlanningYear = app(CreatePlanningYear::class)->execute(
            $otherActor,
            new TenantContext($otherTenant, $otherActor),
            new CreatePlanningYearData(2026),
            'other-tenant',
        );

        $this->assertSame((int) $otherTenant->getKey(), (int) $otherPlanningYear->tenant_id);
    }

    public function test_create_requires_the_exact_ability_and_never_uses_role_name_as_authorization(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->grant($actor, $tenant, 'planning-year.view', 'Calendar manager');

        $this->expectException(AuthorizationException::class);

        app(CreatePlanningYear::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            new CreatePlanningYearData(2026),
            'permission-denied',
        );
    }

    public function test_deactivation_and_reactivation_preserve_history_increment_lock_and_are_audited(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext(
            'planning-year.create',
            'planning-year.deactivate',
            'planning-year.reactivate',
        );
        $planningYear = app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2026), 'created');

        $deactivated = app(DeactivatePlanningYear::class)->execute(
            $actor,
            $context,
            $planningYear,
            1,
            'planning-year-deactivate-correlation',
        );

        $this->assertFalse($deactivated->active);
        $this->assertSame(2, $deactivated->lock_version);
        $this->assertDatabaseHas('planning_years', ['id' => $planningYear->getKey(), 'active' => false]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'event_type' => 'planning-year.deactivated',
            'subject_id' => $planningYear->getKey(),
            'correlation_id' => 'planning-year-deactivate-correlation',
        ]);

        $reactivated = app(ReactivatePlanningYear::class)->execute(
            $actor,
            $context,
            $deactivated,
            2,
            'planning-year-reactivate-correlation',
        );

        $this->assertTrue($reactivated->active);
        $this->assertSame(3, $reactivated->lock_version);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'event_type' => 'planning-year.reactivated',
            'subject_id' => $planningYear->getKey(),
            'correlation_id' => 'planning-year-reactivate-correlation',
        ]);
    }

    public function test_lifecycle_rejects_stale_lock_versions_without_overwriting_the_current_state(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.create', 'planning-year.deactivate');
        $planningYear = app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2026), 'created');
        PlanningYear::query()->whereKey($planningYear->getKey())->update(['lock_version' => 2]);

        try {
            app(DeactivatePlanningYear::class)->execute($actor, $context, $planningYear, 1, 'stale');
            $this->fail('A stale lifecycle request overwrote the planning year.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }

        $this->assertDatabaseHas('planning_years', [
            'id' => $planningYear->getKey(),
            'active' => true,
            'lock_version' => 2,
        ]);
    }

    public function test_list_and_selector_are_tenant_scoped_ordered_and_exclude_inactive_years_from_new_selection(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext(
            'planning-year.create',
            'planning-year.view',
            'planning-year.deactivate',
        );
        $first = app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2024), 'first');
        $currentInactive = app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2025), 'current');
        $active = app(CreatePlanningYear::class)->execute($actor, $context, new CreatePlanningYearData(2026), 'active');
        app(DeactivatePlanningYear::class)->execute($actor, $context, $first, 1, 'deactivate-first');
        app(DeactivatePlanningYear::class)->execute($actor, $context, $currentInactive, 1, 'deactivate-current');
        $foreignInactive = PlanningYear::factory()->for(Tenant::factory())->create([
            'year_label' => 2023,
            'active' => false,
        ]);

        $query = app(PlanningYearListQuery::class);
        $this->assertSame([2024, 2025, 2026], $query->forTenant($actor, $context)->pluck('year_label')->all());
        $this->assertSame([2026], $query->forNewSelection($actor, $context)->pluck('year_label')->all());
        $this->assertSame(
            [2025, 2026],
            $query->forNewSelection($actor, $context, (int) $currentInactive->getKey())->pluck('year_label')->all(),
        );
        $this->assertSame(
            [2026],
            $query->forNewSelection($actor, $context, (int) $foreignInactive->getKey())->pluck('year_label')->all(),
        );
        $this->assertSame(2026, $active->year_label);
    }

    public function test_policy_and_list_query_reject_a_persisted_inactive_tenant_with_the_stable_code(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.view');
        $planningYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->app->instance(TenantContext::class, $context);
        Tenant::query()->whereKey($tenant->getKey())->update(['state' => TenantState::Inactive->value]);

        $recordDecision = app(PlanningYearPolicy::class)->view($actor, $planningYear);
        $this->assertTrue($recordDecision->denied());
        $this->assertSame('TENANT_INACTIVE', $recordDecision->message());

        try {
            app(PlanningYearListQuery::class)->forTenant($actor, $context)->get();
            $this->fail('An inactive persisted tenant exposed the Planning Year register.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_INACTIVE', $exception->getMessage());
        }
    }

    public function test_create_planning_year_audit_failure_rolls_back_the_new_year(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.create');
        $correlationId = 'planning-year-create-rollback';
        $beforeCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(CreatePlanningYear::class);
            $action->execute($actor, $context, new CreatePlanningYearData(2027), $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertDatabaseMissing('planning_years', [
                'tenant_id' => $tenant->getKey(),
                'year_label' => 2027,
            ]);
            $this->assertDatabaseCount('audit_events', $beforeCount);
        }
    }

    public function test_deactivate_planning_year_audit_failure_rolls_back_state_and_lock_version(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.deactivate');
        $planningYear = PlanningYear::factory()->for($tenant)->create([
            'year_label' => 2027,
            'active' => true,
            'lock_version' => 4,
        ]);
        $correlationId = 'planning-year-deactivate-rollback';
        $beforeCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeactivatePlanningYear::class);
            $action->execute($actor, $context, $planningYear, 4, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertDatabaseHas('planning_years', [
                'id' => $planningYear->getKey(),
                'active' => true,
                'lock_version' => 4,
            ]);
            $this->assertDatabaseCount('audit_events', $beforeCount);
        }
    }

    public function test_reactivate_planning_year_audit_failure_rolls_back_state_and_lock_version(): void
    {
        [$tenant, $actor, $context] = $this->authorizedContext('planning-year.reactivate');
        $planningYear = PlanningYear::factory()->for($tenant)->create([
            'year_label' => 2027,
            'active' => false,
            'lock_version' => 6,
        ]);
        $correlationId = 'planning-year-reactivate-rollback';
        $beforeCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ReactivatePlanningYear::class);
            $action->execute($actor, $context, $planningYear, 6, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertDatabaseHas('planning_years', [
                'id' => $planningYear->getKey(),
                'active' => false,
                'lock_version' => 6,
            ]);
            $this->assertDatabaseCount('audit_events', $beforeCount);
        }
    }

    public function test_planning_year_has_no_update_delete_or_revision_domain_surface(): void
    {
        foreach ([
            'App\\Domain\\MasterData\\Actions\\UpdatePlanningYear',
            'App\\Domain\\MasterData\\Actions\\DeletePlanningYear',
            'App\\Domain\\MasterData\\Actions\\RestorePlanningYearRevision',
        ] as $forbiddenClass) {
            $this->assertFalse(class_exists($forbiddenClass), "Planning years must not expose [{$forbiddenClass}].");
        }
    }

    /** @return array{Tenant, User, TenantContext} */
    private function authorizedContext(string ...$abilities): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        foreach ($abilities as $ability) {
            $this->grant($actor, $tenant, $ability);
        }

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }

    private function grant(User $actor, Tenant $tenant, string $ability): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $role = Role::query()->firstOrCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Planning year '.$ability,
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($ability);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    /** @return array{Dispatcher, string, array<int, mixed>} */
    private function auditCreatingListeners(): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;

        return [$dispatcher, $eventName, $dispatcher->getRawListeners()[$eventName] ?? []];
    }

    /** @param array<int, mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);

        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
