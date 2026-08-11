<?php

namespace App\Domain\Revisions\Actions;

use App\Domain\Revisions\Data\RevisionMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Vendor;
use App\Models\Version as ApplicationVersion;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class LinkVersionToRevisionBatch
{
    public function execute(
        RevisionBatch $batch,
        ApplicationVersion $version,
        int $sequence,
        ?RevisionMutation $mutation = null,
        bool $changed = true,
    ): RevisionBatchItem {
        return DB::transaction(function () use ($batch, $changed, $mutation, $sequence, $version): RevisionBatchItem {
            $persistedBatch = $this->persistedBatch($batch);
            $persistedVersion = $this->persistedVersion($version);
            $versionable = $this->sameTenantVersionable($persistedBatch, $persistedVersion);
            $this->assertSequenceAvailable($persistedBatch, $sequence);
            $planningYearId = $this->planningYearId($versionable);
            $resolvedMutation = $mutation ?? $this->mutation($persistedBatch, $versionable);
            $snapshot = (array) $persistedVersion->contents;
            [$rootType, $rootId] = $this->operationalRoot($versionable, $snapshot);

            return RevisionBatchItem::query()->create([
                'revision_batch_id' => $persistedBatch->getKey(),
                'tenant_id' => $persistedBatch->tenant_id,
                'planning_year_id' => $planningYearId,
                'mutation' => $resolvedMutation,
                'is_changed' => $changed,
                'version_id' => $persistedVersion->getKey(),
                'versionable_type' => (string) $persistedVersion->getAttribute('versionable_type'),
                'versionable_id' => (int) $persistedVersion->getAttribute('versionable_id'),
                'snapshot_contents' => $snapshot,
                'operational_root_type' => $rootType,
                'operational_root_id' => $rootId,
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

    private function sameTenantVersionable(RevisionBatch $batch, ApplicationVersion $version): Model
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

        return $versionable;
    }

    private function planningYearId(Model $versionable): ?int
    {
        if ($versionable instanceof PlanningYear) {
            return (int) $versionable->getKey();
        }
        if ($versionable instanceof Expense) {
            return (int) $versionable->planning_year_id;
        }
        if ($versionable instanceof ExpenseRow) {
            $expense = $versionable->expense()->withTrashed()->first();

            return $expense instanceof Expense ? (int) $expense->planning_year_id : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{string|null, int|null}
     */
    private function operationalRoot(Model $versionable, array $snapshot): array
    {
        if ($versionable instanceof Expense) {
            return [$versionable->getMorphClass(), (int) $versionable->getKey()];
        }
        if ($versionable instanceof ExpenseRow) {
            $expenseId = $snapshot['expense_id'] ?? $versionable->expense_id;

            return $expenseId === null ? [null, null] : [(new Expense)->getMorphClass(), (int) $expenseId];
        }
        if ($versionable instanceof Contract) {
            return [$versionable->getMorphClass(), (int) $versionable->getKey()];
        }
        if ($versionable instanceof ContractTerm) {
            $contractId = $snapshot['contract_id'] ?? $versionable->contract_id;

            return $contractId === null ? [null, null] : [(new Contract)->getMorphClass(), (int) $contractId];
        }
        if ($versionable instanceof Project || $versionable instanceof Vendor || $versionable instanceof CostCenter) {
            return [$versionable->getMorphClass(), (int) $versionable->getKey()];
        }

        return [null, null];
    }

    private function mutation(RevisionBatch $batch, Model $versionable): RevisionMutation
    {
        $operation = $batch->operation;
        $rootDeleted = ($operation instanceof RevisionOperation ? $operation : RevisionOperation::tryFrom((string) $operation)) === RevisionOperation::Delete
            && $batch->root_subject_type === $versionable->getMorphClass()
            && (int) $batch->root_subject_id === (int) $versionable->getKey();
        $softDeleted = method_exists($versionable, 'trashed') && $versionable->trashed();

        return $rootDeleted || $softDeleted ? RevisionMutation::Delete : RevisionMutation::Upsert;
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
