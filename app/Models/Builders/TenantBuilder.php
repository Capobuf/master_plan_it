<?php

namespace App\Models\Builders;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Tenant>
 */
final class TenantBuilder extends Builder
{
    public function delete(): never
    {
        $this->denyDeletion();
    }

    public function forceDelete(): never
    {
        $this->denyDeletion();
    }

    private function denyDeletion(): never
    {
        throw new \LogicException('Tenants cannot be permanently deleted.');
    }
}
