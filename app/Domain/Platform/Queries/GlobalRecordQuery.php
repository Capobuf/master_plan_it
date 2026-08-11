<?php

namespace App\Domain\Platform\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class GlobalRecordQuery
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return Builder<TModel>
     */
    public static function for(string $modelClass): Builder
    {
        $model = app($modelClass);

        return $model->newQuery();
    }
}
