<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Platform\Queries\PlatformOverviewQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlatformOverviewTenantResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PlatformOverviewController extends Controller
{
    public function __invoke(PlatformOverviewQuery $query): AnonymousResourceCollection
    {
        return PlatformOverviewTenantResource::collection($query->get());
    }
}
