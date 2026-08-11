<?php

namespace App\Domain\Audit\Queries;

use App\Domain\Platform\Queries\GlobalRecordQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AuditEventListQuery
{
    /** @return Builder<AuditEvent> */
    public function forTenant(Request $request, TenantContext $context): Builder
    {
        return $this->filters(TenantOwnedRecordQuery::forTenant($context, AuditEvent::class), $request);
    }

    /** @return Builder<AuditEvent> */
    public function forPlatform(Request $request, ?int $tenantId): Builder
    {
        return $this->filters(
            GlobalRecordQuery::for(AuditEvent::class)
                ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId)),
            $request,
        );
    }

    /**
     * @param  Builder<AuditEvent>  $query
     * @return Builder<AuditEvent>
     */
    private function filters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('event_type'), fn (Builder $query) => $query->where('event_type', $request->string('event_type')->toString()))
            ->when($request->integer('actor_id') > 0, fn (Builder $query) => $query->where('actor_user_id', $request->integer('actor_id')))
            ->when($request->filled('from'), fn (Builder $query) => $query->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('occurred_at', '<=', $request->date('to')))
            ->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
