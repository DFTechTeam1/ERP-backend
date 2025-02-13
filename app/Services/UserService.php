<?php

namespace App\Services;

use App\Enums\ErrorCode\Code;
use App\Exceptions\UserNotFound;
use App\Models\User;
use App\Models\UserEncryptedToken;
use App\Repository\RoleRepository;
use App\Repository\UserLoginHistoryRepository;
use App\Repository\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Hrd\Jobs\SendEmailActivationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Spatie\Permission\Models\Role;
use Vinkla\Hashids\Facades\Hashids;

class UserService {
    private $repo;

    private $employeeRepo;

    private $roleRepo;

    private $loginHistoryRepo;

    private $generalService;

    private $roleService;

    public function __construct(
        UserRepository $userRepo,
        EmployeeRepository $employeeRepo,
        RoleRepository $roleRepo,
        UserLoginHistoryRepository $userLogHistoryRepo,
        GeneralService $generalService,
        RoleService $roleService
    )
    {
        $this->repo = $userRepo;

        $this->employeeRepo = $employeeRepo;

        $this->roleRepo = $roleRepo;

        $this->loginHistoryRepo = $userLogHistoryRepo;

        $this->generalService = $generalService; 

        $this->roleService = $roleService;
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
        $where = '';
        
        $search = request('search');

        if (!empty($search)) { // array
            $where = formatSearchConditions($search['filters'], $where);
        }

        $sort = "email asc";
        if (request('sort')) {
            $sort = "";
            foreach (request('sort') as $sortList) {
                if ($sortList['field'] == 'email') {
                    $sort = $sortList['field'] . " {$sortList['order']},";
                } else {
                    $sort .= "," . $sortList['field'] . " {$sortList['order']},";
                }
            }

            $sort = rtrim($sort, ",");
            $sort = ltrim($sort, ',');
        }

        $paginated = $this->repo->pagination(
            'id,uid,email,email_verified_at',
            $where,
            ['lastLogin:id,user_id,login_at'],
            $itemsPerPage,
            $page,
            [],
            $sort
        );

        $paginated = collect((object) $paginated)->map(function ($item) {
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
            $user->save();

            $role = $this->roleRepo->show($data['role_id']);
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
     * Main service to store a new user and send activation link via email
     *
     * @param array $data
     * @return \App\Models\User
     */
    public function mainServiceStoreUser(array $data): \App\Models\User
    {
        $isEmployee = false;
        $isDirector = false;
        $isProjectManager = false;

        // setup role
        if (isset($data['role_id'])) {
            $roleData = $this->roleService->show($data['role_id']);
            if (!$roleData['error']) {
                $role = $roleData['data']['raw'];
            }
        }

        if (!$data['is_external_user']) {    
            $currentProjectManagerRole = json_decode($this->generalService->getSettingByKey('project_manager_role'), true) ?? [];
            if (
                ($currentProjectManagerRole) &&
                (in_array($data['role_id'], $currentProjectManagerRole))
            ) {
                $isProjectManager = true;
            }

            $directorRole = $this->generalService->getSettingByKey('director_role');
            if (
                (isset($data['role_id'])) &&
                ($directorRole == $data['role_id'])
            ) {
                $isDirector = true;
            }

            $pmAndDirectorRole = collect($currentProjectManagerRole)->merge([$directorRole])->toArray();

            $isEmployee = !isset($data['role_id']) ? false : !in_array($data['role_id'], $pmAndDirectorRole);

            $employeeId = $this->generalService->getIdFromUid($data['employee_id'], new Employee());
        }


        $payload = [
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_external_user' => $data['is_external_user'],
            'employee_id' => $employeeId ?? null,
            'username' => NULL,
            'is_employee' => $isEmployee,
            'is_director' => $isDirector,
            'is_project_manager' => $isProjectManager,
        ];

        // only assign role and send notification to internal user
        $user = $this->repo->store($payload);

        if ($role) {
            $user->assignRole($role);
        }

        if (!$data['is_external_user']) {
            // update relation on employee
            $this->employeeRepo->update([
                'user_id' => $user->id,
            ], $data['employee_id']);

            SendEmailActivationJob::dispatch($user, $data['password'])->afterCommit();
        }

        return $user;
    }

    /**
     * Store user
     *
     * @param array $data
     * $data is
     * @param bool is_external_user
     * @param string email
     * @param string employee_id
     * @param string password
     * @param int role_id
     * @return array
     */
    public function store(array $data)
    {
        DB::beginTransaction();
        try {
            $this->mainServiceStoreUser($data);

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

        $employeeData = $this->employeeRepo->show('id', 'id,uid', [], 'user_id = ' . $data->id);

        return generalResponse(
            'success',
            false,
            [
                'uid' => $data->uid,
                'id' => $data->id,
                'employee_uid' => $employeeData ? $employeeData->uid : null,
                'email' => $data->email,
                'is_external_user' => $data->is_external_user,
                'role_id' => isset($data->roles[0]) ? $data->roles[0]->id : 0,
            ],
        );
    }

    public function userChangePassword(array $payload, string $userUid): array
    {
        try {
            $this->repo->update([
                'password' => Hash::make($payload['password'])
            ], 'uid', $userUid);

            return generalResponse(
                message: __('notification.successChangePassword'),
                error: false
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
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
        DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $user = $this->repo->detail(
                    select: '*',
                    where: "uid = '{$id}'"
                );

                // validate relation
                $employee = $this->employeeRepo->show(
                    uid: 'id',
                    select: 'id,name,email,uid',
                    relation: [
                        'tasks:id,project_task_id,employee_id',
                        'projects:id,project_id,pic_id'
                    ],
                    where: "user_id = " . $user->id
                );

                if (
                    ($employee) &&
                    (
                        $employee->projects->count() > 0 || $employee->tasks->count() > 0
                    )
                ) {
                    DB::rollBack();

                    return errorResponse(__('notification.cannotDeleteEmployeeBcsRelation'));
                }

                $roles = $user->roles;
                foreach ($roles as $role) {
                    $user->removeRole($role);
                }

                // detach from employee data
                if ($employee) {
                    $this->employeeRepo->update([
                        'user_id' => NULL,
                    ], $employee->uid);
                }

                // update email
                $this->repo->update([
                    'email' => $user->email . '_deleted_' . strtotime('now'),
                    'employee_id' => NULL
                ], 'id', $user->id);
            }

            $this->repo->bulkDelete($ids, 'uid');

            DB::commit();
            return generalResponse(
                __('global.successDeleteUser'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    public function login(array $validated, bool $unitTesting = false)
    {
        $user = $this->repo->detail(
            id: 'id',
            select: '*',
            where: "email = '{$validated['email']}'",
            relation: ['employee.position', 'roles']
        );

        if (!$user) {
            throw new UserNotFound(__('global.userNotFound'));
        }

        if (!$user->email_verified_at) {
            throw new UserNotFound(__('global.userNotActive'));
        }

        if (!Hash::check($validated['password'], $user->password)) {
            throw new UserNotFound(__('global.credentialDoesNotMatch'));
        }

        if (!isset($user->getRoleNames()[0])) {
            throw new \App\Exceptions\DoNotHaveAppPermission();
        }

        $role = $user->getRoleNames()[0];
        $roles = $user->roles;

        $roleId = null;
        if (count($roles) > 0) {
            $roleId = $roles[0]->id;
        }
        $permissions = count($user->getAllPermissions()) > 0 ? $user->getAllPermissions()->pluck('name')->toArray() : [];

        $expireTime = now()->addHours(24);
        if (isset($validated['remember_me'])) {
            $expireTime = now()->addDays(30);
        }

        $token = $user->createToken($role, $permissions, $expireTime);

        $menuService = new \App\Services\MenuService();
        $menus = $menuService->getMenus($user->getAllPermissions());

        $isProjectManager = false;

        $isEmployee = false;

        $isDirector = false;

        $isSuperUser = false;

        $positionAsDirectors = json_decode($this->generalService->getSettingByKey('position_as_directors'), true);

        $positionAsProjectManager = json_decode($this->generalService->getSettingByKey('position_as_project_manager'), true);

        $superUserRole = $this->generalService->getSettingByKey('super_user_role');

        if ($roleId == $superUserRole) {
            $isSuperUser = true;
        }

        if (
            ($positionAsDirectors) &&
            ($user->employee) &&
            (in_array($user->employee->position->uid, $positionAsDirectors))
        ) {
            $isDirector = true;
        }

        if (
            ($positionAsProjectManager) &&
            ($user->employee) &&
            (in_array($user->employee->position->uid, $positionAsProjectManager))
        ) {
            $isProjectManager = true;
        }

        $emailShow = trim(
            strip_tags(
                html_entity_decode(
                    $user->email, ENT_QUOTES, 'UTF-8')
                )
            );
        if (strlen($user->email) > 15) {
            $emailShow = mb_substr($emailShow, 0, 15) . ' ...';
        } else {
            $emailShow = $user->email;
        }
        $user['email_show'] = $emailShow;

        $employee = \Modules\Hrd\Models\Employee::select("id")
            ->find($user->employee_id);

        $notifications = [];
        if ($employee) {
            $notifications = $this->generalService->formatNotifications($employee->unreadNotifications->toArray());
        }

        $userIdEncode = Hashids::encode($user->id);

        $payload = [
            'token' => $token->plainTextToken,
            'exp' => date('Y-m-d H:i:s', strtotime($token->accessToken->expires_at)),
            'user' => $user,
            'permissions' => $permissions,
            'role' => $role,
            'menus' => $menus['data'],
            'role_id' => $roleId,
            'app_name' => $this->generalService->getSettingByKey('app_name'),
            'board_start_calcualted' => $this->generalService->getSettingByKey('board_start_calcualted'),
            'is_director' => $isDirector,
            'is_project_manager' => $isProjectManager,
            'is_super_user' => $isSuperUser,
            'notifications' => $notifications,
            'encrypted_user_id' => $userIdEncode,
        ];

        // this data is used when changing to other subdomains
        UserEncryptedToken::updateOrCreate(
            ['user_id' => $user->id],
            ['data' => json_encode($payload)]
        );

        $encryptionService = new EncryptionService();
        $encryptedPayload = $encryptionService->encrypt(json_encode($payload), env('SALT_KEY'));

        // store histories
        $this->loginHistoryRepo->store([
            'user_id' => $user->id,
            'ip' => $this->generalService->getClientIp(),
            'browser' => $this->generalService->parseUserAgent($this->generalService->getUserAgentInfo())['browser'],
            'login_at' => Carbon::now(),
        ]);

        // store to cache for user device information
        \Illuminate\Support\Facades\Cache::rememberForever('userLogin' . $user->id, function () {
            return [
                'ip' => $this->generalService->getClientIp(),
                'browser' => $this->generalService->parseUserAgent($this->generalService->getUserAgentInfo()),
            ];
        });

        return $encryptedPayload;
    }
}
