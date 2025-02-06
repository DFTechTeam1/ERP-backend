<?php

namespace App\Actions\Project;

use App\Enums\Production\TaskSongStatus;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectSongListRepository;
use Modules\Production\Services\ProjectSongListService;

class UpdateSongList
{
    use AsAction;

    private $user;

    public function handle(int $projectId)
    {
        $this->user = auth()->user();

        $projectSongListRepo = new ProjectSongListRepository();

        $songs = $projectSongListRepo->list(
            select: 'uid,id,name,created_by,is_request_edit,is_request_delete',
            where: 'project_id = ' . $projectId,
            relation: [
                'task:id,project_song_list_id,employee_id,status',
                'task.employee:id,nickname,user_id'
            ]
        );

        $songs = collect((object) $songs)->map(function ($item) {
            $item = $this->formatSingleSongStatus($item);

            $disabled = false;
            if ($item->is_request_edit || $item->is_request_delete) {
                $disabled = true;
            }
            $item['disabled'] = $disabled;

            return $item;
        })->toArray();

        return $songs;
    }

    protected function formatSingleSongStatus(object $item)
    {
        $statusFormat = $item->task ? __('global.distributed') : __('global.waitingToDistribute');
        $statusColor = $item->task ? 'success': 'info';

        $statusRequest = null;
        if ($item->is_request_edit) {
            $statusRequest = __('global.songEditRequest');
        }

        if ($item->is_request_delete) {
            $statusRequest = __('global.songDeleteRequest');
        }

        $item['status_format'] = $statusFormat;
        $item['status_color'] = $statusColor;
        $item['status_request'] = $statusRequest;

        $item['status_of_work'] = !$item->task ? null : TaskSongStatus::getLabel($item->task->status);
        $item['status_of_work_color'] = !$item->task ? null : TaskSongStatus::getColor($item->task->status);

        $item['my_own'] = !$item->task ? false : ($item->task->employee->user_id == $this->user->id ? true : false);
        $item['need_to_be_done'] = !$item->task ? false : ($item->task->status == TaskSongStatus::OnProgress->value ? true : false);
        $item['need_worker_approval'] = !$item->task ? false : ($item->task->status == TaskSongStatus::Active->value && $item->task->employee->user_id == $this->user->id ? true : false);

        return $item;
    }

}
