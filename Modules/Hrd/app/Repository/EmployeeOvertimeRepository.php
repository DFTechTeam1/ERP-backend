<?php

namespace Modules\Hrd\Repository;

use App\Repository\BaseRepository;
use Modules\Hrd\Models\EmployeeOvertime;

class EmployeeOvertimeRepository extends BaseRepository
{
    public function __construct(EmployeeOvertime $model)
    {
        return parent::__construct($model);
    }
}
