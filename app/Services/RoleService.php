<?php

namespace App\Services;

use App\Data\User\Role\DetailPermissionRoleData;
use App\Data\User\Role\DetailRoleData;
use App\Data\User\Role\ListRoleData;
use App\Exceptions\CannotDeleteRoleWithRelated;
use App\Exceptions\RoleHasRelation as ExceptionsRoleHasRelation;
use App\Models\User;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    private $repo;

    private $permissionRepo;

    private UserRepository $userRepo;

    public function __construct()
    {
        $this->repo = new RoleRepository;

        $this->permissionRepo = new PermissionRepository;

        $this->userRepo = new UserRepository;
    }

    /**
     * Paginated, searchable list of roles for the datatable.
     *
     * Reads the datatable controls from the current request: `limit` (page size),
     * `page` (1-based), `name` (partial, case-insensitive name filter) and `sortBy`
     * (array of `{key, order}`, defaults to newest first). Each row is projected to a
     * {@see ListRoleData} DTO; `totalData` is the unpaginated match count.
     *
     * @return array{error: bool, message: string, data: array{paginated: Collection<int, ListRoleData>, totalData: int}, code: int}
     */
    public function list(): array
    {
        try {
            $itemsPerPage = request('limit');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $name = request('name');
            $sort = request('sortBy');

            $where = '';
            if ($name) {
                $where = "name like '%{$name}%'";
            }

            $orderBy = 'id desc';
            if ($sort && gettype($sort) == 'array' && count($sort) > 0) {
                $sortKey = $sort[0]['key'];
                $sortDir = $sort[0]['order'];
                $orderBy = "{$sortKey} {$sortDir}";
            }

            $roles = $this->repo->pagination(
                select: 'id,name',
                where: $where,
                itemsPerPage: $itemsPerPage,
                page: $page,
                orderBy: $orderBy,
                relation: []
            )->map(function ($item) {
                return new ListRoleData(
                    uid: $item->id,
                    name: $item->name
                );
            });

            $totalData = $this->repo->list(select: 'id', where: $where)->count();

            return generalResponse(
                message: __('global.success'),
                data: [
                    'paginated' => $roles,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * All roles as select options (`{value: id, title: name}`), with the role name
     * de-slugified (underscores replaced by spaces) for display.
     *
     * @return array{error: bool, message: string, data: array<int, array{value: int, title: string, name: string}>, code: int}
     */
    public function getAll(): array
    {
        $data = $this->repo->list('id as value,name as title')->map(function ($item) {
            $item['name'] = str_replace('_', ' ', $item->title);

            return $item;
        })->toArray();

        return generalResponse(
            __('global.success'),
            false,
            $data,
        );
    }

    /**
     * Create a role (on the `sanctum` guard) and attach the given permissions.
     *
     * Runs in a transaction; the Spatie permission cache is flushed so the new
     * grants take effect immediately. Any failure rolls back and returns an error.
     *
     * @param  array{name: string, permissions: array<int, int|string>}  $data
     * @return array{error: bool, message: string, data: array, code: int}
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $role = $this->repo->store(['name' => $data['name'], 'guard_name' => 'sanctum']);

            foreach ($data['permissions'] as $perm) {
                $permission = $this->permissionRepo->show((int) $perm);

                $role->givePermissionTo($permission);
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
     * Replace a role's permission set with the given permissions.
     *
     * All existing permissions are revoked and the new ones attached, in a
     * transaction, flushing the Spatie permission cache afterward. When the acting
     * user holds the role being edited, `has_to_logout` is returned true so the UI
     * can prompt a re-login (their cached abilities just changed).
     *
     * @param  array{name: string, permissions: array<int, int|string>}  $data
     * @return array{error: bool, message: string, data: array{has_to_logout: bool}, code: int}
     */
    public function update(array $data, int $id): array
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

            $permissions = $this->permissionRepo->list(select: 'name', where: 'id IN ('.implode(',', $data['permissions']).')')->pluck('name');
            $role->givePermissionTo($permissions);

            // foreach ($data['permissions'] as $perm) {
            //     $permission = $this->permissionRepo->show((int) $perm);
            //
            //     $role->givePermissionTo($permission);
            // }

            // if actor is the owner of this role, then suggest to logout
            $userRoles = auth()->user()->roles;
            $hasToLogout = false;
            if ($userRoles[0]->id == $role->id) {
                $hasToLogout = true;
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

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

    /**
     * Role detail as a {@see DetailRoleData} DTO: the role's uid and name plus its
     * permissions, each a {@see DetailPermissionRoleData} (`{uid, name}`).
     *
     * @return array{error: bool, message: string, data: array{uid: string, name: string, permissions: array<int, array{uid: string, name: string}>}, code: int}
     */
    public function show(int $id): array
    {
        try {
            $role = $this->repo->show($id);

            /** @var array<int, DetailPermissionRoleData> * */
            $permissions = [];
            foreach ($role->permissions as $permission) {
                $permissions[] = new DetailPermissionRoleData(
                    uid: $permission->id,
                    name: $permission->name,
                );
            }

            $output = new DetailRoleData(
                uid: $role->id,
                name: $role->name,
                permissions: $permissions
            );

            return generalResponse(
                message: __('global.success'),
                data: $output->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete a single role after revoking its permissions.
     *
     * Guarded: if any user still holds the role, nothing is deleted and a
     * {@see CannotDeleteRoleWithRelated} error envelope is returned instead.
     *
     * @return array{error: bool, message: string, data: array, code: int}
     */
    public function destroy(int $id): array
    {
        try {
            $role = $this->repo->show($id);
            $user = $this->userRepo->detail(id: '', select: 'id', whereRole: [$role->name]);

            if ($user) {
                throw new CannotDeleteRoleWithRelated;
            }

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
     * Delete several roles at once.
     *
     * Each role is checked first; if any one of them is still assigned to a user,
     * the whole operation aborts with a {@see ExceptionsRoleHasRelation} error
     * envelope and nothing is deleted.
     *
     * @param  array<int, int>  $ids
     * @return array{error: bool, message: string, data: array, code: int}
     */
    public function bulkDelete(array $ids): array
    {
        try {
            // check relation (roles live on the `sanctum` guard, so resolve against it explicitly)
            foreach ($ids as $id) {
                $role = Role::findById($id, 'sanctum');
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
