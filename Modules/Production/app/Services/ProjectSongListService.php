<?php

namespace Modules\Production\Services;

use App\Enums\Production\Entertainment\TaskSongLogType;
use App\Services\GeneralService;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Jobs\SongApprovedToBeEditedJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectSongListRepository;

class ProjectSongListService
{
    private $repo;

    private $projectService;

    private $generalService;

    private $employeeRepo;

    private $entertainmentTaskSongLogService;

    private $projectRepo;

    /**
     * Construction Data
     */
    public function __construct(
        ProjectSongListRepository $repo,
        ProjectService $projectService,
        GeneralService $generalService,
        EmployeeRepository $employeeRepo,
        EntertainmentTaskSongLogService $entertainmentTaskSongLogService,
        ProjectRepository $projectRepo
    ) {
        $this->repo = $repo;

        $this->projectService = $projectService;

        $this->generalService = $generalService;

        $this->employeeRepo = $employeeRepo;

        $this->entertainmentTaskSongLogService = $entertainmentTaskSongLogService;

        $this->projectRepo = $projectRepo;
    }

    /**
     * Function to approve edit request
     * This function will notify the changes to PM project and current worker
     */
    public function confirmEditSong(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $detail = $this->repo->show(
                uid: $songUid,
                select: 'id,name,target_name',
                relation: [
                    'task:id,project_song_list_id,employee_id',
                ]
            );

            $currentWorkerId = $detail->task->employee_id;
            $currentName = $detail->name;
            $newName = $detail->target_name;

            $this->doEditSong(
                payload: [
                    'name' => $detail->target_name,
                    'target_name' => null,
                    'is_request_edit' => false,
                    'is_request_delete' => false,
                ],
                songUid: $songUid
            );

            // logging task
            $user = $this->employeeRepo->show(
                uid: 'id',
                select: 'id,nickname',
                where: 'user_id = '.auth()->id()
            );

            $event = $this->projectRepo->show(
                uid: $projectUid,
                select: 'id,name'
            );

            $this->entertainmentTaskSongLogService->storeLog(
                type: TaskSongLogType::ApprovedRequestEdit->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                    'employee_id' => null,
                ],
                params: [
                    'pm' => $user->nickname ?? 'Unknown',
                    'event' => $event->name,
                    'currentName' => $currentName,
                    'newName' => $newName,
                ]
            );

            $currentData = $this->projectService->renewCache($projectUid);

            SongApprovedToBeEditedJob::dispatch($currentName, $newName, $currentWorkerId, $projectId)->afterCommit();

            DB::commit();

            return generalResponse(
                message: 'success',
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update song
     */
    public function doEditSong(array $payload, string $songUid): bool
    {
        $this->repo->update($payload, $songUid);

        return true;
    }

    /**
     * Get list of data
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function formatSingleSongStatus(object $item)
    {
        $statusFormat = $item->task ? __('global.distributed') : __('global.waitingToDistribute');
        $statusColor = $item->task ? 'success' : 'info';

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

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'name,uid,id');

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     */
    public function bulkDelete(array $ids): array
    {
        try {
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
