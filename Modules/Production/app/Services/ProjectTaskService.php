<?php

namespace Modules\Production\Services;

use Modules\Production\Repository\ProjectTaskRepository;

class ProjectTaskService
{
    private $repo;

    /**
     * Construction Data
     */
    public function __construct(
        ProjectTaskRepository $projectTaskRepo
    ) {
        $this->repo = $projectTaskRepo;
    }

    public function massUpdateIdentifierID()
    {
        $tasks = $this->repo->list(select: 'uid,task_identifier_id');

        foreach ($tasks as $task) {
            if (! $task->task_identifier_id) {
                $this->repo->update(
                    data: ['task_identifier_id' => $this->generateIdentifier()],
                    id: $task->uid,
                );
            }
        }
    }

    /**
     * Generate random task_identifier_id for each task
     */
    public function generateIdentifier(): string
    {
        $random = generateRandomPassword(4);

        // check unique
        $finalRandomText = $this->checkCurrentIdentifier(identifier: $random);

        return $finalRandomText;
    }

    /**
     * Make sure task_identifier_id is unique
     */
    protected function checkCurrentIdentifier(string $identifier): string
    {
        $current = $this->repo->show(
            uid: 'id',
            select: 'id',
            where: "task_identifier_id = '{$identifier}'"
        );

        if ($current) {
            $identifier = generateRandomPassword(4);
        }

        return $identifier;
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
