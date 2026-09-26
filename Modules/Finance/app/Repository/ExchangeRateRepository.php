<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\ExchangeRate;

class ExchangeRateRepository extends BaseRepository
{
    public function __construct(ExchangeRate $model)
    {
        return parent::__construct($model);
    }
}
