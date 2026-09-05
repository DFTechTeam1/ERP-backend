<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmployeeReward;

class EmployeeRewardRepository extends BaseRepository
{
    public function __construct(EmployeeReward $model)
    {
        return parent::__construct($model);
    }
}
