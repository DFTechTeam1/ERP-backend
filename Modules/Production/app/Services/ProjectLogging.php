<?php

namespace Modules\Production\Services;

use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectTaskLogRepository;
use stdClass;

class ProjectLogging {
    private $projectTaskLogRepository;

    private $employeeRepo;

    private $telegramEmployee;

    private $boardRepo;

    private $user;

    public function __construct(
        ProjectTaskLogRepository $projectTaskLogRepo,
        EmployeeRepository $employeeRepo,
        ProjectBoardRepository $boardRepo,
        $telegramEmployee = ''
    )
    {
        $this->boardRepo = $boardRepo;
        $this->projectTaskLogRepository = $projectTaskLogRepo;
        $this->employeeRepo = $employeeRepo;
        $this->telegramEmployee = empty($telegramEmployee) ? new stdClass : $telegramEmployee;
        $this->user = auth()->user();
    }

    /**
     * Create task log in every event
     *
     * @param array $payload
     * @param string $type
     * Type will be:
     * 1. moveTask
     * 2. addUser
     * 3. addNewTask
     * 4. addAttachment
     * 5. deleteAttachment
     * 6. changeTaskName
     * 7. addDescription
     * 8. updateDeadline
     * 9. assignMemberTask
     * 10. removeMemberTask
     * 11. deleteAttachment
     * @return void
     */
    public function loggingTask($payload, string $type)
    {
        $type .= "Log";
        return $this->{$type}($payload);
    }

    protected function startTaskLog($payload)
    {
        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'holdTask',
            'text' => __('global.actorStartTheTask', ['actor' => $payload['actor']]),
            'user_id' => $this->user->id,
        ]);
    }

    protected function holdTaskLog($payload)
    {
        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'holdTask',
            'text' => __('global.actorHoldTheTask', ['actor' => $payload['actor']]),
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when user add attachment
     *
     * @param array $payload
     * $payload will have
     * [int task_id, string media_name]
     * @return void
     */
    protected function addAttachmentLog($payload)
    {
        $text = __('global.addAttachmentLogText', [
            'name' => $this->user->username,
            'media' => $payload['media_name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'addAttachment',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when user delete attachment
     *
     * @param array $payload
     * $payload will have
     * [string task_uid, string media_name]
     * @return void
     */
    protected function deleteAttachmentLog($payload)
    {
        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $text = __('global.deleteAttachmentLogText', [
            'name' => $this->user->username,
            'media' => $payload['media_name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'addAttachment',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when remove member from selected task
     *
     * @param array $payload
     * $payload will have
     * [int task_id, string employee_uid]
     * @return void
     */
    protected function removeMemberTaskLog($payload)
    {
        $employee = $this->employeeRepo->show($payload['employee_uid'], 'id,name,nickname');
        $text = __('global.removedMemberLogText', [
            'removedUser' => $employee->nickname
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'assignMemberTask',
            'text' => $text,
            'user_id' => $this->user->id ?? 0,
        ]);
    }

    /**
     * Add log when add new member to task
     *
     * @param array $payload
     * $payload will have
     * [int task_id, string employee_uid]
     * @return void
     */
    protected function assignMemberTaskLog($payload)
    {
        $employee = $this->employeeRepo->show($payload['employee_uid'], 'id,name,nickname');
        $text = __('global.assignMemberLogText', [
            'assignedUser' => $employee->nickname
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'assignMemberTask',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when change task deadline
     *
     * @param array $payload
     * $payload will have
     * [string task_uid]
     * @return void
     */
    protected function updateDeadlineLog($payload)
    {
        $text = __('global.updateDeadlineLogText', [
            'name' => $this->user->username
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'updateDeadline',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when change task description
     *
     * @param array $payload
     * $payload will have
     * [string task_uid]
     * @return void
     */
    protected function addDescriptionLog($payload)
    {
        $text = __('global.updateDescriptionLogText', [
            'name' => $this->user->username
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'addDescription',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when change task name
     *
     * @param array $payload
     * $payload will have
     * [string task_uid]
     * @return void
     */
    protected function changeTaskNameLog($payload)
    {
        $text = __('global.changeTaskNameLogText', [
            'name' => $this->user->username
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'changeTaskName',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when create new task
     *
     * @param array $payload
     * $payload will have
     * [array board, int board_id, array task]
     * @return void
     */
    protected function addNewTaskLog($payload)
    {
        $board = $payload['board'];

        $text = __('global.addTaskText', [
            'name' => $this->user->username,
            'boardTarget' => $board['name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task']['id'],
            'type' => 'addNewTask',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Add log when moving a task
     *
     * @param array $payload
     * $payload will have
     * [array boards, collection task, int|string board_id, int|string task_id, int|string board_source_id]
     * @return void
     */
    protected function moveTaskLog($payload)
    {
        $nickname = $this->telegramEmployee ? $this->telegramEmployee->nickname : $this->user->username;
        // get source board
        $sourceBoard = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_source_id'];
        })->values();

        $boardTarget = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_id'];
        })->values();

        $text = __('global.moveTaskLogText', [
            'name' => $nickname, 'boardSource' => $sourceBoard[0]['name'], 'boardTarget' => $boardTarget[0]['name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'moveTask',
            'text' => $text,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Get list of boards to use in 'move to' action
     *
     * @param integer $boardId
     * @param string $projectUid
     * @return array
     */
    public function getMoveToBoards(int $boardId, string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        $data = $this->boardRepo->list('id,name', 'project_id = ' . $projectId . ' and id != ' . $boardId);

        $data = collect((object) $data)->map(function ($board) {
            return [
                'title' => $board->name,
                'value' => $board->id,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data,
        );
    }
}