<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use Modules\Company\Repository\CityRepository;
use Modules\Company\Repository\CountryRepository;

class CityService {
    /**
     * Construction Data
     */
    public function __construct(
        private readonly CityRepository $repo,
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
            $user = \Illuminate\Support\Facades\Auth::user();
            $user = (new \App\Repository\UserRepository)->detail(id: $user->id, select: 'id');

            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('name');

            $state = request('state');
            $countryId = request('country');
            $where = '1 = 1';

            if (!empty($search)) {
                $where .= " and lower(name) LIKE '%{$search}%'";
            }

            if ($state) {
                $stateId = collect($state)->implode(',');
                $where .= " AND state_id IN ({$stateId})";
            }

            if ($countryId) {
                $where .= " AND country_id = {$countryId}";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );
            $totalData = $this->repo->list('id', $where)->count();
            $paginated = $paginated->map(function ($city) use ($user) {
                $city['country_name'] = $city->state ? $city->state->country?->name : null;
                $city['state_name'] = $city->state?->name;
                $city['uid'] = $city->id;
                $city['can_edit'] = $user->hasPermissionTo('create_city');
                $city['can_delete'] = $user->hasPermissionTo('delete_city');

                return $city;
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
            $country = $this->countryRepo->show($data['country_id'], 'iso3');
            $data['country_code'] = $country->iso3;
            
            $this->repo->store($data);

            return generalResponse(
                __('notification.successCreateCity'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $data
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
            // get country code
            $country = $this->countryRepo->show($data['country_id'], 'iso3');
            logging('country update city', $country->toArray());
            $data['country_code'] = $country->iso3;

            $this->repo->update($data, $id);

            return generalResponse(
                __('notification.successUpdateCity'),
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
                    __('notification.dataNotFound'),
                );
            }

            if ($check->lastProjectDeal) {
                return errorResponse(
                    __('notification.cannotDeleteCityBcsRelation'),
                );
            }

            $this->repo->delete($id);

            return generalResponse(
                __('notification.successDeleteCity'),
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
}