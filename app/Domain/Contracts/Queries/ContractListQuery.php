<?php

namespace App\Domain\Contracts\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Contract;
use App\Models\User;
use App\Policies\ContractPolicy;
use Illuminate\Pagination\LengthAwarePaginator;

final class ContractListQuery
{
    /** @return LengthAwarePaginator<int, Contract> */
    public function paginate(User $actor, TenantContext $context, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        app(ContractPolicy::class)->viewAny($actor)->authorize();
        return TenantOwnedRecordQuery::forTenant($context, Contract::class)
            ->with(['vendor:id,name', 'costCenter:id,name', 'terms'])
            ->withCount(['terms', 'expenses as generated_expenses_count' => fn ($query) => $query->whereHas('rows', fn ($rows) => $rows->whereNotNull('source_key'))])
            ->orderBy('title')
            ->paginate($perPage, ['*'], 'page', max(1, $page));
    }
}
