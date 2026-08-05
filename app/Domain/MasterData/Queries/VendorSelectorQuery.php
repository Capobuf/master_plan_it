<?php

namespace App\Domain\MasterData\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class VendorSelectorQuery
{
    public function __construct(private readonly VendorListQuery $listQuery) {}

    /** @return Collection<int, Vendor> */
    public function forNewSelection(User $actor, TenantContext $context): Collection
    {
        return $this->listQuery->forTenant($actor, $context)
            ->where('active', true)
            ->get();
    }

    /** @return Collection<int, Vendor> */
    public function forRecord(User $actor, TenantContext $context, ?int $currentVendorId): Collection
    {
        return $this->listQuery->forTenant($actor, $context)
            ->where(function (Builder $query) use ($currentVendorId): void {
                $query->where('active', true);

                if ($currentVendorId !== null) {
                    $query->orWhere(function (Builder $current) use ($currentVendorId): void {
                        $current
                            ->whereKey($currentVendorId)
                            ->where('active', false);
                    });
                }
            })
            ->get();
    }
}
