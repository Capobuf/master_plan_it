<?php

namespace Tests\Feature\Revisions;

use App\Domain\Revisions\Actions\ApplyOperationalRevisionRetention;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OperationalRevisionRetentionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_eleventh_logical_revision_detaches_only_the_oldest_version_and_prunes_it_physically(): void
    {
        [$tenant, $actor, $vendor] = $this->fixture();
        $versions = collect();
        $oldestBatch = null;

        foreach (range(1, 11) as $position) {
            $version = $this->version($actor, $vendor, "Versione {$position}");
            $batch = $this->batch($tenant, $actor, $vendor, $position);
            $oldestBatch ??= $batch;
            $this->item($tenant, $vendor, $batch, $version, "Versione {$position}");
            $versions->push($version);
        }

        $detached = app(ApplyOperationalRevisionRetention::class)->execute();

        $this->assertSame(1, $detached);
        $this->assertNull(RevisionBatchItem::query()->where('revision_batch_id', $oldestBatch?->getKey())->value('version_id'));
        $this->assertSame(
            OperationalRevisionQuery::LIMIT,
            RevisionBatchItem::query()->whereNotNull('version_id')->where('operational_root_id', $vendor->getKey())->count(),
        );
        $this->assertSame('Versione 1', RevisionBatchItem::query()
            ->where('operational_root_type', $vendor->getMorphClass())
            ->where('operational_root_id', $vendor->getKey())
            ->oldest('id')
            ->firstOrFail()
            ->snapshot_contents['name']);
        $this->assertNotNull(Version::withTrashed()->findOrFail($versions->first()->getKey())->deleted_at);

        (new Version)->pruneAll();

        $this->assertSame(0, DB::table('versions')->where('id', $versions->first()->getKey())->count());
        $this->assertSame(1, DB::table('versions')->where('id', $versions->last()->getKey())->count());
        $this->assertSame(11, RevisionBatch::query()->where('root_subject_id', $vendor->getKey())->count());
        $this->assertSame(11, RevisionBatchItem::query()->where('operational_root_id', $vendor->getKey())->count());
    }

    public function test_retention_is_idempotent_and_legacy_restore_reference_protects_the_version(): void
    {
        [$tenant, $actor, $vendor] = $this->fixture();
        $protectedVersion = null;

        foreach (range(1, 11) as $position) {
            $version = $this->version($actor, $vendor, "Versione {$position}");
            $batch = $this->batch($tenant, $actor, $vendor, $position);
            $this->item($tenant, $vendor, $batch, $version, "Versione {$position}");
            $protectedVersion ??= $version;
        }

        RevisionBatch::query()->create([
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_kind' => 'human',
            'root_subject_type' => $vendor->getMorphClass(),
            'root_subject_id' => $vendor->getKey(),
            'operation' => 'restore',
            'correlation_id' => (string) Str::uuid(),
            'restored_from_version_id' => $protectedVersion?->getKey(),
            'occurred_at' => Carbon::parse('2026-01-01 01:00:00'),
        ]);

        $this->assertSame(1, app(ApplyOperationalRevisionRetention::class)->execute());
        $this->assertSame(0, app(ApplyOperationalRevisionRetention::class)->execute());
        (new Version)->pruneAll();

        $this->assertSame(1, DB::table('versions')->where('id', $protectedVersion?->getKey())->count());
    }

    public function test_operational_query_exposes_only_the_ten_newest_logical_batches(): void
    {
        [$tenant, $actor, $vendor] = $this->fixture();
        $batches = collect();
        foreach (range(1, 11) as $position) {
            $version = $this->version($actor, $vendor, "Visibile {$position}");
            $batch = $this->batch($tenant, $actor, $vendor, $position);
            $this->item($tenant, $vendor, $batch, $version, "Visibile {$position}");
            $batches->push($batch);
        }

        $query = app(OperationalRevisionQuery::class);
        $visible = $query->visibleForRoot(new TenantContext($tenant, $actor), $vendor);

        $this->assertCount(OperationalRevisionQuery::LIMIT, $visible);
        $this->assertSame($batches->slice(1)->reverse()->pluck('id')->values()->all(), $visible->pluck('id')->all());

        $this->expectException(ModelNotFoundException::class);
        $query->findVisibleBatch(new TenantContext($tenant, $actor), $vendor, (int) $batches->first()->getKey());
    }

    public function test_unlinked_legacy_version_is_not_marked_or_pruned(): void
    {
        [, $actor, $vendor] = $this->fixture();
        $legacy = $this->version($actor, $vendor, 'Versione legacy non classificata');

        $this->assertSame(0, app(ApplyOperationalRevisionRetention::class)->execute());
        (new Version)->pruneAll();

        $this->assertNull(Version::withTrashed()->findOrFail($legacy->getKey())->deleted_at);
    }

    public function test_retention_failure_rolls_back_every_detached_link_and_soft_delete_marker(): void
    {
        [$tenant, $actor, $vendor] = $this->fixture();
        $oldestVersion = null;
        foreach (range(1, 11) as $position) {
            $version = $this->version($actor, $vendor, "Rollback {$position}");
            $batch = $this->batch($tenant, $actor, $vendor, $position);
            $this->item($tenant, $vendor, $batch, $version, "Rollback {$position}");
            $oldestVersion ??= $version;
        }
        $itemCount = RevisionBatchItem::query()->count();
        $listener = function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'update `versions` set `deleted_at`')) {
                throw new RuntimeException('forced retention marker failure');
            }
        };
        DB::listen($listener);

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(ApplyOperationalRevisionRetention::class));
            $action = app(ApplyOperationalRevisionRetention::class);
            $action->execute();
        } finally {
            $this->assertDatabaseHas('revision_batch_items', ['version_id' => $oldestVersion?->getKey()]);
            $this->assertDatabaseHas('versions', ['id' => $oldestVersion?->getKey(), 'deleted_at' => null]);
            $this->assertDatabaseCount('revision_batch_items', $itemCount);
            $this->assertSame(11, RevisionBatchItem::query()
                ->where('operational_root_type', $vendor->getMorphClass())
                ->where('operational_root_id', $vendor->getKey())
                ->count());
        }
    }

    /** @return array{Tenant, User, Vendor} */
    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Fornitore corrente']);

        return [$tenant, $actor, $vendor];
    }

    private function version(User $actor, Vendor $vendor, string $name): Version
    {
        return Version::query()->create([
            'user_id' => $actor->getKey(),
            'versionable_type' => $vendor->getMorphClass(),
            'versionable_id' => $vendor->getKey(),
            'contents' => [
                'name' => $name,
                'vat_number' => null,
                'email' => null,
                'phone' => null,
                'address' => null,
                'active' => true,
            ],
        ]);
    }

    private function batch(Tenant $tenant, User $actor, Vendor $vendor, int $position): RevisionBatch
    {
        return RevisionBatch::query()->create([
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $actor->getKey(),
            'actor_kind' => 'human',
            'root_subject_type' => $vendor->getMorphClass(),
            'root_subject_id' => $vendor->getKey(),
            'operation' => $position === 1 ? 'create' : 'update',
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => Carbon::parse('2026-01-01')->addSeconds($position),
        ]);
    }

    private function item(Tenant $tenant, Vendor $vendor, RevisionBatch $batch, Version $version, string $name): void
    {
        RevisionBatchItem::query()->create([
            'revision_batch_id' => $batch->getKey(),
            'tenant_id' => $tenant->getKey(),
            'mutation' => 'upsert',
            'version_id' => $version->getKey(),
            'versionable_type' => $vendor->getMorphClass(),
            'versionable_id' => $vendor->getKey(),
            'snapshot_contents' => ['name' => $name],
            'operational_root_type' => $vendor->getMorphClass(),
            'operational_root_id' => $vendor->getKey(),
            'sequence' => 1,
        ]);
    }
}
