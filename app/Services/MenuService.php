<?php

namespace App\Services;

use App\Repository\MenuRepository;

class MenuService {
    private $repo;

    public function __construct()
    {
        $this->repo = new MenuRepository();
    }

    public function getMenus($permissionsData = null)
    {
        $user = auth()->user();
        if ($user) {
            $permissions = $user->getAllPermissions();
        } else {
            $permissions = $permissionsData;
        }
        if (!empty($permissions)) {
            $permissions = collect($permissions)->pluck('name')->all();
        }
            
        $data = $this->repo->list();
        $menuGroups = \App\Enums\Menu\Group::cases();

        $headerLang = request()->header('App-Language');
        if (!$headerLang) {
            $headerLang = 'en';
        }

        $parent = collect($data)->where('parent_id', null)->values();
        $parent = collect((object) $parent)->map(function ($item) use ($data, $menuGroups, $permissions, $headerLang) {
            $child = [];

            if ($headerLang == 'en') {
                $item['name'] = $item['lang_en'];
            } else if ($headerLang == 'id') {
                $item['name'] = $item['lang_id'];
            }

            foreach ($menuGroups as $menuGroup) {
                if ($menuGroup->value == $item->group) {
                    $item['group'] = $menuGroup->label();
                }
            }

            foreach ($data as $d) {
                if ($item->id == $d->parent_id) {
                    if (!empty($d->permission)) {
                        if (gettype(array_search($d->permission, $permissions)) != 'boolean') {
                            if ($headerLang == 'en') {
                                $d['name'] = $d['lang_en'];
                            } else if ($headerLang == 'id') {
                                $d['name'] = $d['lang_id'];
                            }

                            $child[] = $d;
                        }
                    }
                    
                }
            }

            if (!empty($item->permission)) {
                if (gettype(array_search($item->permission, $permissions)) == 'boolean') {
                    $isShow = false;
                } else {
                    $isShow = true;
                }
                $item['is_show'] = $isShow;
            } else {
                $item['is_show'] = true;
            }

            $item['children'] = $child;
            $item['icon'] = asset($item['icon']);

            return $item;
        })->filter(function ($filter) {
            return $filter->is_show;
        })->values()->toArray();

        $out = [];
        foreach ($parent as $key => $p) {
            if (empty($p['permission']) && empty($p['children'])) {
                unset($parent[$key]);
            } else {
                $out[] = $p;
            }
        }

        $out = collect($out)->groupBy('group')->toArray();

        return generalResponse(
            'success',
            false,
            $out,
        );
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
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            if (!empty($search)) { // array
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas
            );

            $totalData = $this->repo->list('id', $where)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'where' => $where,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}