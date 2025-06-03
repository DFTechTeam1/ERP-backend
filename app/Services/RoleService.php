<?php

namespace App\Services;

use App\Exceptions\RoleHasRelation as ExceptionsRoleHasRelation;
use App\Models\User;
use App\Repository\RoleRepository;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    private $repo;

    private $permissionRepo;

    public function __construct()
    {
        $this->repo = new RoleRepository;

        $this->permissionRepo = new \App\Repository\PermissionRepository;
    }

    public function list()
    {
        $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
        $where = '';

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

        $paginated = $this->repo->pagination(
            'id as uid,name,is_permanent',
            $where,
            [],
            $itemsPerPage,
            $page,
            [],
            $sort
        );

        $totalData = $this->repo->list('id', $where)->count();

        return generalResponse(
            'success',
            false,
            [
                'paginated' => $paginated,
                'totalData' => $totalData,
            ],
        );
    }

    public function getAll()
    {
        $data = $this->repo->list('id as value,name as title')->map(function ($item) {
            $item['name'] = str_replace('_', ' ', $item->title);

            return $item;
        })->toArray();

        return generalResponse(
            'Success',
            false,
            $data,
        );
    }

    /**
     * Store Role
     *
     * @return array
     */
    public function store(array $data)
    {
        DB::beginTransaction();
        try {
            $role = $this->repo->store(['name' => $data['name']]);

            foreach ($data['permissions'] as $perm) {
                $permission = $this->permissionRepo->show((int) $perm);

                $role->givePermissionTo($permission);
            }

            DB::commit();

            return generalResponse(
                __('global.successCreateRole'),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update Role
     *
     * @return array
     */
    public function update(array $data, int $id)
    {
        DB::beginTransaction();
        try {
            $role = $this->repo->show($id);
            $rolePermissions = $role->permissions;
            if (count($rolePermissions) > 0) {
                foreach ($rolePermissions as $rolePermission) {
                    $role->revokePermissionTo($rolePermission);
                }
            }

            foreach ($data['permissions'] as $perm) {
                $permission = $this->permissionRepo->show((int) $perm);

                $role->givePermissionTo($permission);
            }

            // if actor is the owner of this role, then suggest to logout
            $userRoles = auth()->user()->roles;
            $hasToLogout = false;
            if ($userRoles[0]->id == $role->id) {
                $hasToLogout = true;
            }

            DB::commit();

            return generalResponse(
                __('global.successUpdateRole'),
                false,
                [
                    'has_to_logout' => $hasToLogout,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function show(int $id)
    {
        $data = $this->repo->show($id);
        $permissions = $data->permissions;
        if (count($permissions) > 0) {
            $permissions = collect($permissions)->map(function ($item) {
                $item['name'] = str_replace('_', ' ', $item->name);

                return $item;
            })->toArray();
        }

        return generalResponse(
            'success',
            false,
            [
                'role' => [
                    'id' => $data->id,
                    'name' => $data->name,
                    'permissions' => $permissions,
                    'is_permanent' => $data->is_permanent,
                ],
                'raw' => $data,
            ],
        );
    }

    public function destroy(int $id)
    {
        try {
            $role = $this->repo->show($id);
            $rolePermissions = $role->permissions;
            if (count($rolePermissions) > 0) {
                foreach ($rolePermissions as $rolePermission) {
                    $role->revokePermissionTo($rolePermission);
                }
            }

            $this->repo->delete($id);

            return generalResponse(
                __('global.successDeleteRole'),
                false,
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
            // check relation
            foreach ($ids as $id) {
                $role = Role::findById($id);
                $user = User::role($role->name)->get();
                if ($user->count()) {
                    throw new ExceptionsRoleHasRelation;
                }
            }

            $this->repo->bulkDelete($ids, 'id');

            return generalResponse(
                __('global.successDeleteRole'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
