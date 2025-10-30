<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\Auth;
use Modules\Company\Repository\CountryRepository;
use Modules\Company\Repository\StateRepository;

class StateService {
    /**
     * Construction Data
     */
    public function __construct(
        private readonly StateRepository $repo,
        private readonly CountryRepository $countryRepo
    )
    {
        //
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
    ): array
    {
        try {
            $user = Auth::user();
            $user = (new \App\Repository\UserRepository)->detail(id: $user->id, select: 'id');

            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $country = request('country');
            $name = request('name');

            $where = '1 = 1';

            if (!empty($search)) {
                $where = "and lower(name) LIKE '%{$search}%'";
            }

            if (!empty($name)) {
                $where .= " and lower(name) LIKE '%" . strtolower($name) . "%'";
            }

            if (!empty($country)) {
                $countryId = collect($country)->implode(',');
                $where .= " and country_id IN ({$countryId})";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();

            $paginated = $paginated->map(function ($state) use ($user) {
                $state['country_name'] = $state->country?->name;
                $state['uid'] = $state->id;
                $state['can_edit'] = $user->hasPermissionTo('create_state');
                $state['can_delete'] = $user->hasPermissionTo('delete_state');

                return $state;
            });

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
     *
     * @param string $uid
     * @return array
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
     *
     * @param array $data
     * 
     * @return array
     */
    public function store(array $data): array
    {
        try {
            // get country code
            $country = $this->countryRepo->show($data['country_id'], 'iso3,id');
            $data['country_code'] = $country->iso3;

            $this->repo->store($data);

            return generalResponse(
                __('notification.successCreateState'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $datasuccessCreateState
     * @param string $id
     * @param string $where
     * 
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array
    {
        try {
            $country = $this->countryRepo->show($data['country_id'], 'iso3,id');
            $data['country_code'] = $country->iso3;

            $this->repo->update($data, $id);

            return generalResponse(
                __('notification.successUpdateState'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }   

    /**
     * Delete selected data
     *
     * @param integer $id
     * 
     * @return array
     */
    public function delete(int $id): array
    {
        try {
            // validate relation
            $check = $this->repo->show(uid: $id, select: 'id', relation: ['lastProjectDeal']);
            
            if (!$check) {
                return errorResponse(
                    __('notification.dataNotFound')
                );
            }

            if ($check->lastProjectDeal) {
                return errorResponse(
                    __('notification.cannotDeleteStateBcsRelation'),
                );
            }

            $this->repo->delete($id);

            return generalResponse(
                __('notification.successDeleteState'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete bulk data
     *
     * @param array $ids
     * 
     * @return array
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

    /**
     * Request states selection list
     * This function is used to get states list for selection purpose
     * @return array<mixed>
     */
    public function requestStatesSelectionList(): array
    {
        $search = request('search');
        $where = "1 = 1";
        $itemsPerPage = request('per_page') ?? 10;
        $itemsPerPage = $itemsPerPage == -1 ? 999999 : $itemsPerPage;
        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

        if ($search) {
            $where .= " and name like '%{$search}%'";
        }

        if (request('country_id')) {
            $where .= " and country_id = " . request('country_id');
        }

        $paginated = $this->repo->pagination(
            select: 'id,name,country_id',
            where: $where,
            relation: [],
            itemsPerPage: $itemsPerPage,
            page: $page
        );

        $paginated = $paginated->map(function ($item) {
            $item['uid'] = $item->id;

            return $item;
        });

        return generalResponse(
            message: "Success",
            data: $paginated->toArray()
        );
    }
}