<?php

namespace Modules\Production\Services;

use App\Data\Production\Entertainment\CreateEntertainmentTaskData;
use App\Data\Production\Entertainment\CreateJumpBackData;
use App\Data\Production\Entertainment\CreateSongData;
use App\Data\Production\Entertainment\CreateWorkStateData;
use App\Data\Production\Entertainment\SongListData;
use App\Data\Production\Entertainment\UpdateSongData;
use App\Enums\Employee\Status;
use App\Enums\Production\Entertainment\TaskStatus;
use App\Enums\Production\Entertainment\TaskType;
use App\Exceptions\DataNotFound;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Models\EntertainmentTask;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSong;
use Modules\Production\Repository\EntertainmentTaskPicRepository;
use Modules\Production\Repository\EntertainmentTaskPicWorkstateRepository;
use Modules\Production\Repository\EntertainmentTaskRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectSongItemRepository;
use Modules\Production\Repository\ProjectSongRepository;

class EntertainmentService
{
    const SONG_LIST_CACHE_KEY = 'project_song_list';

    const TASK_LIST = 'task_list';

    public function __construct(
        private readonly ProjectSongRepository $projectSongRepo,
        private readonly ProjectSongItemRepository $projectSongItemRepo,
        private readonly GeneralService $generalService,
        private readonly EmployeeRepository $employeeRepo,
        private readonly ProjectRepository $projectRepo,
        private readonly EntertainmentTaskRepository $entertainmentTaskRepo,
        private readonly EntertainmentTaskPicRepository $taskPicRepo,
        private readonly EntertainmentTaskPicWorkstateRepository $workStateRepo,
        private readonly EntertainmentLogService $logService
    ) {}

    /**
     * Get song list cache identifier
     */
    public function getSongListCacheKey(string $projectUid): string
    {
        return self::SONG_LIST_CACHE_KEY."_{$projectUid}";
    }

    protected function resetSongListCache(string $projectUid)
    {
        Cache::forget($this->getSongListCacheKey($projectUid));
    }

    protected function appendSongToCache(string $projectUid)
    {
        $cache = Cache::get($this->getSongListCacheKey($projectUid));

        if ($cache) {
            // update song list

        }
    }

    /**
     * Get task cache identifier
     */
    public function getTaskCacheKey(string $projectUid): string
    {
        return self::TASK_LIST."_{$projectUid}";
    }

    public function storeTask() {}

    /**
     * Format project song list to serve API response
     */
    protected function formatSongList(ProjectSong $data): array
    {
        /** @var SongListData[] */
        $output = [];
        foreach ($data->items as $songList) {
            $output[] = new SongListData(
                uid: $songList->uid,
                name: $songList->song_name,
                group: $data->group_name,
                status: ! $songList->latestTask ? __('global.unassigned') : __('global.assigned'),
                status_color: ! $songList->latestTask ? 'grey' : 'green'
            );
        }

        return $output;
    }

    /**
     * Fetch main data for project song list
     */
    protected function fetchSongList(int $projectId): ?ProjectSong
    {
        return $this->projectSongRepo->show(
            uid: '',
            select: 'id,group_name,uid,project_id',
            relation: [
                'items:id,song_name,project_song_id,uid',
                'items.latestTask:entertainment_task_song_items.id,entertainment_task_song_items.song_item_id',
            ],
            where: "project_id = {$projectId}"
        );
    }

    /**
     * Cached the song list
     */
    protected function cacheSongList(string $projectUid): array
    {
        $projectId = getIdFromUid($projectUid, new Project);

        return Cache::remember(
            key: self::SONG_LIST_CACHE_KEY."_{$projectUid}",
            ttl: now()->addHours(2),
            callback: function () use ($projectId) {
                $data = $this->fetchSongList($projectId);

                /** @var SongListData[] */
                $output = [];
                if ($data) {
                    $output = $this->formatSongList(data: $data);
                }

                return $output;
            }
        );
    }

    /**
     * Get list of songs
     */
    public function list(string $projectUid): array
    {
        try {
            // Cache
            $songListCache = Cache::get(
                $this->getSongListCacheKey($projectUid)
            );

            /**
             * Create a cache if not exists
             */
            if (! $songListCache) {
                $songListCache = $this->cacheSongList($projectUid);
            }

            return generalResponse(
                message: 'Success',
                data: $songListCache
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Create new song group
     */
    public function createSong(CreateSongData $payload, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new Project);

            $actor = $this->generalService->me();

            collect($payload->groups)->each(function ($item) use ($projectId, $actor) {
                $groupName = strtolower($item->name);
                $group = $this->projectSongRepo->show(
                    uid: '',
                    select: 'id',
                    where: "lower(group_name) = '{$groupName}'"
                );

                if (! $group) {
                    $group = $this->projectSongRepo->store([
                        'project_id' => $projectId,
                        'group_name' => $item->name,
                        'created_by' => $actor->id,
                    ]);
                }

                $this->projectSongRepo->storeSongs(
                    groupId: $group->id,
                    songs: $item->songs,
                );
            });

            DB::commit();

            // Reset songs cache
            $this->resetSongListCache($projectUid);

            return generalResponse(
                message: 'Success create song',
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update song name in song list cache
     */
    protected function updateSongNameInCache(string $songUid, string $projectUid, string $updatedName): void
    {
        $cache = Cache::get($this->getSongListCacheKey($projectUid));

        if ($cache) {
            $updated = collect($cache)->map(function ($song) use ($songUid, $updatedName) {
                if ($song->uid === $songUid) {
                    $song->name = $updatedName;
                }

                return $song;
            })->all();

            Cache::set($this->getSongListCacheKey($projectUid), $updated, now()->addHours(2));
        }
    }

    protected function deleteSongFromCache(string $projectUid, string $songUid): void
    {
        $cache = Cache::get($this->getSongListCacheKey($projectUid));

        if ($cache) {
            $find = collect($cache)->search(function ($item) use ($songUid) {
                return $item->uid === $songUid;
            });

            if ($find !== false) {
                $updated = collect($cache)->forget($find);
                Cache::set($this->getSongListCacheKey($projectUid), $updated->values()->all(), now()->addHours(2));
            }
        }
    }

    /**
     * Update selected songe
     */
    public function updateSong(UpdateSongData $payload, string $projectUid, string $songUid): array
    {
        try {
            $this->projectSongItemRepo->update(
                data: [
                    'song_name' => $payload->song,
                ],
                id: $songUid,
            );

            // Update cache name
            $this->updateSongNameInCache(
                $songUid,
                $projectUid,
                $payload->song
            );

            return generalResponse(
                message: 'Success update song'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected song
     */
    public function deleteSong(string $projectUid, string $songUid): array
    {
        try {
            $song = $this->projectSongItemRepo->show(
                uid: $songUid,
                select: 'id',
                relation: [
                    'latestTask',
                ]
            );

            if (! $song) {
                return errorResponse(message: 'Song not found');
            }

            if ($song->latestTask) {
                return errorResponse(message: 'Failed to delete song. Please delete task related with this song.');
            }

            // No task here
            $song->delete();

            // Delete song from cache list
            $this->deleteSongFromCache($projectUid, $songUid);

            return generalResponse(
                message: 'Success delete song list'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function registerTask(CreateEntertainmentTaskData $payload, array $songIds = []): EntertainmentTask
    {
        $task = $this->entertainmentTaskRepo->store($payload->toArray());

        if (! empty ($songIds)) {
            $this->entertainmentTaskRepo->insertSongs(
                task: $task,
                songIds: $songIds
            );
        }

        return $task;
    }

    protected function startWorkState(
        array $employeeIds,
        EntertainmentTask $task,
        CreateWorkStateData $payload
    ): void
    {
        foreach ($employeeIds as $employeeId) {
            if (! $this->workStateRepo->getEmployeeState($employeeId, $task->id)) {
                $this->workStateRepo->store($payload->toArray());
            }
        }
    }
    
    /**
     * Create task for jump back -> Should have a song list here
     *
     * @param CreateJumpBackData $payload
     * @return array
     */
    public function createJumpBackTask(CreateJumpBackData $payload, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            // ------------- Validation and formatting --
            $project = $this->projectRepo->show(
                uid: $projectUid,
                select: 'id'
            );

            if (! $project) throw new DataNotFound(message: "Project not found");

            $employeeActiveStatus = Status::determineActiveStatus();
            $statusString = collect($employeeActiveStatus)->join(",");

            $formattedUids = "'" . collect($payload->assignee_uids)->join("','") . "'";
            $employees = $this->employeeRepo->list(
                select: 'id',
                where: "status IN ({$statusString}) and uid IN ({$formattedUids})"
            );

            if (count($employees) !== count($payload->assignee_uids)) {
                throw new DataNotFound(message: "Employee not found.");
            }

            $employeeIds = $employees->pluck('id');

            // -------------- Song validation and formatting
            $songUids = "'" . collect($payload->song_uids)->join("','") . "'";
            $songItems = $this->projectSongItemRepo->list(
                select: 'id',
                where: "uid IN ({$songUids})"
            );
            if ($songItems->count() !== count($payload->song_uids)) throw new DataNotFound("Song not found.");
            $songIds = $songItems->pluck("id");

            // ------------- process task --
            $task = $this->registerTask(
                new CreateEntertainmentTaskData(
                    project_id: $project->id,
                    type: TaskType::JumpBack,
                    name: $payload->name,
                    description: $payload->note ?? null,
                    deadline: date('Y-m-d H:i:s', strtotime($payload->due)),
                    status: TaskStatus::WaitingApproval
                ),
                $songIds->toArray()
            );

            // ------------ Assign employee to task --
            $this->taskPicRepo->assignEmployees(
                taskId: $task->id,
                employeeIds: $employeeIds->toArray()
            );

            DB::commit();

            return generalResponse(
                message: "Success",
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function createTask()
    {

    }

    public function listTask(string $projectUid)
    {
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project());

            $data = $this->entertainmentTaskRepo->get([
                'select' => ['id'],
                'where' => [
                    'project_id' => $projectId
                ]
            ]);

            return generalResponse(
                message: 'Successsss',
                data: $data->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
