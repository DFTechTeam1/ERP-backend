<?php

namespace Modules\Hrd\Repository;

use App\Enums\Hrd\Signature\GenerateDocument\AssignTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\Interface\EmployeeInterface;

class EmployeeRepository extends EmployeeInterface
{
    private $model;

    private $key;

    /**
     * @param  $model
     * @param  $key
     */
    public function __construct()
    {
        $this->model = new Employee;
        $this->key = 'uid';
    }

    /**
     * Get All Data
     *
     * @return Collection
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = [],
        string $orderBy = '',
        string $limit = '',
        array $whereHas = [],
        array $whereIn = [],
        array $whereHasNested = []
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if (count($whereHas) > 0) {
            foreach ($whereHas as $wh) {
                $query->whereHas($wh['relation'], function (Builder $query) use ($wh) {
                    $query->whereRaw($wh['query']);
                });
            }
        }

        if ($whereIn) {
            $query->whereIn($whereIn['key'], $whereIn['value']);
        }

        if (! empty($whereHasNested)) {
            applyNestedWhereHas($query, $whereHasNested);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (! empty($orderBy)) {
            $query->orderByRaw($orderBy);
        }

        if (! empty($limit)) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Make paginated data
     *
     * @return Builder[]|Collection
     */
    public function pagination(
        string $select,
        string $where,
        array $relation,
        int $itemsPerPage,
        int $page,
        array $whereHas = [],
        string $orderBy = ''
    ) {
        $query = $this->model->query();

        $query->selectRaw($select);

        if (! empty($where)) {
            $query->whereRaw($where);
        }

        if ($relation) {
            $query->with($relation);
        }

        if (! empty($orderBy)) {
            $query->orderByRaw($orderBy);
        } else {
            $query->orderBy('updated_at', 'DESC');
        }

        return $query->skip($page)->take($itemsPerPage)->get();
    }

    /**
     * Get Detail Data
     *
     * @return Collection
     */
    public function show(string $uid, string $select = '*', array $relation = [], string $where = '')
    {
        $query = $this->model->query();

        $query->selectRaw($select);

        if ($relation) {
            $query->with($relation);
        }

        if (empty($where)) {
            $query->where('uid', $uid);
        } else {
            $query->whereRaw($where);
        }

        return $query->first();
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
     * @return Collection
     */
    public function update(array $data, string $uid = '', string $where = '')
    {
        $query = $this->model->query();

        if (! empty($where)) {
            $query->whereRaw($where);
        } else {
            $query->where('uid', $uid);
        }

        $model = $query->first();
        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * Delete Data
     *
     * @return Collection
     */
    public function delete(string $uid)
    {
        return $this->model->where('uid', $uid)->delete();
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

        return $this->model->whereIn($key, $ids)
            ->delete();
    }

    public function getTotalActiveEmployee()
    {
        return $this->model
            ->select(['id'])
            ->activeEmployee()->count();
    }

    public function getActiveProjectManager(array $positionIds)
    {
        return $this->model
            ->select(['uid', 'name', 'position_id', 'avatar_color'])
            ->with([
                'position:id,name',
            ])
            ->projectManagers($positionIds)
            ->get();
    }

    /**
     * Fetch the active employees a signature document should be disbursed to.
     *
     * The audience is derived from the {@see AssignTo} target: every active employee,
     * every active employee whose position belongs to a division, or every active
     * employee holding a specific position.
     *
     * @param  array<int, string>  $select  Columns to select (must include `id`)
     * @param  int|null  $divisionId  Internal division id, required when targeting a division
     * @param  int|null  $positionId  Internal position id, required when targeting a position
     * @return Collection<int, Employee>
     */
    public function getForSignatureDisbursement(
        array $select,
        AssignTo $assignTo,
        ?int $divisionId = null,
        ?int $positionId = null
    ): Collection {
        $query = $this->model->newQuery()
            ->select($select)
            ->activeEmployee();

        if ($assignTo === AssignTo::Position) {
            $query->where('position_id', $positionId);
        }

        if ($assignTo === AssignTo::Division) {
            $query->whereHas('position', function (Builder $subQuery) use ($divisionId) {
                $subQuery->where('division_id', $divisionId);
            });
        }

        return $query->get();
    }
}
