<?php

namespace App\Actions\Project\Task;

use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectTaskLogRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskPicWorkstateRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class DetachTaskPic
{
    use AsAction;

    private readonly ProjectTaskPicRepository $taskPicRepo;

    private readonly EmployeeRepository $employeeRepo;

    private readonly ProjectTaskPicWorkstateRepository $projectTaskWorkStateRepo;

    private readonly ProjectTaskRepository $taskRepo;

    private readonly ProjectTaskPicHistoryRepository $taskPicHistory;

    private readonly ProjectTaskLogRepository $projectTaskLogRepository;

    protected function registerService(): void
    {
        $this->taskPicRepo = app(ProjectTaskPicRepository::class);
        $this->employeeRepo = app(EmployeeRepository::class);
        $this->projectTaskWorkStateRepo = app(ProjectTaskPicWorkstateRepository::class);
        $this->taskRepo = app(ProjectTaskRepository::class);
        $this->taskPicHistory = app(ProjectTaskPicHistoryRepository::class);
        $this->projectTaskLogRepository = app(ProjectTaskLogRepository::class);
    }

    public function handle(
        array $ids,
        int $taskId,
        bool $isEmployeeUid = true,
        bool $removeFromHistory = false,
        string $message = '',
        bool $doLogging = true, // sometime we don't need to log the action
        bool $removeWorkState = true,
        bool $removeCurrentPic = true)
    {
        $this->registerService();

        foreach ($ids as $removedUser) {
            if ($isEmployeeUid) {
                $removedEmployeeId = getIdFromUid($removedUser, new Employee);
            } else {
                $removedEmployeeId = $removedUser;
            }

            // delete from table task_pics
            $this->taskPicRepo->deleteWithCondition('employee_id = '.$removedEmployeeId.' AND project_task_id = '.$taskId);

            // delete from history
            if ($removeFromHistory) {
                // delete from table task_pic_histories
                $this->taskPicHistory->deleteWithCondition('employee_id = '.$removedEmployeeId.' AND project_task_id = '.$taskId);
            }

            $employee = $this->employeeRepo->show('id', 'id,name,nickname', [], 'id = '.$removedEmployeeId);

            // remove workstate
            if ($removeWorkState) {
                $this->projectTaskWorkStateRepo->delete(id: 0, where: "task_id = {$taskId} AND employee_id = {$removedEmployeeId} and complete_at IS NULL");
            }

            // Remove employee id from current_pics in project_tasks table
            if ($removeCurrentPic) {
                $currentTask = $this->taskRepo->show(
                    uid: 'uid',
                    select: 'id,current_pics',
                    where: "id = {$taskId}"
                );
                $currentPics = json_decode($currentTask->current_pics, true) ?? [];
                if (in_array($removedEmployeeId, $currentPics)) {
                    $updatedPics = array_values(array_diff($currentPics, [$removedEmployeeId]));
                    $this->taskRepo->update(
                        data: [
                            'current_pics' => json_encode($updatedPics),
                        ],
                        where: "id = {$taskId}"
                    );
                }
            }

            $logMessage = __('global.removedMemberLogText', [
                'removedUser' => $employee->nickname,
            ]);
            if (! empty($message)) {
                $logMessage = $message;
            }

            if ($doLogging) {
                $this->writeLog([
                    'task_id' => $taskId,
                    'employee_uid' => $removedUser,
                    'message' => $logMessage,
                ]);
            }
        }
    }

    protected function writeLog(array $payload)
    {
        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'assignMemberTask',
            'text' => $payload['message'],
            'user_id' => Auth::id() ?? 0,
        ]);
    }
}
