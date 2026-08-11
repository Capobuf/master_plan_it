<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Queries\AuditEventListQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditEventResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditController extends Controller
{
    public function tenant(Request $request, AuditEventListQuery $query): AnonymousResourceCollection
    {
        return AuditEventResource::collection($query->forTenant($request, $this->tenantContext($request))
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))->withQueryString());
    }

    public function platform(Request $request, AuditEventListQuery $query): AnonymousResourceCollection
    {
        $tenantId = $request->integer('tenant_id');

        return AuditEventResource::collection($query->forPlatform($request, $tenantId > 0 ? $tenantId : null)
            ->paginate(min(max($request->integer('per_page', 25), 1), 100))->withQueryString());
    }
}
