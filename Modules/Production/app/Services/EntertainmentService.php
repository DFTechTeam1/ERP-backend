<?php

namespace Modules\Production\Services;

use App\Data\Production\Entertainment\CreateSongData;
use App\Data\Production\Entertainment\SongListData;
use App\Data\Production\Entertainment\UpdateSongData;
use App\Services\GeneralService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSong;
use Modules\Production\Repository\ProjectSongItemRepository;
use Modules\Production\Repository\ProjectSongRepository;

class EntertainmentService
{
    const SONG_LIST_CACHE_KEY = 'project_song_list';

    const TASK_LIST = 'task_list';

    public function __construct(
        private readonly ProjectSongRepository $projectSongRepo,
        private readonly ProjectSongItemRepository $projectSongItemRepo,
        private readonly GeneralService $generalService
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
}
