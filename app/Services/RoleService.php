<?php

namespace App\Services;

use App\Repository\RoleRepository;
use Illuminate\Support\Facades\DB;

class RoleService {
    private $repo;

    private $permissionRepo;

    public function __construct()
    {
        $this->repo = new RoleRepository();

        $this->permissionRepo = new \App\Repository\PermissionRepository();
    }

    public function list()
    {
        $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
        $search = request('search');

        $where = '';

        $paginated = $this->repo->pagination(
            'id as uid,name',
            $where,
            [],
            $itemsPerPage,
            $page
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

    /**
     * Store Role
     *
     * @param array $data
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
     * @param array $data
     * @param int $id
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

            DB::commit();

            return generalResponse(
                __('global.successUpdateRole'),
                false,
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
                ],
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
                __("global.successDeleteRole"),
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