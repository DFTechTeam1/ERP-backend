<?php

namespace Modules\Company\Services;

use App\Enums\ErrorCode\Code;
use App\Repository\UserRepository;
use Illuminate\Support\Facades\Auth;
use Modules\Company\Repository\CountryRepository;

class CountryService {
    private $repo;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new CountryRepository;
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
            $user = (new UserRepository)->detail(id: $user->id);

            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
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

            $paginated = $paginated->map(function ($item) use ($user) {
                $item['uid'] = $item->id;
                $item['can_edit'] = $user->hasPermissionTo('create_country');
                $item['can_delete'] = $user->hasPermissionTo('delete_country');

                return $item;
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
            $this->repo->store($data);

            return generalResponse(
                __('notification.successCreateCountry'),
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
            $this->repo->update($data, $id);

            return generalResponse(
                __('notification.successUpdateCountry'),
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
            $data = $this->repo->show(uid: $id, select: 'id', relation: ['lastProjectDeal']);
            
            if (!$data) {
                return errorResponse(
                    __('notification.dataNotFound'),
                );
            }

            if ($data->lastProjectDeal) {
                return errorResponse(
                    __('notification.cannotDeleteCountryBcsRelation'),
                );
            }

            $this->repo->delete($id);

            return generalResponse(
                __('notification.successDeleteCountry'),
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
     * Request project deal selection list
     * This function is used to get project deal list for selection purpose
     * @return array<mixed>
     */
    public function requestCountriesSelectionList(): array
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

        $paginated = $this->repo->pagination(
            select: 'id,name,iso3,iso2,phone_code,currency',
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