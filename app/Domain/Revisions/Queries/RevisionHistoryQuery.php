<?php

namespace App\Domain\Revisions\Queries;

use App\Domain\Revisions\Data\RevisionHistoryRow;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\Version as ApplicationVersion;
use DomainException;
use Illuminate\Support\Collection;

final class RevisionHistoryQuery
{
    /**
     * @return Collection<int, RevisionHistoryRow>
     */
    public function forBatch(RevisionBatch $batch, TenantContext $context): Collection
    {
        $persistedBatch = $this->persistedSameTenantBatch($batch, $context);

        return RevisionBatchItem::query()
            ->where('revision_batch_id', $persistedBatch->getKey())
            ->with(['version'])
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->map(fn (RevisionBatchItem $item): RevisionHistoryRow => new RevisionHistoryRow(
                tenantId: (int) $persistedBatch->tenant_id,
                sequence: (int) $item->sequence,
                versionId: (int) $item->version_id,
                versionableType: (string) $item->versionable_type,
                versionableId: (int) $item->versionable_id,
                contents: $item->version instanceof ApplicationVersion
                    ? (array) $item->version->contents
                    : [],
            ));
    }

    private function persistedSameTenantBatch(RevisionBatch $batch, TenantContext $context): RevisionBatch
    {
        if (! $batch->exists || $batch->getKey() === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        if ((int) $batch->tenant_id !== $context->tenantId) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $tenantKeyName = $context->tenant->getKeyName();
        $tenantKey = $context->tenant->getKey();
        $tenantOriginalKey = $context->tenant->getRawOriginal($tenantKeyName);

        if (! $context->tenant->exists || $tenantKey === null || $tenantKey !== $tenantOriginalKey || (int) $tenantKey !== $context->tenantId) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        if (! Tenant::query()->whereKey($tenantOriginalKey)->exists()) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persistedBatch = RevisionBatch::query()
            ->where('tenant_id', $context->tenantId)
            ->whereKey($batch->getKey())
            ->first();

        if ($persistedBatch === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persistedBatch;
    }
}
