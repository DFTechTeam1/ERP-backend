<?php

namespace Modules\Production\Repository;

use App\Repository\BaseRepository;
use Modules\Production\Models\EntertainmentTask;

class EntertainmentTaskRepository extends BaseRepository
{
    public function __construct(EntertainmentTask $model)
    {
        return parent::__construct($model);
    }

    /**
     * Update or Create song items
     *
     * @param EntertainmentTask $task
     * @param array<int, int> $songIds
     * @return void
     */
    public function insertSongs(EntertainmentTask $task, array $songIds): void
    {
        foreach ($songIds as $songId) {
            $task->songItems()->updateOrCreate(
                ['entertainment_task_id' => $task->id, 'song_item_id' => $songId],
                ['entertainment_task_id' => $task->id, 'song_item_id' => $songId]
            );
        }
    }

    /**
     * Delete song items
     *
     * @param EntertainmentTask $task
     * @param array $songIds
     * @return void
     */
    public function deleteSongItems(EntertainmentTask $task, array $songIds): void
    {
        $task->songItems()->whereIn('song_item_id', $songIds)->delete();
    }
}
