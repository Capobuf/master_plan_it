<?php

namespace Tests\Feature\Revisions;

use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\RevisionHistoryQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version as ApplicationVersion;
use App\Policies\RevisionPolicy;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class RevisionBatchIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_begin_revision_batch_persists_actor_tenant_root_operation_correlation_and_audit(): void
    {
        [$actor, $context] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);
        $correlationId = (string) Str::uuid();
        $auditCount = AuditEvent::query()->count();

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            'Correct an approved supplier value.',
            $correlationId,
            $vendor,
            null,
        );

        $this->assertInstanceOf(RevisionBatch::class, $batch);
        $this->assertSame($context->tenantId, $batch->tenant_id);
        $this->assertSame($actor->getKey(), $batch->actor_user_id);
        $this->assertSame($vendor->getMorphClass(), $batch->root_subject_type);
        $this->assertSame($vendor->getKey(), $batch->root_subject_id);
        $this->assertSame(RevisionOperation::Update, $batch->operation);
        $this->assertSame('Correct an approved supplier value.', $batch->reason);
        $this->assertSame($correlationId, $batch->correlation_id);
        $this->assertNull($batch->restored_from_batch_id);
        $this->assertNull($batch->restored_from_version_id);
        $this->assertNotNull($batch->occurred_at);
        $this->assertDatabaseCount('audit_events', $auditCount + 1);
        $this->assertDatabaseHas('audit_events', [
            'correlation_id' => $correlationId,
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $actor->getKey(),
        ]);
    }

    public function test_begin_revision_batch_rejects_a_spoofed_actor_without_side_effects(): void
    {
        [$actor, $context] = $this->actorContext();
        [$otherActor] = $this->actorContext();
        $actor->forceFill(['id' => $otherActor->getKey()]);
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);
        $batchCount = DB::table('revision_batches')->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertAuthorizationFailure(fn () => app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $vendor,
            null,
        ), 'PERMISSION_DENIED');

        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_begin_revision_batch_rejects_a_root_with_a_spoofed_current_key_without_side_effects(): void
    {
        [$actor, $context] = $this->actorContext();
        $root = Vendor::factory()->for($context->tenant)->create(['name' => 'Original root']);
        $otherRoot = Vendor::factory()->for($context->tenant)->create(['name' => 'Spoof target']);
        $root->forceFill([$root->getKeyName() => $otherRoot->getKey()]);
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertDomainFailure(fn () => app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $root,
            null,
        ));

        $this->assertNotSame($root->getRawOriginal($root->getKeyName()), $root->getKey());
        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_begin_revision_batch_rejects_a_foreign_root_with_tenant_state_tampered_in_memory(): void
    {
        [$actor, $context] = $this->actorContext();
        [, $foreignContext] = $this->actorContext();
        $foreignRoot = Vendor::factory()->for($foreignContext->tenant)->create(['name' => 'Foreign root']);
        $foreignRoot->forceFill(['tenant_id' => $context->tenantId]);
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertDomainFailure(fn () => app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $foreignRoot,
            null,
        ));

        $this->assertSame($foreignContext->tenantId, (int) $foreignRoot->getRawOriginal('tenant_id'));
        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_begin_revision_batch_rejects_a_persisted_tenant_model_without_the_versionable_contract(): void
    {
        [$actor, $context] = $this->actorContext();
        $tenantOwnedUser = User::factory()->for($context->tenant)->create();
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        $this->assertDomainFailure(fn () => app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $tenantOwnedUser,
            null,
        ));

        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_begin_restore_persists_only_a_reloaded_version_source_for_the_exact_root(): void
    {
        [$actor, $context] = $this->actorContext();
        $root = Vendor::factory()->for($context->tenant)->create(['name' => 'Restore root']);
        $root->forceFill(['name' => 'Restore root updated', 'lock_version' => 2])->save();
        $source = ApplicationVersion::query()
            ->where('versionable_type', $root->getMorphClass())
            ->where('versionable_id', $root->getKey())
            ->oldest('id')
            ->firstOrFail();

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Restore,
            null,
            (string) Str::uuid(),
            $root,
            (int) $source->getKey(),
        );

        $this->assertSame($source->getKey(), $batch->restored_from_version_id);
        $this->assertDatabaseHas('revision_batches', [
            'id' => $batch->getKey(),
            'root_subject_type' => $root->getMorphClass(),
            'root_subject_id' => $root->getKey(),
            'operation' => RevisionOperation::Restore->value,
            'restored_from_version_id' => $source->getKey(),
        ]);
    }

    public function test_begin_revision_batch_rejects_invalid_restore_source_combinations_without_side_effects(): void
    {
        [$actor, $context] = $this->actorContext();
        [, $foreignContext] = $this->actorContext();
        $root = Vendor::factory()->for($context->tenant)->create(['name' => 'Restore root']);
        $sameTenantOtherRoot = Vendor::factory()->for($context->tenant)->create(['name' => 'Other local root']);
        $foreignRoot = Vendor::factory()->for($foreignContext->tenant)->create(['name' => 'Foreign root']);
        $rootSource = $this->oldestVersionFor($root);
        $sameTenantWrongSource = $this->oldestVersionFor($sameTenantOtherRoot);
        $foreignSource = $this->oldestVersionFor($foreignRoot);
        $missingVersionId = (int) ApplicationVersion::query()->max('id') + 1;
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        foreach ([
            [RevisionOperation::Restore, null],
            [RevisionOperation::Update, (int) $rootSource->getKey()],
            [RevisionOperation::Restore, $missingVersionId],
            [RevisionOperation::Restore, (int) $sameTenantWrongSource->getKey()],
            [RevisionOperation::Restore, (int) $foreignSource->getKey()],
        ] as [$operation, $sourceId]) {
            $this->assertDomainFailure(fn () => app(BeginRevisionBatch::class)->execute(
                $actor,
                $context,
                $operation,
                null,
                (string) Str::uuid(),
                $root,
                $sourceId,
            ));
        }

        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_begin_revision_batch_audit_failure_rolls_back_the_batch(): void
    {
        [$actor, $context] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);
        $correlationId = (string) Str::uuid();
        $batchCount = RevisionBatch::query()->count();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(BeginRevisionBatch::class);
            $action->execute(
                $actor,
                $context,
                RevisionOperation::Update,
                null,
                $correlationId,
                $vendor,
                null,
            );
        } finally {
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseMissing('revision_batches', ['correlation_id' => $correlationId]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_link_version_to_revision_batch_links_exact_version_and_sequence(): void
    {
        [$actor, $context] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Versioned supplier']);
        $vendor->forceFill(['name' => 'Updated supplier', 'lock_version' => 2])->save();
        $version = $vendor->latestVersion()->firstOrFail();
        $this->assertInstanceOf(ApplicationVersion::class, $version);

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            'Correct an approved supplier value.',
            (string) Str::uuid(),
            $vendor,
            null,
        );
        app(LinkVersionToRevisionBatch::class)->execute($batch, $version, sequence: 1);

        $item = RevisionBatchItem::query()->where('revision_batch_id', $batch->getKey())->firstOrFail();
        $this->assertSame($version->getKey(), $item->version_id);
        $this->assertSame(1, $item->sequence);
        $this->assertSame($vendor->getMorphClass(), $item->versionable_type);
        $this->assertSame($vendor->getKey(), $item->versionable_id);
    }

    public function test_link_version_to_revision_batch_rejects_a_foreign_version_without_link(): void
    {
        [$actor, $context] = $this->actorContext();
        [, $otherContext] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Local supplier']);
        $foreignVendor = Vendor::factory()->for($otherContext->tenant)->create(['name' => 'Foreign supplier']);
        $foreignVendor->forceFill(['name' => 'Foreign updated', 'lock_version' => 2])->save();
        $foreignVersion = ApplicationVersion::query()
            ->where('versionable_type', $foreignVendor->getMorphClass())
            ->where('versionable_id', $foreignVendor->getKey())
            ->latest('id')
            ->firstOrFail();

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $vendor,
            null,
        );
        $itemCount = DB::table('revision_batch_items')->count();

        $this->assertDomainFailure(fn () => app(LinkVersionToRevisionBatch::class)->execute($batch, $foreignVersion, sequence: 1));
        $this->assertDatabaseCount('revision_batch_items', $itemCount);
    }

    public function test_link_version_to_revision_batch_rejects_a_batch_with_a_spoofed_current_key(): void
    {
        [$actor, $context] = $this->actorContext();
        $root = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);
        $firstBatch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $root,
            null,
        );
        $secondBatch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $root,
            null,
        );
        $firstBatch->forceFill([$firstBatch->getKeyName() => $secondBatch->getKey()]);
        $itemCount = RevisionBatchItem::query()->count();

        $this->assertDomainFailure(fn () => app(LinkVersionToRevisionBatch::class)->execute(
            $firstBatch,
            $this->anyVersion($context),
            sequence: 1,
        ));

        $this->assertNotSame($firstBatch->getRawOriginal($firstBatch->getKeyName()), $firstBatch->getKey());
        $this->assertDatabaseCount('revision_batch_items', $itemCount);
    }

    public function test_link_version_to_revision_batch_rejects_spoofed_and_detached_version_identities(): void
    {
        [$actor, $context] = $this->actorContext();
        $root = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $root,
            null,
        );
        $version = $this->anyVersion($context);
        $otherVersion = $this->anyVersion($context);
        $version->forceFill([$version->getKeyName() => $otherVersion->getKey()]);
        $detachedVersion = new ApplicationVersion($otherVersion->getAttributes());
        $itemCount = RevisionBatchItem::query()->count();

        $this->assertDomainFailure(fn () => app(LinkVersionToRevisionBatch::class)->execute($batch, $version, sequence: 1));
        $this->assertDomainFailure(fn () => app(LinkVersionToRevisionBatch::class)->execute($batch, $detachedVersion, sequence: 1));

        $this->assertNotSame($version->getRawOriginal($version->getKeyName()), $version->getKey());
        $this->assertFalse($detachedVersion->exists);
        $this->assertDatabaseCount('revision_batch_items', $itemCount);
    }

    public function test_link_version_to_revision_batch_rejects_foreign_persisted_version_state_tampered_in_memory(): void
    {
        [$actor, $context] = $this->actorContext();
        [, $foreignContext] = $this->actorContext();
        $localRoot = Vendor::factory()->for($context->tenant)->create(['name' => 'Local root']);
        $foreignRoot = Vendor::factory()->for($foreignContext->tenant)->create(['name' => 'Foreign versionable']);
        $foreignVersion = ApplicationVersion::query()
            ->where('versionable_type', $foreignRoot->getMorphClass())
            ->where('versionable_id', $foreignRoot->getKey())
            ->oldest('id')
            ->firstOrFail();
        $persistedContents = $foreignVersion->getRawOriginal('contents');
        $foreignVersion->forceFill([
            'versionable_type' => $localRoot->getMorphClass(),
            'versionable_id' => $localRoot->getKey(),
            'contents' => ['name' => 'Tampered local payload'],
        ]);
        $foreignVersion->setRelation('versionable', $localRoot);
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $localRoot,
            null,
        );
        $itemCount = RevisionBatchItem::query()->count();

        $this->assertDomainFailure(fn () => app(LinkVersionToRevisionBatch::class)->execute(
            $batch,
            $foreignVersion,
            sequence: 1,
        ));

        $this->assertSame(
            $persistedContents,
            ApplicationVersion::query()->findOrFail($foreignVersion->getKey())->getRawOriginal('contents'),
        );
        $this->assertDatabaseCount('revision_batch_items', $itemCount);
    }

    public function test_link_version_to_revision_batch_creates_the_item_from_same_tenant_persisted_version_state(): void
    {
        [$actor, $context] = $this->actorContext();
        $versionable = Vendor::factory()->for($context->tenant)->create(['name' => 'Persisted versionable']);
        $version = ApplicationVersion::query()
            ->where('versionable_type', $versionable->getMorphClass())
            ->where('versionable_id', $versionable->getKey())
            ->oldest('id')
            ->firstOrFail();
        $persistedContents = $version->getRawOriginal('contents');
        $tamperTarget = CostCenter::factory()->for($context->tenant)->create(['name' => 'Tamper target']);
        $version->forceFill([
            'versionable_type' => $tamperTarget->getMorphClass(),
            'versionable_id' => $tamperTarget->getKey(),
            'contents' => ['name' => 'Tampered payload'],
        ]);
        $version->setRelation('versionable', $tamperTarget);
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $versionable,
            null,
        );

        $item = app(LinkVersionToRevisionBatch::class)->execute($batch, $version, sequence: 1);

        $this->assertSame($version->getKey(), $item->version_id);
        $this->assertSame($versionable->getMorphClass(), $item->versionable_type);
        $this->assertSame($versionable->getKey(), $item->versionable_id);
        $this->assertSame(
            $persistedContents,
            ApplicationVersion::query()->findOrFail($version->getKey())->getRawOriginal('contents'),
        );
    }

    public function test_link_version_to_revision_batch_supports_persisted_versions_of_soft_deleted_master_data(): void
    {
        [$actor, $context] = $this->actorContext();
        $batchRoot = Vendor::factory()->for($context->tenant)->create(['name' => 'Batch root']);
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $batchRoot,
            null,
        );

        foreach ([
            Vendor::factory()->for($context->tenant)->create(['name' => 'Deleted supplier']),
            CostCenter::factory()->for($context->tenant)->create(['name' => 'Deleted cost center']),
        ] as $sequence => $versionable) {
            $versionable->forceFill(['name' => $versionable->name.' updated', 'lock_version' => 2])->save();
            $versionId = $versionable->latestVersion()->firstOrFail()->getKey();
            $versionable->delete();
            $version = ApplicationVersion::query()->findOrFail($versionId);

            $item = app(LinkVersionToRevisionBatch::class)->execute($batch, $version, sequence: $sequence + 1);

            $this->assertSame($versionId, $item->version_id);
            $this->assertSame($versionable->getMorphClass(), $item->versionable_type);
            $this->assertSame($versionable->getKey(), $item->versionable_id);
        }
    }

    public function test_revision_history_query_returns_tenant_scoped_ordered_compare_rows(): void
    {
        [$actor, $context] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'History supplier']);
        $vendor->forceFill(['name' => 'First update', 'lock_version' => 2])->save();
        $vendor->forceFill(['name' => 'Second update', 'lock_version' => 3])->save();
        $versions = ApplicationVersion::query()
            ->where('versionable_type', $vendor->getMorphClass())
            ->where('versionable_id', $vendor->getKey())
            ->orderBy('id')
            ->get();

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            'Multiple corrections.',
            (string) Str::uuid(),
            $vendor,
            null,
        );
        foreach ($versions as $index => $version) {
            app(LinkVersionToRevisionBatch::class)->execute($batch, $version, sequence: $index + 1);
        }

        $rows = app(RevisionHistoryQuery::class)->forBatch($batch, $context);

        $this->assertCount($versions->count(), $rows);
        $this->assertSame(
            $versions->sortBy('id')->pluck('id')->values()->all(),
            collect($rows)->sortBy('versionId')->pluck('versionId')->values()->all(),
        );
        foreach ($rows as $row) {
            $this->assertSame($context->tenantId, $row->tenantId);
        }
    }

    public function test_revision_history_query_rejects_a_foreign_batch_tenant(): void
    {
        [, $context] = $this->actorContext();
        [, $otherContext] = $this->actorContext();
        $otherVendor = Vendor::factory()->for($otherContext->tenant)->create(['name' => 'Other supplier']);

        $batch = app(BeginRevisionBatch::class)->execute(
            $otherContext->actor,
            $otherContext,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $otherVendor,
            null,
        );

        $this->assertDomainFailure(fn () => app(RevisionHistoryQuery::class)->forBatch($batch, $context));
    }

    public function test_revision_policy_denies_direct_restore_and_permits_same_tenant_view(): void
    {
        [$actor, $context] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $vendor,
            null,
        );

        $policy = app(RevisionPolicy::class);
        $this->assertFalse($policy->restore($actor, $batch));
        $this->assertTrue($policy->view($actor, $batch));
    }

    public function test_link_failure_rolls_back_without_an_item_or_partial_batch_mutation(): void
    {
        [$actor, $context] = $this->actorContext();
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Root supplier']);
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $vendor,
            null,
        );
        $batchId = $batch->getKey();
        $itemCount = RevisionBatchItem::query()->count();

        $dispatcher = DB::connection()->getEventDispatcher();
        $eventName = QueryExecuted::class;
        $dispatcher->listen($eventName, function (QueryExecuted $event): void {
            if (! str_contains(strtolower($event->sql), 'insert into `revision_batch_items`')) {
                return;
            }

            throw new RuntimeException('forced link failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(LinkVersionToRevisionBatch::class);
            $action->execute($batch, $this->anyVersion($context), sequence: 1);
        } finally {
            $dispatcher->forget($eventName);
            $this->assertDatabaseHas('revision_batches', ['id' => $batchId]);
            $this->assertDatabaseCount('revision_batch_items', $itemCount);
            $this->assertDatabaseMissing('revision_batch_items', ['revision_batch_id' => $batchId]);
        }
    }

    private function anyVersion(TenantContext $context): ApplicationVersion
    {
        $vendor = Vendor::factory()->for($context->tenant)->create(['name' => 'Revision supplier '.Str::uuid()]);

        return ApplicationVersion::query()
            ->where('versionable_type', $vendor->getMorphClass())
            ->where('versionable_id', $vendor->getKey())
            ->oldest('id')
            ->firstOrFail();
    }

    private function oldestVersionFor(Model $versionable): ApplicationVersion
    {
        return ApplicationVersion::query()
            ->where('versionable_type', $versionable->getMorphClass())
            ->where('versionable_id', $versionable->getKey())
            ->oldest('id')
            ->firstOrFail();
    }

    /** @return array{User, TenantContext} */
    private function actorContext(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();

        return [$actor, new TenantContext($tenant, $actor)];
    }

    private function assertAuthorizationFailure(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Unexpectedly bypassed [{$code}].");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    private function assertDomainFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a domain rejection for a foreign revision relationship.');
        } catch (DomainException $exception) {
            $this->assertSame('TENANT_RELATION_MISMATCH', $exception->getMessage());
        }
    }
}
