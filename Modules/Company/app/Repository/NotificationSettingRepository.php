<?php

namespace Modules\Company\Repository;

use Modules\Company\Models\NotificationSetting;
use Modules\Company\Repository\Interface\NotificationSettingInterface;

class NotificationSettingRepository extends NotificationSettingInterface
{
    private NotificationSetting $model;

    public function __construct()
    {
        $this->model = new NotificationSetting;
    }

    /**
     * Get all rows matching an optional raw where clause.
     */
    public function list(string $select = '*', string $where = '', array $relation = []): mixed
    {
        $query = $this->model->query()->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        return $query->get();
    }

    /**
     * Paginated slice for the datatable. $page is already the row offset.
     */
    public function pagination(string $select, string $where, int $itemsPerPage, int $page, string $orderBy = ''): mixed
    {
        $query = $this->model->query()->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (! empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Count rows matching an optional raw where clause (for totalData).
     */
    public function count(string $where = ''): int
    {
        $query = $this->model->query();

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        return $query->count();
    }

    /**
     * Get one row by primary key.
     */
    public function show(int $id, string $select = '*'): ?NotificationSetting
    {
        return $this->model->query()->selectRaw($select)->where('id', $id)->first();
    }

    /**
     * Create a row. Uses create() so the model's json attribute mutators run.
     */
    public function store(array $data): NotificationSetting
    {
        return $this->model->create($data);
    }

    /**
     * Update a row through the model instance so the json attribute mutators run
     * (a query-builder mass update would bypass them and store raw arrays).
     */
    public function update(array $data, int $id): ?NotificationSetting
    {
        $model = $this->model->query()->where('id', $id)->first();

        if (! $model) {
            return null;
        }

        $model->update($data);

        return $model->refresh();
    }

    /**
     * Delete a row by primary key.
     */
    public function delete(int $id): bool
    {
        return (bool) $this->model->query()->where('id', $id)->delete();
    }
}
