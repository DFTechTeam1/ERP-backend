<?php

namespace App\Services;

use App\Enums\ErrorCode\Code;
use App\Repository\UserLoginHistoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
            'id,email,email_verified_at',
            $where,
            ['lastLogin:id,user_id,login_at'],
            $itemsPerPage,
            $page
        );

        $paginated = collect($paginated)->map(function ($item) {
            $roles = $item->getRoleNames();


            return [
                'last_login_at' => $item->lastLogin ? date('Y-m-d H:i:s', strtotime($item->lastLogin->login_at)) : null,
                'id' => $item->id,
                'email' => $item->email,
                'role_name' => count($roles) > 0 ? $roles[0] : null,
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

            SendEmailActivationJob::dispatch($user)->afterCommit();
            
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

    public function activate(string $key)
    {
        try {
            $service = new EncryptionService();
    
            $email = $service->decrypt($key, env('SALT_KEY'));
    
            $this->repo->update([
                'email_verified_at' => Carbon::now(),
            ], 'email', $email);
    
            return generalResponse(
                __('global.accountIsActive'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}