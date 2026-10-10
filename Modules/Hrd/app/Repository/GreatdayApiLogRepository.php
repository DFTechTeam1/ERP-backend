<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\GreatdayApiLog;

class GreatdayApiLogRepository extends BaseRepository
{
    public function __construct(GreatdayApiLog $model)
    {
        return parent::__construct($model);
    }
}
