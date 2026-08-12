<?php

namespace App\Domain\Budget\Services;

use App\Models\PlanningYear;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Serializes every mutation that can change one or more annual economic datasets.
 *
 * Callers must already be inside a database transaction. When Tenant state is part
 * of the same mutation, request the Tenant lock first so every writer shares one
 * deterministic lock order.
 */
final class AnnualEconomicMutationGuard
{
    /**
     * @param  list<int>  $planningYearIds
     * @return Collection<int, PlanningYear>
     */
    public function acquire(int $tenantId, array $planningYearIds, bool $lockTenant = false): Collection
    {
        if ($lockTenant) {
            $tenant = Tenant::query()
                ->whereKey($tenantId)
                ->lockForUpdate()
                ->first();

            if (! $tenant instanceof Tenant) {
                throw new DomainException('TENANT_CONTEXT_REQUIRED');
            }
        }

        $ids = collect($planningYearIds)
            ->map(static fn (int $id): int => $id)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $years = PlanningYear::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (PlanningYear $year): int => (int) $year->getKey());

        if ($years->count() !== $ids->count()) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $years;
    }
}
