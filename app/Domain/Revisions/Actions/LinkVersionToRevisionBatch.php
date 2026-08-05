<?php

namespace App\Domain\Revisions\Actions;

use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Version as ApplicationVersion;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class LinkVersionToRevisionBatch
{
    public function execute(RevisionBatch $batch, ApplicationVersion $version, int $sequence): RevisionBatchItem
    {
        return DB::transaction(function () use ($batch, $sequence, $version): RevisionBatchItem {
            $persistedBatch = $this->persistedBatch($batch);
            $this->assertSameTenantVersion($persistedBatch, $version);
            $this->assertSequenceAvailable($persistedBatch, $sequence);
            $versionable = $version->versionable;

            return RevisionBatchItem::query()->create([
                'revision_batch_id' => $persistedBatch->getKey(),
                'version_id' => $version->getKey(),
                'versionable_type' => (string) $version->getAttribute('versionable_type'),
                'versionable_id' => (int) $version->getAttribute('versionable_id'),
                'sequence' => $sequence,
            ]);
        });
    }

    private function persistedBatch(RevisionBatch $batch): RevisionBatch
    {
        if (! $batch->exists || $batch->getKey() === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persisted = RevisionBatch::query()->lockForUpdate()->find($batch->getKey());

        if ($persisted === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persisted;
    }

    private function assertSameTenantVersion(RevisionBatch $batch, ApplicationVersion $version): void
    {
        if (! $version->exists || $version->getKey() === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $versionable = $version->versionable;

        if (! $versionable instanceof Model) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $tenantColumn = 'tenant_id';

        if (! isset($versionable->{$tenantColumn})) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        if ((int) $versionable->{$tenantColumn} !== (int) $batch->tenant_id) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }
    }

    private function assertSequenceAvailable(RevisionBatch $batch, int $sequence): void
    {
        if ($sequence < 1) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $exists = RevisionBatchItem::query()
            ->where('revision_batch_id', $batch->getKey())
            ->where('sequence', $sequence)
            ->exists();

        if ($exists) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }
    }
}
