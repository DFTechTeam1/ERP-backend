<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Finance\Models\Currency;

class CurrencyRepository extends BaseRepository
{
    public function __construct(Currency $model)
    {
        return parent::__construct($model);
    }

    public function showByUid(string $uid): ?Currency
    {
        return parent::show([
            'where' => [
                'uid' => $uid
            ]
        ]);
    }

    public function applyParams(Builder $query, array $params): Builder
    {
        $query = parent::applyParams($query, $params);

        if (isset($params['whereLike'])) {
            $a = 0;
            foreach ($params['whereLike'] as $key => $value) {
                $operator = 'whereLike';
                if ($a > 0) {
                    $operator = 'orWhereLike';
                }
                $query->$operator($key, $value);
                $a++;
            }
        }

        if (isset($params['whereNotNull'])) {
            foreach ($params['whereNotNull'] as $key) {
                $query->whereNotNull($key);
            }
        }

        return $query;
    }
}
