<?php

namespace App\Domain\Tenancy\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TenantOwnedRecordQuery
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return Builder<TModel>
     */
    public static function forTenant(TenantContext $context, string $modelClass): Builder
    {
        $model = app($modelClass);

        return $model->newQuery()->where($model->qualifyColumn('tenant_id'), $context->tenantId);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public static function findOrFail(TenantContext $context, string $modelClass, mixed $key): Model
    {
        return self::forTenant($context, $modelClass)
            ->whereKey($key)
            ->firstOrFail();
    }
}
