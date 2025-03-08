<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use App\Exceptions\DivisionException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Modules\Company\Repository\DivisionRepository;
use Modules\Company\Repository\PositionRepository;

class DivisionService
{
    private $repo;

    /**
     * @param $repo
     */
    public function __construct()
    {
        $this->repo = new DivisionRepository;
    }

    /**
     * Get list of data
     *
     * @param string $select
     * @param string $where
     * @param array $relation
     *
     * @return array
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ) : array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $search = request('search');

            if (!empty($search)) { // array
                $where = formatSearchConditions($search['filters'], $where);
            }

            $sort = "name asc";
            if (request('sort')) {
                $sort = "";
                foreach (request('sort') as $sortList) {
                    if ($sortList['field'] == 'name') {
                        $sort = $sortList['field'] . " {$sortList['order']},";
                    } else {
                        $sort .= "," . $sortList['field'] . " {$sortList['order']},";
                    }
                }

                $sort = rtrim($sort, ",");
                $sort = ltrim($sort, ',');
            }


            $divisions = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                [],
                $sort
            )->toArray();

            $paginated = [];
            foreach ($divisions as $division) {
                unset($division['parent_id']);
                unset($division['parent_division']['id']);

                $paginated[] = $division;
            }
            $totalData = $this->repo->list($select, $where, $relation)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'where' => $where
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function allDivisions()
    {
        $data = $this->repo->list('uid as value, name as title');

        return generalResponse(
            'success',
            false,
            $data->toArray()
        );
    }

    /**
     * Get specific data by id
     *
     * @param string $uid
     * @param string $select
     * @param array $relation
     *
     * @return array
     */
    public function show(
        string $uid,
        string $select = '*',
        array $relation = []
    ): array
    {
        try {
            $data = $this->repo->show($uid, $select, $relation);
            $data->makeHidden('id','parent_id');

            if ($data->parentDivision) $data->parentDivision->makeHidden('id');
            $data->childDivisions->each(function ($childDivision) {
                $childDivision->makeHidden(['id','parent_id']);
            });
            $data->positions->each(function ($positions) {
                $positions->makeHidden('division_id');
            });

            return generalResponse(
                'Success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage('message'),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Store data
     *
     * @param array $data
     *
     * @return array
     */
    public function store(array $data): array
    {
        try {
            if(isset($data['parent_id'])) {
                $division = $this->repo->show($data['parent_id'],'id');
                $data['parent_id'] = $division->id;
            }

            $this->repo->store($data)->toArray();

            return generalResponse(
                __("global.successCreateDivision"),
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
     * Update data
     *
     * @param string $uid
     * @param array $data
     * @return array
     */
    public function update(
        array $data,
        string $uid='',
        string $where=''
    ): array
    {
        try {
            if (isset($data['parent_id'])) {
                $division = $this->repo->show($data['parent_id'],'id');
                $data['parent_id'] = $division->id;
            }

            $this->repo->update($data,$uid);

            return generalResponse(
                __("global.successUpdateDivision"),
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
     * Delete specific data
     *
     * @param string $uid
     * @return array
     */
    public function delete(string $uid): array
    {
        try {
            $data = $this->repo->show($uid,'id,name,parent_id',['childDivisions:id,parent_id','positions:id,division_id'])->toArray();
            if(count($data['child_divisions']) > 0 || count($data['positions']) > 0) {
                throw new DivisionException(__("global.divisionRelationFound", ['name' => $data['name']]));
            }

            $this->repo->delete($uid);

            return generalResponse(
                __("global.successDeleteDivision"),
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

    public function bulkDelete(array $uids, string $key = ''): array
    {
        try {
            $errorDivisionStatus = false;

            foreach ($uids as $uid) {
                $data = $this->repo->show($uid['uid'],'id,name,parent_id',['childDivisions:id,parent_id','positions:id,division_id']);
                if($data->childDivisions->count() > 0 || $data->positions->count() > 0) {
                    $errorDivisionName[] = $data->name;
                    $errorDivisionStatus = true;
                }
            }

            if ($errorDivisionStatus) {
                throw new DivisionException(__("global.divisionRelationFound", ['name' => implode(', ', $errorDivisionName)]));
            }

            $this->repo->bulkDelete($uids,$key);

            return generalResponse(
                __("global.successDeleteDivision"),
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
