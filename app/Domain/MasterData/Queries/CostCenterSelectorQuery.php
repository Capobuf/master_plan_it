<?php

namespace App\Domain\MasterData\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class CostCenterSelectorQuery
{
    public function __construct(private readonly CostCenterTreeQuery $treeQuery) {}

    /** @return Collection<int, CostCenter> */
    public function forNewSelection(User $actor, TenantContext $context): Collection
    {
        return $this->flatten($this->treeQuery->forTenant($actor, $context))
            ->filter(fn (CostCenter $costCenter): bool => $costCenter->active)
            ->values();
    }

    /** @return Collection<int, CostCenter> */
    public function forRecord(User $actor, TenantContext $context, ?int $currentCostCenterId): Collection
    {
        return $this->flatten($this->treeQuery->forTenant($actor, $context))
            ->filter(fn (CostCenter $costCenter): bool => $costCenter->active || $costCenter->getKey() === $currentCostCenterId)
            ->values();
    }

    /** @param Collection<int, CostCenter> $branch
     * @return Collection<int, CostCenter>
     */
    private function flatten(Collection $branch): Collection
    {
        return new Collection($branch->flatMap(function (CostCenter $costCenter): array {
            $children = $costCenter->getRelation('children');

            return [
                $costCenter,
                ...($children instanceof Collection ? $this->flatten($children)->all() : []),
            ];
        })->values()->all());
    }
}
