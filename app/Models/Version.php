<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Overtrue\LaravelVersionable\Version as PackageVersion;

class Version extends PackageVersion
{
    use Prunable;

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()
            ->without('versionable')
            ->whereNotNull('deleted_at')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('revision_batch_items')
                ->whereColumn('revision_batch_items.version_id', 'versions.id'))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('revision_batches')
                ->whereColumn('revision_batches.restored_from_version_id', 'versions.id'));
    }

    public function revert(): bool
    {
        throw new DomainException('REVISION_RESTORE_INVALID');
    }

    public function revertWithoutSaving(): ?Model
    {
        throw new DomainException('REVISION_RESTORE_INVALID');
    }
}
