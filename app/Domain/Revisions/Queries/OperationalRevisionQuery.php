<?php

namespace App\Domain\Revisions\Queries;

use App\Domain\Revisions\Data\RevisionMutation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class OperationalRevisionQuery
{
    public const LIMIT = 10;

    /** @return Collection<int, RevisionBatch> */
    public function visibleForRoot(TenantContext $context, Model $root): Collection
    {
        $this->assertRoot($context, $root);

        return TenantOwnedRecordQuery::forTenant($context, RevisionBatch::class)
            ->where('root_subject_type', $root->getMorphClass())
            ->where('root_subject_id', $root->getKey())
            ->whereHas('items', fn ($query) => $query
                ->where('operational_root_type', $root->getMorphClass())
                ->where('operational_root_id', $root->getKey())
                ->whereNotNull('snapshot_contents'))
            ->with(['actor:id,name', 'items' => fn ($query) => $query
                ->where('operational_root_type', $root->getMorphClass())
                ->where('operational_root_id', $root->getKey())
                ->orderBy('sequence')])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    public function findVisibleBatch(TenantContext $context, Model $root, int $batchId): RevisionBatch
    {
        $batch = $this->visibleForRoot($context, $root)->firstWhere('id', $batchId);
        if (! $batch instanceof RevisionBatch) {
            throw (new ModelNotFoundException)->setModel(RevisionBatch::class, [$batchId]);
        }

        return $batch;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function snapshot(TenantContext $context, Model $root, RevisionBatch $source): array
    {
        $source = $this->findVisibleBatch($context, $root, (int) $source->getKey());
        $items = TenantOwnedRecordQuery::forTenant($context, RevisionBatchItem::class)
            ->join('revision_batches as batches', 'batches.id', '=', 'revision_batch_items.revision_batch_id')
            ->where('revision_batch_items.operational_root_type', $root->getMorphClass())
            ->where('revision_batch_items.operational_root_id', $root->getKey())
            ->where(function ($query) use ($source): void {
                $query->where('batches.occurred_at', '<', $source->occurred_at)
                    ->orWhere(function ($sameTimestamp) use ($source): void {
                        $sameTimestamp->where('batches.occurred_at', $source->occurred_at)
                            ->where('batches.id', '<=', $source->getKey());
                    });
            })
            ->select('revision_batch_items.*')
            ->orderBy('batches.occurred_at')
            ->orderBy('batches.id')
            ->orderBy('revision_batch_items.sequence')
            ->orderBy('revision_batch_items.id')
            ->get();

        $state = [];
        foreach ($items as $item) {
            $type = (string) $item->versionable_type;
            $id = (int) $item->versionable_id;
            $mutation = $item->mutation instanceof RevisionMutation
                ? $item->mutation
                : RevisionMutation::tryFrom((string) $item->getRawOriginal('mutation'));
            if ($mutation === RevisionMutation::Delete) {
                unset($state[$type][$id]);

                continue;
            }
            $contents = $item->snapshot_contents;
            if (! is_array($contents)) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $state[$type][$id] = $contents;
        }

        $rootType = $root->getMorphClass();
        if (! isset($state[$rootType][(int) $root->getKey()])) {
            throw new DomainException('REVISION_RESTORE_INVALID');
        }

        return $state;
    }

    /** @return Collection<int, RevisionBatchItem> */
    public function changedItems(Model $root, RevisionBatch $batch): Collection
    {
        return $batch->items
            ->where('operational_root_type', $root->getMorphClass())
            ->where('operational_root_id', $root->getKey())
            ->values();
    }

    private function assertRoot(TenantContext $context, Model $root): void
    {
        $key = $root->getKey();
        if (! $root->exists || $key === null || $key !== $root->getRawOriginal($root->getKeyName())
            || (int) $root->getAttribute('tenant_id') !== $context->tenantId) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }
    }
}
