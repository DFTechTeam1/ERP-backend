<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Finance\Models\CostCenter;
use Override;

class CostCenterRepository extends BaseRepository
{
    public function __construct(CostCenter $model)
    {
        return parent::__construct($model);
    }

    public function applyParams(Builder $query, array $params): Builder
    {
        $query = parent::applyParams($query, $params);

        if (isset($params['whereLike'])) {
            foreach ($params['whereLike'] as $likeKey => $likeValue) {
                $query->whereLike($likeKey, $likeValue);
            }
        }

        return $query;
    }
}
