<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Finance\Models\Account;

class AccountRepository extends BaseRepository
{
    public function __construct(Account $model)
    {
        return parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $params
     */
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
