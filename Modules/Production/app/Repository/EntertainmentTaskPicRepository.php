<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTaskPic;

class EntertainmentTaskPicRepository extends BaseRepository
{
    public function __construct(EntertainmentTaskPic $model)
    {
        return parent::__construct($model);
    }

    /**
     * Assign employees to selected task
     *
     * @param integer $taskId
     * @param array $employeeIds
     * @return void
     */
    public function assignEmployees(int $taskId, array $employeeIds): void
    {
        foreach ($employeeIds as $employeeId) {
            $this->query()
                ->updateOrCreate(
                    ['task_id' => $taskId],
                    ['employee_id' => $employeeId]
                );
        }
    }
}
