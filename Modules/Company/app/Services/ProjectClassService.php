<?php

namespace Modules\Company\Services;

use App\Data\Company\ProjectClass\UpdateStatusData;
use Modules\Company\Models\ProjectClass;
use Modules\Company\Repository\ProjectClassRepository;

class ProjectClassService
{
    private $repo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new ProjectClassRepository;
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $select = 'id as uid,name,color,reward,pm_reward,vj_reward,is_active as status';

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

    public function getAll()
    {
        $data = $this->repo->list('id,name,maximal_point', 'is_active = 1');

        return generalResponse('success', false, $data->toArray());
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
            // maximal_point is a legacy, non-null column that the Create request no longer
            // collects (the module uses `reward` now), so default it to 0.
            $data['maximal_point'] = $data['maximal_point'] ?? 0;

            // pm_reward / vj_reward are optional on the request, so default them to 0 to keep an
            // explicit PM/VJ pot on every class.
            $data['pm_reward'] = $data['pm_reward'] ?? 0;
            $data['vj_reward'] = $data['vj_reward'] ?? 0;

            $created = $this->repo->store($data);

            return generalResponse(
                __('global.projectClassCreated'),
                false,
                $this->formatClass($created),
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
            $this->repo->update($data, $id, $where);

            // Return the saved class (incl. reward / pm_reward / vj_reward) so the management
            // interface can reflect the persisted values without a second request.
            $fetchWhere = ! empty($where) ? $where : "id = {$id}";
            $updated = $this->repo->list(
                'id,name,color,reward,pm_reward,vj_reward,is_active',
                $fetchWhere
            )->first();

            return generalResponse(
                __('global.projectClassUpdated'),
                false,
                $updated ? $this->formatClass($updated) : [],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Shape a project class for the management interface (matches the list columns).
     *
     * @return array<string, mixed>
     */
    protected function formatClass(ProjectClass $class): array
    {
        return [
            'uid' => $class->id,
            'name' => $class->name,
            'color' => $class->color,
            'reward' => $class->reward,
            'pm_reward' => $class->pm_reward,
            'vj_reward' => $class->vj_reward,
            'status' => $class->is_active,
        ];
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
            // validation relation
            foreach ($ids as $id) {
                $relation = $this->repo->show($id, 'id', ['project:id,project_class_id']);

                if ($relation->project) {
                    return generalResponse(
                        __('global.failedDeleteProjectClassBcsRelation'),
                        true,
                        [],
                        500,
                    );
                }
            }

            $this->repo->bulkDelete($ids, 'id');

            return generalResponse(
                __('global.successDeleteProjectClass'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function updateStatus(UpdateStatusData $payload, int $projectClassId): array
    {
        try {
            $this->repo->update([
                'is_active' => $payload->status,
            ], $projectClassId);

            return generalResponse(
                message: 'Success update project class status'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
