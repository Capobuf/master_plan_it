<?php

namespace Tests\Feature\Projects;

use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\DeleteProject;
use App\Domain\Projects\Actions\PromoteDeferredProjects;
use App\Domain\Projects\Actions\RestoreProjectRevision;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Data\SaveProjectData;
use App\Domain\Projects\Enums\ProjectStage;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ProjectActionRollbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_create_project_audit_failure_rolls_back_project_revision_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        $center = CostCenter::factory()->for($context->tenant)->create();
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners(
            static fn (AuditEvent $event): bool => $event->correlation_id === $correlationId,
            static fn (): never => throw new RuntimeException('forced audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(CreateProject::class));
            $action = app(CreateProject::class);
            $action->execute($actor, $context, new SaveProjectData('Rollback create', (int) $center->getKey(), ProjectStage::Idea, null, null), $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('projects', ['tenant_id' => $context->tenantId, 'title' => 'Rollback create']);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_update_project_audit_failure_rolls_back_snapshot_lock_revision_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$project, $center] = $this->project($actor, $context);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners(
            static fn (AuditEvent $event): bool => $event->correlation_id === $correlationId,
            static fn (): never => throw new RuntimeException('forced audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(UpdateProject::class));
            $action = app(UpdateProject::class);
            $action->execute($actor, $context, $project, new SaveProjectData('Rollback update', (int) $center->getKey(), ProjectStage::Approved, null, 1), $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'title' => 'Original project', 'lock_version' => 1]);
            $this->assertDatabaseMissing('projects', ['id' => $project->getKey(), 'title' => 'Rollback update']);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_delete_project_audit_failure_restores_live_record_revision_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$project] = $this->project($actor, $context);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners(
            static fn (AuditEvent $event): bool => $event->correlation_id === $correlationId,
            static fn (): never => throw new RuntimeException('forced audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(DeleteProject::class));
            $action = app(DeleteProject::class);
            $action->execute($actor, $context, $project, 1, null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'deleted_at' => null, 'lock_version' => 1]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_restore_project_audit_failure_preserves_current_snapshot_source_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        [$project, $center] = $this->project($actor, $context);
        $source = RevisionBatch::query()
            ->where('tenant_id', $context->tenant->getKey())
            ->where('root_subject_type', $project->getMorphClass())
            ->where('root_subject_id', $project->getKey())
            ->latest('id')
            ->firstOrFail();
        self::assertInstanceOf(RevisionBatch::class, $source);
        $project = app(UpdateProject::class)->execute(
            $actor, $context, $project,
            new SaveProjectData('Current project', (int) $center->getKey(), ProjectStage::Approved, null, 1),
            (string) str()->uuid(),
        );
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners(
            static fn (AuditEvent $event): bool => $event->correlation_id === $correlationId,
            static fn (): never => throw new RuntimeException('forced audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(RestoreProjectRevision::class));
            $action = app(RestoreProjectRevision::class);
            $action->execute($actor, $context, $project, $source, 2, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'title' => 'Current project', 'lock_version' => 2]);
            $this->assertDatabaseMissing('projects', ['id' => $project->getKey(), 'title' => 'Original project', 'lock_version' => 3]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_promote_deferred_projects_audit_failure_rolls_back_stage_revision_and_audit(): void
    {
        [$actor, $context] = $this->administratorContext();
        $year = PlanningYear::factory()->for($context->tenant)->create(['year_label' => (int) now($context->tenant->timezone)->year]);
        $project = Project::factory()->for($context->tenant)->create([
            'stage' => ProjectStage::Deferred,
            'deferred_target_planning_year_id' => $year->getKey(),
        ]);
        $auditCount = AuditEvent::query()->count();
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners(
            static fn (AuditEvent $event): bool => $event->event_type === 'project.deferred-promoted',
            static fn (): never => throw new RuntimeException('forced audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(PromoteDeferredProjects::class));
            $action = app(PromoteDeferredProjects::class);
            $action->execute($actor, $context);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('projects', ['id' => $project->getKey(), 'stage' => ProjectStage::Deferred->value, 'lock_version' => 1]);
            $this->assertDatabaseMissing('projects', ['id' => $project->getKey(), 'stage' => ProjectStage::Proposed->value]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{User,TenantContext} */
    private function administratorContext(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $this->app->make('request')->attributes->set(TenantContext::class, $context);

        return [$actor, $context];
    }

    /** @return array{Project,CostCenter} */
    private function project(User $actor, TenantContext $context): array
    {
        $center = CostCenter::factory()->for($context->tenant)->create();
        $project = app(CreateProject::class)->execute(
            $actor, $context,
            new SaveProjectData('Original project', (int) $center->getKey(), ProjectStage::Idea, null, null),
            (string) str()->uuid(),
        );

        return [$project, $center];
    }

    /** @return array{Dispatcher,string,array<int,mixed>} */
    private function auditCreatingListeners(\Closure $matches, \Closure $failure): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;
        $listeners = $dispatcher->getRawListeners()[$eventName] ?? [];
        $dispatcher->listen($eventName, static function (AuditEvent $event) use ($failure, $matches): void {
            if ($matches($event)) {
                $failure();
            }
        });

        return [$dispatcher, $eventName, $listeners];
    }

    /** @param array<int,mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);
        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
