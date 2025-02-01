<?php

namespace App\Actions\Project;

use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Production\Repository\ProjectSongListRepository;
use Modules\Production\Services\ProjectSongListService;

class UpdateSongList
{
    use AsAction;

    public function handle(int $projectId)
    {
        $projectSongListRepo = new ProjectSongListRepository();

        $songs = $projectSongListRepo->list(
            select: 'uid,id,name,created_by,is_request_edit,is_request_delete',
            where: 'project_id = ' . $projectId,
            relation: [
                'task:id,project_song_list_id,employee_id',
                'task.employee:id,nickname'
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

        return $item;
    }

}
