<?php

namespace App\Actions\Project;

use App\Services\GeneralService;
use Exception;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskState;

class ProjectTaskStateEvent
{
    use AsAction;

    public function handle(string $projectUid, string $taskUid)
    {
        try {
            $generalService = new GeneralService();
            $projectId = $generalService->getIdFromUid($projectUid, new Project());
            $taskId = $generalService->getIdFromUid($taskUid, new ProjectTask());

            // get detail task and pics
            $task = ProjectTask::selectRaw('id,project_board_id,current_pics')
                ->find($taskId);

            foreach (json_decode($task->current_pics, true) as $pic) {
                ProjectTaskState::create([
                    'project_id' => $projectId,
                    'project_task_id' => $taskId,
                    'employee_id' => $pic,
                    'project_board_id' => $task->project_board_id
                ]);
            }
        } catch (\Throwable $th) {
            throw new Exception(errorMessage($th));
        }
    }
}
