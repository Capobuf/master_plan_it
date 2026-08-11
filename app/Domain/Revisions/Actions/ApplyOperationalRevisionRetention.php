<?php

namespace App\Domain\Revisions\Actions;

use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Version;
use Illuminate\Support\Facades\DB;

final class ApplyOperationalRevisionRetention
{
    public function execute(): int
    {
        return DB::transaction(function (): int {
            $this->detachRedundantLegacyRestoreSources();

            $batchIds = collect(DB::select(<<<'SQL'
                SELECT ranked.revision_batch_id
                FROM (
                    SELECT roots.revision_batch_id,
                        ROW_NUMBER() OVER (
                            PARTITION BY roots.tenant_id, roots.operational_root_type, roots.operational_root_id
                            ORDER BY roots.occurred_at DESC, roots.revision_batch_id DESC
                        ) AS logical_rank
                    FROM (
                        SELECT DISTINCT
                            items.tenant_id,
                            items.operational_root_type,
                            items.operational_root_id,
                            items.revision_batch_id,
                            batches.occurred_at
                        FROM revision_batch_items AS items
                        INNER JOIN revision_batches AS batches ON batches.id = items.revision_batch_id
                        WHERE items.tenant_id IS NOT NULL
                            AND items.operational_root_type IS NOT NULL
                            AND items.operational_root_id IS NOT NULL
                            AND items.snapshot_contents IS NOT NULL
                    ) AS roots
                ) AS ranked
                WHERE ranked.logical_rank > ?
                SQL, [OperationalRevisionQuery::LIMIT]))
                ->map(static fn (object $row): int => (int) $row->revision_batch_id)
                ->unique()
                ->values();

            if ($batchIds->isEmpty()) {
                return 0;
            }

            $versionIds = RevisionBatchItem::query()
                ->whereIn('revision_batch_id', $batchIds)
                ->whereNotNull('snapshot_contents')
                ->whereNotNull('operational_root_type')
                ->whereNotNull('operational_root_id')
                ->whereNotNull('version_id')
                ->pluck('version_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();

            $detached = RevisionBatchItem::query()
                ->whereIn('revision_batch_id', $batchIds)
                ->whereNotNull('snapshot_contents')
                ->whereNotNull('operational_root_type')
                ->whereNotNull('operational_root_id')
                ->whereNotNull('version_id')
                ->update(['version_id' => null]);

            if ($versionIds->isNotEmpty()) {
                Version::query()
                    ->without('versionable')
                    ->whereIn('id', $versionIds)
                    ->whereNotExists(fn ($query) => $query
                        ->selectRaw('1')
                        ->from('revision_batch_items')
                        ->whereColumn('revision_batch_items.version_id', 'versions.id'))
                    ->whereNotExists(fn ($query) => $query
                        ->selectRaw('1')
                        ->from('revision_batches')
                        ->whereColumn('revision_batches.restored_from_version_id', 'versions.id'))
                    ->delete();
            }

            return $detached;
        });
    }

    private function detachRedundantLegacyRestoreSources(): void
    {
        RevisionBatch::query()
            ->whereNotNull('restored_from_batch_id')
            ->whereNotNull('restored_from_version_id')
            ->update(['restored_from_version_id' => null]);
    }
}
