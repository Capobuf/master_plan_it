<?php

namespace App\Domain\Budget\Services;

use App\Domain\Tenancy\Services\TenantMutationLock;
use App\Models\PlanningYear;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Serializes every mutation that can change one or more annual economic datasets.
 *
 * Callers must already be inside a database transaction. Every mutation acquires
 * the Tenant first: shared for ordinary annual writes, exclusive only when Tenant
 * state/Base is part of the mutation. It then locks Years in ascending ID order.
 * This Tenant -> Years -> roots order prevents approval/Base writers from forming
 * an InnoDB cycle with writers whose Revision/Audit inserts reference the Tenant.
 */
final class AnnualEconomicMutationGuard
{
    public function __construct(private readonly TenantMutationLock $tenantLock) {}

    /**
     * @param  list<int>  $planningYearIds
     * @return Collection<int, PlanningYear>
     */
    public function acquire(int $tenantId, array $planningYearIds, bool $lockTenant = false): Collection
    {
        $lockTenant
            ? $this->tenantLock->exclusive($tenantId)
            : $this->tenantLock->shared($tenantId);

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
