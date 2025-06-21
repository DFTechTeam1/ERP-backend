<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Repository\EmployeeTaskStateRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class SaveTaskState
{
    use AsAction;

    private $taskRepo;

    private $employeeStateRepo;

    protected function constructor()
    {
        $this->taskRepo = new ProjectTaskRepository;

        $this->employeeStateRepo = new EmployeeTaskStateRepository;
    }

    public function handle(array $currentPicIds, string $taskUid)
    {
        $this->constructor();

        // get task detail
        $task = $this->taskRepo->show(
            uid: $taskUid,
            select: 'id,current_pics,project_id,project_board_id'
        );

        // insert to state table
        foreach ($currentPicIds as $pic) {
            $this->employeeStateRepo->updateOrInsert(
                key: [
                    'project_id' => $task->project_id,
                    'project_task_id' => $task->id,
                    'project_board_id' => $task->project_board_id,
                    'employee_id' => $pic,
                ],
                updatedValue: []
            );
        }
    }
}
