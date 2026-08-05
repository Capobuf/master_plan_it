<?php

namespace App\Policies;

use App\Models\RevisionBatch;
use App\Models\User;

final class RevisionPolicy
{
    public function view(User $user, RevisionBatch $batch): bool
    {
        return $user->exists
            && $user->getKey() !== null
            && $batch->exists
            && $batch->getKey() !== null
            && $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $batch->tenant_id;
    }

    public function restore(User $user, RevisionBatch $batch): bool
    {
        return false;
    }
}
