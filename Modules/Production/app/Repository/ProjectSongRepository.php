<?php

namespace Modules\Production\Repository;

use Illuminate\Database\Eloquent\Collection;
use Modules\Production\Models\ProjectSong;
use Modules\Production\Models\ProjectSongItem;
use Modules\Production\Repository\Interface\ProjectSongInterface;
use Ramsey\Uuid\Uuid;

class ProjectSongRepository extends ProjectSongInterface
{
    private $model;

    private $key;

    public function __construct()
    {
        $this->model = new ProjectSong;
        $this->key = 'id';
    }

    /**
     * Get All Data
     *
     * @return Collection
     */
    public function list(string $select = '*', string $where = '', array $relation = [])
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        return $query->get();
    }

    /**
     * Store detail of songs in each group
     *
     * @param integer $groupId
     * @param array $songs
     */
    public function storeSongs(int $groupId, array $songs)
    {
        $payload = collect($songs)->map(function ($song) use ($groupId) {
            return [
                'project_song_id' => $groupId,
                'song_name' => $song,
                'uid' => Uuid::uuid4()->toString()
            ];
        })->toArray();

        return ProjectSongItem::upsert(
            $payload,
            ['project_song_id', 'song_name'],
            ['song_name']
        );
    }

    /**
     * Paginated data for datatable
     *
     * @return Collection
     */
    public function pagination(
        string $select,
        string $where,
        array $relation,
        int $itemsPerPage,
        int $page
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Get Detail Data
     *
     * @return ProjectSong
     */
    public function show(string $uid, string $select = '*', array $relation = [], string $where = '')
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (empty($where)) {
            $query->where('uid', $uid);
        } else {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        $data = $query->first();

        return $data;
    }

    /**
     * Store Data
     *
     * @return Collection
     */
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update Data
     *
     * @param  int|string  $id
     * @return Collection
     */
    public function update(array $data, string $id = '', string $where = '')
    {
        $query = $this->model->query();

        if (! empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('uid', $id);
        }

        $query->update($data);

        return $query;
    }

    /**
     * Delete Data
     *
     * @param  int|string  $id
     * @return Collection
     */
    public function delete(int $id)
    {
        return $this->model->whereIn('id', $id)
            ->delete();
    }

    /**
     * Bulk Delete Data
     *
     * @return Collection
     */
    public function bulkDelete(array $ids, string $key = '')
    {
        if (empty($key)) {
            $key = $this->key;
        }

        return $this->model->whereIn($key, $ids)->delete();
    }
}
