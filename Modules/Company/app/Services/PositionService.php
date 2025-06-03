<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use App\Exceptions\PositionException;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\PositionRepository;

class PositionService
{
    private $repo;

    private $divisionRepo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new PositionRepository;
        $this->divisionRepo = new DivisionRepository;
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

            if (! empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = 'name asc';
            if (request('sort')) {
                $sort = '';
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field']." {$sortList['order']},";
                    } else {
                        $sort .= ','.$sortList['field']." {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ',');
                $sort = ltrim($sort, ',');
            }

            $positions = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                [],
                $sort
            )->toArray();

            $paginated = [];
            foreach ($positions as $position) {
                unset($position['division_id']);
                unset($position['division']['id']);

                $paginated[] = $position;
            }
            $totalData = $this->repo->list('id', $where, $relation)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Function to get all position without pagination
     *
     * @return array
     */
    public function getAll(string $select = 'id,uid,name', string $where = '')
    {
        $search = request('search');
        if ($search) {
            $where = "LOWER(name) LIKE '%{$search}%'";
        }
        $data = $this->repo->list($select, $where);

        $data = collect((object) $data)->map(function ($item) {
            return [
                'title' => $item->name,
                'value' => $item->uid,
            ];
        })->toArray();

        return generalResponse(
            'Success',
            false,
            $data,
        );
    }

    /**
     * Get specific data by id
     */
    public function show(
        string $uid,
        string $select = '*',
        array $relation = []
    ): array {
        try {
            $data = $this->repo->show($uid, $select, $relation);
            $data->makeHidden('id', 'division_id');
            $data->division->makeHidden('id');
            $data->employees->makeHidden('position_id');
            // $data->jobs->makeHidden('position_id');

            return generalResponse(
                'Success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    public function datatable()
    {
        //
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        try {
            $data['division_id'] = getIdFromUid($data['division_id'], new DivisionBackup);

            $this->repo->store($data);

            return generalResponse(
                __('global.successCreatePosition'),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Update selected data
     */
    public function update(
        array $data,
        string $uid = '',
        string $where = ''
    ): array {
        try {
            if (isset($data['division_id'])) {
                $division = $this->divisionRepo->show($data['division_id'], 'id')->toArray();
                $data['division_id'] = $division['id'];
            }

            $this->repo->update($data, $uid);

            return generalResponse(
                __('global.successUpdatePosition'),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Delete selected data
     */
    public function delete(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'id,name', [
                'employees:id,position_id,name',
            ]);

            $positionErrorStatus = false;

            if ($data->employees->count() > 0) {
                $positionErrorRelation[] = 'employees';
                $positionErrorStatus = true;
            }

            if ($positionErrorStatus) {
                throw new PositionException(__('global.positionRelationFound', [
                    'name' => $data->name,
                    'relation' => implode(' and ', $positionErrorRelation),
                ]));
            }

            $this->repo->delete($uid);

            return generalResponse(
                __('global.successDeletePosition'),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Delete bulk data
     *
     * @param  array  $ids
     */
    public function bulkDelete(array $uids, string $key): array
    {
        try {
            $positionErrorStatus = false;

            foreach ($uids as $uid) {
                $data = $this->repo->show($uid['uid'], 'id,name', [
                    'employees:id,position_id,name',
                ]);

                if ($data->employees->count() > 0) {
                    $positionErrorName[] = $data->name;
                    $positionErrorRelation[] = 'employees';
                    $positionErrorStatus = true;
                }
            }

            if ($positionErrorStatus) {
                throw new PositionException(__('global.positionRelationFound', [
                    'name' => implode(', ', array_unique($positionErrorName)),
                    'relation' => implode(' and ', array_unique($positionErrorRelation)),
                ]));
            }
            $this->repo->bulkDelete($uids, $key);

            return generalResponse(
                __('global.successDeletePosition'),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }
}
