<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskPicWorkstate;

class EntertainmentTaskPicWorkstateRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskPicWorkstate $model)
    {
        return parent::__construct($model);
    }

    public function getEmployeeState(int $employeeId, int $taskId): ?EntertainmentTaskPicWorkstate
    {
        return $this->query()
            ->select('id')
            ->where('employee_id', $employeeId)
            ->where('task_id', $taskId)
            ->first();
    }
}
