<?php

namespace App\Actions\Project\Task;

use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectTaskLogRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskRepository;

class ChangeTaskBoard
{
    use AsAction;

    private readonly ProjectBoardRepository $boardRepo;

    private readonly ProjectTaskPicRepository $taskPicRepo;

    private readonly ProjectTaskRepository $taskRepo;

    private readonly ProjectTaskLogRepository $projectTaskLogRepository;

    protected function registerService()
    {
        $this->boardRepo = app(ProjectBoardRepository::class);
        $this->taskPicRepo = app(ProjectTaskPicRepository::class);
        $this->taskRepo = app(ProjectTaskRepository::class);
        $this->projectTaskLogRepository = app(ProjectTaskLogRepository::class);
    }

    public function handle(array $data, string $nextTaskStatus = '', bool $setCurrentPic = false)
    {
        $this->registerService();

        $taskId = $data['task_id'];

        $boardIds = [$data['board_id'], $data['board_source_id']];
        $boards = $this->boardRepo->list('id,name,based_board_id', 'id IN ('.implode(',', $boardIds).')');

        $payloadUpdate = [
            'project_board_id' => $data['board_id'],
            'current_board' => $data['board_source_id'],
        ];

        if (! empty($nextTaskStatus)) {
            $payloadUpdate['status'] = $nextTaskStatus;
        }

        if ($setCurrentPic) {
            $currentPics = $this->taskPicRepo->list('employee_id', 'project_task_id = '.$taskId);
            $payloadUpdate['current_pics'] = json_encode(collect($currentPics)->pluck('employee_id')->toArray());
        }

        $this->taskRepo->update($payloadUpdate, '', 'id = '.$taskId);

        // logging
        $this->writeLog(
            [
                'task_id' => $taskId,
                'boards' => $boards,
                'board_id' => $data['board_id'],
                'board_source_id' => $data['board_source_id'],
            ],
        );
    }

    protected function writeLog(array $payload)
    {
        $nickname = Auth::user()->username;
        // get source board
        $sourceBoard = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_source_id'];
        })->values();

        $boardTarget = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_id'];
        })->values();

        $text = __('global.moveTaskLogText', [
            'name' => $nickname,
            'boardSource' => $sourceBoard[0]['name'],
            'boardTarget' => $boardTarget[0]['name'],
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'moveTask',
            'text' => $text,
            'user_id' => Auth::id(),
        ]);
    }
}
