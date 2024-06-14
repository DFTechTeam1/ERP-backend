<?php

namespace App\Services;

use App\Enums\ErrorCode\Code;
use App\Repository\UserLoginHistoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Hrd\Jobs\SendEmailActivationJob;

class UserService {
    private $repo;

    private $employeeRepo;

    private $roleRepo;

    private $loginHistoryRepo;
    
    public function __construct()
    {
        $this->repo = new \App\Repository\UserRepository();    

        $this->employeeRepo = new \Modules\Hrd\Repository\EmployeeRepository();

        $this->roleRepo = new \App\Repository\RoleRepository();

        $this->loginHistoryRepo = new UserLoginHistoryRepository();
    }

    public function addAsUser(string $userId)
    {
        try {
            
        } catch(\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
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
            'id,uid,email,email_verified_at',
            $where,
            ['lastLogin:id,user_id,login_at'],
            $itemsPerPage,
            $page
        );

        $paginated = collect($paginated)->map(function ($item) {
            $roles = $item->getRoleNames();
            $is_deleteable = true;
            $is_editable = true;

            // if user see himself on the list, then user cannot delete the data
            if ($item->id == auth()->id()) {
                $is_deleteable = false;
            }

            return [
                'last_login_at' => $item->lastLogin ? date('Y-m-d H:i:s', strtotime($item->lastLogin->login_at)) : null,
                'uid' => $item->uid,
                'email' => $item->email,
                'role_name' => count($roles) > 0 ? $roles[0] : null,
                'status' => $item->status,
                'status_color' => $item->status_color,
                'is_deleteable' => $is_deleteable,
                'is_editable' => $is_editable,
            ];
        })->toArray();
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

    public function update(array $data, string $id)
    {
        try {
            $user = $this->repo->detail($id);
            $roles = $user->roles;
            foreach ($roles as $role) {
                $user->removeRole($role);
            }

            $user->email = $data['email'];
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();

            $role = $this->roleRepo->show($data['role']);
            $user->assignRole($role);

            return generalResponse(
                __('global.successUpdateUser'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store user
     *
     * @param array $data
     * @return array
     */
    public function store(array $data)
    {
        DB::beginTransaction();
        try {
            if (!$data['is_external_user']) {
                $employee = $this->employeeRepo->show($data['employee_id'], 'id,uid,email');
                $email = $employee->email;
            } else {
                $email = $data['email'];
            }

            $user = $this->repo->store([
                'email' => $email,
                'password' => $data['password'],
            ]);

            // assign role
            $role = $this->roleRepo->show($data['role']);
            $user->assignRole($role);

            if (!$data['is_external_user']) {
                $this->employeeRepo->update([
                    'user_id' => $user->id,
                ], $data['employee_id']);
            }

            SendEmailActivationJob::dispatch($user, $data['password'])->afterCommit();
            
            DB::commit();

            return generalResponse(
                __('global.successCreateUser'),
                false
            );
            
        } catch(\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function show(string $id)
    {
        $data = $this->repo->detail($id);
        $data->roles;
        
        return generalResponse(
            'success',
            false,
            [
                'uid' => $data->uid,
                'id' => $data->id,
                'email' => $data->email,
                'is_external_user' => $data->is_external_user,
                'role_id' => $data->roles[0]->id,
            ],
        );
    }

    public function activate(string $key)
    {
        try {
            $service = new EncryptionService();
    
            $email = $service->decrypt($key, env('SALT_KEY'));

            $user = $this->repo->detail('', 'id,email_verified_at', "email = '{$email}'");

            $message = __('global.accontAlreadyActive');
            if (!$user->email_verified_at) {
                $this->repo->update([
                    'email_verified_at' => Carbon::now(),
                ], 'email', $email);
                $message = __('global.accountIsActive');
            }
    
            return generalResponse(
                $message,
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
            foreach ($ids as $id) {
                $user = $this->repo->detail($id);

                $roles = $user->roles;
                foreach ($roles as $role) {
                    $user->removeRole($role);
                }
            }

            $this->repo->bulkDelete($ids, 'id');

            return generalResponse(
                __('global.successDeleteUser'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}