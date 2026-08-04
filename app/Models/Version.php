<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Overtrue\LaravelVersionable\Version as PackageVersion;

class Version extends PackageVersion
{
    public function revert(): bool
    {
        throw new DomainException('REVISION_RESTORE_INVALID');
    }

    public function revertWithoutSaving(): ?Model
    {
        throw new DomainException('REVISION_RESTORE_INVALID');
    }
}
