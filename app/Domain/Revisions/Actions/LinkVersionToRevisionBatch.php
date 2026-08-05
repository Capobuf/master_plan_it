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
            $persistedVersion = $this->persistedVersion($version);
            $this->assertSameTenantVersion($persistedBatch, $persistedVersion);
            $this->assertSequenceAvailable($persistedBatch, $sequence);

            return RevisionBatchItem::query()->create([
                'revision_batch_id' => $persistedBatch->getKey(),
                'version_id' => $persistedVersion->getKey(),
                'versionable_type' => (string) $persistedVersion->getAttribute('versionable_type'),
                'versionable_id' => (int) $persistedVersion->getAttribute('versionable_id'),
                'sequence' => $sequence,
            ]);
        });
    }

    private function persistedBatch(RevisionBatch $batch): RevisionBatch
    {
        $key = $batch->getKey();
        $originalKey = $batch->getRawOriginal($batch->getKeyName());

        if (! $batch->exists || $key === null || $originalKey === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persisted = RevisionBatch::query()->lockForUpdate()->find($originalKey);

        if ($persisted === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persisted;
    }

    private function persistedVersion(ApplicationVersion $version): ApplicationVersion
    {
        $key = $version->getKey();
        $originalKey = $version->getRawOriginal($version->getKeyName());

        if (! $version->exists || $key === null || $originalKey === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persisted = ApplicationVersion::query()->whereKey($originalKey)->first();

        if (! $persisted instanceof ApplicationVersion) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persisted;
    }

    private function assertSameTenantVersion(RevisionBatch $batch, ApplicationVersion $version): void
    {
        $versionable = $version->versionable()->withTrashed()->first();

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
