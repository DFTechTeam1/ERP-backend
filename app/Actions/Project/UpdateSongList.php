<?php

namespace App\Actions\Project;

use App\Enums\Production\TaskSongStatus;
use App\Enums\System\BaseRole;
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
                'task.employee:id,nickname,user_id',
                'task.results:id,task_id,nas_path,note',
                'task.results.images:id,result_id,path'
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

        if (!$item->task) {
            $item['status_text'] = $statusFormat;
            $item['status_color'] = $statusColor;
        } else {
            $item['status_text'] = $item->task->status_text;
            $item['status_color'] = $item->task->status_color;
        }

        $statusRequest = null;
        if ($item->is_request_edit) {
            $statusRequest = __('global.songEditRequest');
        }

        if ($item->is_request_delete) {
            $statusRequest = __('global.songDeleteRequest');
        }

        $item['status_request'] = $statusRequest;

        // override all action for root
        $admin = $this->user->hasRole(BaseRole::Root->value);
        $director = $this->user->hasRole(BaseRole::Director->value);
        $entertainmentPm = $this->user->hasRole(BaseRole::ProjectManagerEntertainment->value);

        $item['status_of_work'] = !$item->task ? null : TaskSongStatus::getLabel($item->task->status);
        $item['status_of_work_color'] = !$item->task ? null : TaskSongStatus::getColor($item->task->status);

        $item['my_own'] = $admin || $director || $entertainmentPm ?
            true :
            (
                !$item->task ?
                false :
                (
                    $item->task->employee->user_id == $this->user->id ?
                    true :
                    false
                )
            ); // override permission for root, director and project manager
        $item['need_to_be_done'] = !$item->task ? false : ($item->task->status == TaskSongStatus::OnProgress->value ? true : false);
        $item['need_worker_approval'] = !$item->task ?
            false :
            (
                $item->task->status == TaskSongStatus::Active->value || $item->task->status == TaskSongStatus::Revise->value && ($item->task->employee->user_id == $this->user->id || $admin || $director || $entertainmentPm) ?
                true :
                false
            );

        // permission
        $canEdit = $this->user->hasPermissionTo('edit_request_song') ? true : false;
        $canDelete = $this->user->hasPermissionTo('delete_request_song') ? true : false;
        $canDistribute =!$item->task && $this->user->hasPermissionTo('distribute_request_song') ? true : false;
        $canStartWork = ($this->user->hasPermissionTo('song_proof_of_work') && $item['my_own'] && $item['need_worker_approval'] && !$item['need_to_be_done']) ? true : false;
        $canApproveWork = ($this->user->hasPermissionTo('song_proof_of_work') && $item['my_own'] && $item['need_to_be_done']) ? true : false;
        $item['can_edit'] = $canEdit;
        $item['can_delete'] = $canDelete;
        $item['can_distribute'] = $canDistribute;
        $item['can_start_work']= $canStartWork;
        $item['can_approve_work'] = $canApproveWork;

        return $item;
    }

}
