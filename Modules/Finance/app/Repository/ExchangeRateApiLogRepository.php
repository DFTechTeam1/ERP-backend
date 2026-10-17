<?php

namespace Modules\Finance\Repository;

use App\Repository\BaseRepository;
use Modules\Finance\Models\ExchangeRateApiLog;

class ExchangeRateApiLogRepository extends BaseRepository
{
    public function __construct(ExchangeRateApiLog $model)
    {
        return parent::__construct($model);
    }
}
