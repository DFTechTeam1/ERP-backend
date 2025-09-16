<?php

namespace Modules\Hrd\Services;

use App\Enums\Cache\CacheKey;
use App\Enums\Employee\Gender;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\Status;
use App\Enums\ErrorCode\Code;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Exceptions\EmployeeException;
use App\Exports\EmployeeExport;
use App\Models\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Services\ChartService;
use App\Services\GeneralService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Company\Models\JobLevel;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Repository\JobLevelRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Exceptions\EmployeeNotFound;
use Modules\Hrd\Jobs\DeleteOfficeEmailJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\DeleteOfficeEmailQueueRepository;
use Modules\Hrd\Repository\EmployeeActiveReportRepository;
use Modules\Hrd\Repository\EmployeeEmergencyContactRepository;
use Modules\Hrd\Repository\EmployeeFamilyRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeResignRepository;
use Modules\Hrd\Repository\EmployeeTimeoffRepository;
use Modules\Production\Models\Project;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectVjRepository;
use Modules\Production\Models\ProjectPersonInCharge;

class EmployeeService
{
    private $repo;

    private $positionRepo;

    private $userRepo;

    private $taskRepo;

    private $projectRepo;

    private $projectVjRepo;

    private $projectPicRepo;

    private $projectTaskHistoryRepo;

    private $employeeFamilyRepo;

    private $employeeEmergencyRepo;

    private $idCardPhotoTmp;

    private $npwpPhotoTmp;

    private $bpjsPhotoTmp;

    private $kkPhotoTmp;

    private $userService;

    private $generalService;

    private $jobLevelRepo;

    private $chart;

    private $employeeActiveRepo;

    private $employeeTimeoffRepo;

    private $talentaService;

    private $employeeResignRepo;

    private $deleteOfficeEmailQueueRepo;

    private $projectTaskPicRepo;

    public function __construct(
        EmployeeRepository $employeeRepo,
        PositionRepository $positionRepo,
        UserRepository $userRepo,
        ProjectTaskRepository $projectTaskRepo,
        ProjectRepository $projectRepo,
        ProjectVjRepository $projectVjRepo,
        ProjectPersonInChargeRepository $projectPicRepo,
        ProjectTaskPicHistoryRepository $projectTaskPicHistoryRepo,
        EmployeeFamilyRepository $employeeFamilyRepo,
        EmployeeEmergencyContactRepository $employeeEmergencyRepo,
        UserService $userService,
        GeneralService $generalService,
        JobLevelRepository $jobLevelRepo,
        ChartService $chartService,
        EmployeeActiveReportRepository $employeeActiveRepo,
        EmployeeTimeoffRepository $employeeTimeoffRepo,
        TalentaService $talentaService,
        EmployeeResignRepository $employeeResignRepo,
        DeleteOfficeEmailQueueRepository $deleteOfficeEmailQueueRepo,
        ProjectTaskPicRepository $projectTaskPicRepo
    ) {
        $this->talentaService = $talentaService;

        $this->repo = $employeeRepo;

        $this->userService = $userService;

        $this->positionRepo = $positionRepo;

        $this->userRepo = $userRepo;

        $this->taskRepo = $projectTaskRepo;

        $this->projectRepo = $projectRepo;

        $this->projectVjRepo = $projectVjRepo;

        $this->projectPicRepo = $projectPicRepo;

        $this->projectTaskHistoryRepo = $projectTaskPicHistoryRepo;

        $this->employeeFamilyRepo = $employeeFamilyRepo;

        $this->employeeEmergencyRepo = $employeeEmergencyRepo;

        $this->generalService = $generalService;

        $this->jobLevelRepo = $jobLevelRepo;

        $this->chart = $chartService;

        $this->employeeActiveRepo = $employeeActiveRepo;

        $this->employeeTimeoffRepo = $employeeTimeoffRepo;

        $this->employeeResignRepo = $employeeResignRepo;

        $this->deleteOfficeEmailQueueRepo = $deleteOfficeEmailQueueRepo;

        $this->projectTaskPicRepo = $projectTaskPicRepo;
    }

    /**
     * Get list of data
     */
    public function list(
        string $select = '*',
        string $where = '',
        array $relation = []
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $search = request('search');

            if (! empty($search)) { // array
                $filterNames = collect($search['filters'])->pluck('field')->values()->toArray();

                // append status filter when user is not search for filter. This status filter will be as default filter
                if (! in_array('status', $filterNames)) {
                    $search['filters'] = collect($search['filters'])->merge([
                        [
                            'field' => 'status',
                            'condition' => 'not_contain',
                            'value' => Status::Deleted->value,
                            'data_type' => 'integer',
                        ],
                        [
                            'field' => 'status',
                            'condition' => 'not_contain',
                            'value' => Status::Inactive->value,
                            'data_type' => 'integer',
                        ],
                    ])->toArray();
                }

                $where = formatSearchConditions($search['filters'], $where);
            } else {
                $where = 'status != '.Status::Deleted->value.' and status != '.Status::Inactive->value;
            }

            logging('where employee', [$where]);

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

            $employees = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                [],
                $sort
            );

            $now = Carbon::now();
            $paginated = collect($employees)->map(function ($item) use ($now) {
                // define action to cancel resign
                $canCancelResign = false;
                if ($item->resignData) {
                    $resignData = Carbon::parse($item->resignData->resign_date);
                    if ($now->diffInDays($resignData, false) > 0) {
                        $canCancelResign = true;
                    }
                }

                return [
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'address' => $item->address,
                    'branch' => $item->branch ? $item->branch->short_name : '-',
                    'sign_date' => date('d F Y', strtotime($item->join_date)),
                    'resign_date' => $item->end_date ? date('d F Y', strtotime($item->end_date)) : '-',
                    'email' => $item->email,
                    'birth_date' => date('d F Y', strtotime($item->date_of_birth)),
                    'birth_place' => $item->place_of_birth,
                    'religion' => Religion::getReligion(code: $item->religion->value),
                    'gender' => Gender::getGender(code: $item->gender->value),
                    'position' => $item->position->name,
                    'level_staff' => ! $item->jobLevel ? '-' : $item->jobLevel->name,
                    'status' => $item->status_text,
                    'status_color' => $item->status_color,
                    'join_date' => date('d F Y', strtotime($item->join_date)),
                    'phone' => $item->phone,
                    'martial_status' => MartialStatus::getMartialStatus(code: $item->martial_status->value),
                    'placement' => $item->placement,
                    'employee_id' => $item->employee_id,
                    'user_id' => $item->user_id,
                    'user' => $item->user,
                    'is_resign' => $item->resignData ? true : false,
                    'can_cancel_resign' => $canCancelResign,
                ];
            })->toArray();

            $totalData = $this->repo->list($select, $where, $relation)->count();

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
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Get list of 3d Modeller Employee
     */
    public function get3DModeller(?string $projectUid = null, ?string $taskUid = null): array
    {
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
            $project = $this->projectRepo->show(uid: $projectUid, select: 'id,project_date');
            $position = $this->positionRepo->show(uid: 0, select: 'id', where: "name = '3D Modeller'");

            $where = "position_id = '{$position->id}'";
            $leader = $this->generalService->getSettingByKey('lead_3d_modeller');
            if (request('except_leader') && $leader) {
                $where .= " AND uid != '{$leader}'";
            }

            $employees = $this->repo->list(select: 'id,uid AS value,name AS title', where: $where);

            // get workload
            $output = [];
            foreach ($employees as $employee) {
                if ($projectId) {
                    $taskInSameProject = $this->taskRepo->list(
                        select: 'id',
                        where: "project_id = {$projectId} AND uid != '{$taskUid}'",
                        whereHas: [
                            [
                                'relation' => 'pics',
                                'query' => "employee_id = {$employee->id}",
                            ],
                        ]
                    )->count();

                    $startDate = Carbon::parse($project->project_date);
                    $dateRangeNextWeek = [$startDate->addDay()->format('Y-m-d'), $startDate->addDays(7)->format('Y-m-d')];
                    $startDate = Carbon::parse($project->project_date);
                    $dateRangeCurrentWeek = [$startDate->subDay()->format('Y-m-d'), $startDate->subDays(7)->format('Y-m-d')];

                    $taskInNextWeek = $this->taskRepo->list(
                        select: 'id',
                        where: "uid != '{$taskUid}'",
                        whereHas: [
                            [
                                'relation' => 'project',
                                'query' => "project_date BETWEEN '{$dateRangeNextWeek[0]}' AND '{$dateRangeNextWeek[1]}'",
                            ],
                            [
                                'relation' => 'pics',
                                'query' => "employee_id = {$employee->id}",
                            ],
                        ]
                    )->count();
                    $taskInCurrentWeek = $this->taskRepo->list(
                        select: 'id',
                        where: "uid != '{$taskUid}'",
                        whereHas: [
                            [
                                'relation' => 'project',
                                'query' => "project_date BETWEEN '{$dateRangeCurrentWeek[1]}' AND '{$dateRangeCurrentWeek[0]}'",
                            ],
                            [
                                'relation' => 'pics',
                                'query' => "employee_id = {$employee->id}",
                            ],
                        ]
                    )->count();
                }

                $output[] = [
                    'value' => $employee->value,
                    'title' => $employee->title,
                    'task_in_selected_project' => $taskInSameProject ?? 0,
                    'task_in_next_week' => $taskInNextWeek ?? 0,
                    'task_in_current_week' => $taskInCurrentWeek ?? 0,
                ];
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function export(array $payload): array
    {
        try {
            $filename = 'employees_'.strtotime('now').'.xlsx';
            Excel::store(new EmployeeExport($payload), 'employees/export/'.$filename, 'public');

            return generalResponse(
                message: 'Success',
                data: [
                    'link' => asset('storage/employees/export/'.$filename),
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Generate new employeeID
     *
     * @return array
     */
    public function generateEmployeeID()
    {
        $latestData = $this->repo->list('id', '', [], 'id DESC');

        $count = count($latestData) == 0 ? 1 : count($latestData) + 1;

        /**
         * DUMMY FORMAT
         * DF010
         */
        $idNumberLength = 3;
        $prefix = 'DF';
        $numbering = $prefix.str_pad($count, $idNumberLength, 0, STR_PAD_LEFT);

        return generalResponse(
            'success',
            false,
            [
                'employee_id' => $numbering,
            ],
        );
    }

    /**
     * Validate employee ID
     */
    public function validateEmployeeID(array $data): array
    {
        $notAllowed = [
            Status::Deleted->value,
            Status::Inactive->value,
        ];
        $where = "employee_id = '".$data['employee_id']."' AND status NOT IN (".implode(',', $notAllowed).')';

        if ($data['uid']) {
            $where .= " and uid != '{$data['uid']}'";
        }

        $check = $this->repo->show('id', 'id', [], $where);

        return generalResponse(
            'success',
            false,
            [
                'valid' => ! $check ? true : false,
            ]
        );
    }

    public function getVJ(string $projectUid)
    {
        $positionAsVJ = json_decode(getSettingByKey('position_as_visual_jokey'), true);

        $output = [];

        if ($positionAsVJ) {
            $positionAsVJ = collect($positionAsVJ)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
            })->toArray();

            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $project = $this->projectRepo->show($projectUid, 'id,project_date');

            $position = implode(',', $positionAsVJ);

            $where = 'position_id IN ('.$position.') and status != '.\App\Enums\Employee\Status::Inactive->value;

            // add position project manager entertainment
            $projectManagerEntertainment = User::role(BaseRole::ProjectManagerEntertainment->value)->first();
            if ($projectManagerEntertainment) {
                $where .= ' OR id = '.$projectManagerEntertainment->employee_id;
            }

            $data = $this->repo->list('uid,name,id', $where)->toArray();

            $output = collect($data)->map(function ($employee) use ($project) {
                // check the calendar
                $calendar = $this->projectVjRepo->list('id,project_id', 'employee_id = '.$employee['id'], [
                    'project:id,project_date',
                ]);
                $projectDate = [];
                foreach ($calendar as $projectList) {
                    $projectDate[] = $projectList->project->project_date;
                }

                $selectedDate = collect($projectDate)->filter(function ($filter) use ($project) {
                    return $filter == $project->project_date;
                })->values();

                return [
                    'value' => $employee['uid'],
                    'title' => $employee['name'],
                    'date' => count($selectedDate),
                ];
            })->toArray();
        }

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    /**
     * Get all available status from enums
     */
    public function getAllStatus(): array
    {
        $status = Status::cases();

        $status = collect($status)->map(function ($item) {
            return [
                'value' => $item->value,
                'title' => $item->label(),
            ];
        })->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $status
        );
    }

    /**
     * Function to get all data
     *
     * @return array
     */
    public function getAll()
    {
        $where = '';
        // $levelStaffOrder = \App\Enums\Employee\LevelStaff::levelStaffOrder();
        $levelStaffOrder = [
            'manager',
            'lead',
            'staff',
            'junior staff',
        ];

        $key = request()->min_level;

        if (! empty(request()->min_level)) {
            $search = array_search($key, $levelStaffOrder);

            if ($search > 0) {
                $splice = array_splice($levelStaffOrder, 0, $search);

                $splice = collect($splice)->map(function ($item) {
                    return "'{$item}'";
                })->toArray();

                $where = 'level_staff IN ('.implode(',', $splice).')';
            }

        }

        if (! empty(request('name'))) {
            if (empty($where)) {
                $where = "lower(name) like '%".strtolower(request('name'))."%'";
            } else {
                $where .= " and lower(name) like '%".strtolower(request('name'))."%'";
            }
        }

        if (! empty(request('not_user'))) {
            if (empty($where)) {
                $where = 'user_id IS NULL';
            } else {
                $where .= ' and user_id IS NULL';
            }
        }

        if (! empty($where)) {
            $where .= ' and status != '.\App\Enums\Employee\Status::Inactive->value;
        } else {
            $where = 'status != '.\App\Enums\Employee\Status::Inactive->value;
        }

        $data = $this->repo->list(
            'uid,id,name,email',
            $where
        );

        $data = collect((object) $data)->map(function ($item) {
            return [
                'value' => $item->uid,
                'title' => $item->name,
                'email' => $item->email,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data
        );
    }

    public function activateAccount(string $key)
    {
        $encrypter = new \App\Services\EncryptionService;

        $email = $encrypter->decrypt($key, env('SALT_KEY'));

        $this->userRepo->update([
            'email_verified_at' => \Carbon\Carbon::now(),
        ], 'email', $email);

        return generalResponse(
            __('global.accountIsActive'),
            false,
            [
                'decrypt' => $encrypter->decrypt($key, env('SALT_KEY')),
            ],
        );
    }

    /**
     * Add employee as web app user
     *
     * @param  string  $id
     * @return array
     */
    public function addAsUser(array $payload)
    {
        DB::beginTransaction();
        try {
            $user = $this->repo->show($payload['user_id'], 'id,email,name');

            // check email
            $checkUser = $this->userRepo->detail(
                select: 'id',
                where: "email = '".$user->email."'"
            );
            if ($checkUser) {
                DB::rollBack();

                return generalResponse(
                    message: __('notification.userAlreadyExists'),
                    error: true,
                    code: 500
                );
            }

            $userData = $this->userRepo->store([
                'email' => $user->email,
                'password' => $payload['password'],
                'employee_id' => $user->id,
            ]);

            $this->repo->update([
                'user_id' => $userData->id,
            ], $payload['user_id']);

            // assign role
            $roleRepo = new RoleRepository;
            $role = $roleRepo->show($payload['role_id']);
            $userData->assignRole($role);

            \Modules\Hrd\Jobs\SendEmailActivationJob::dispatch($userData, $payload['password'])->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.successAddEmployeeAsUser', ['name' => $user->name]),
                false
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Function to check given key in the database
     *
     * @return array
     */
    public function checkFieldsUnique(string $key, string $value)
    {
        $data = $this->repo->list(
            'id',
            "{$key} = '{$value}'",
            [],
            '',
            1
        );

        return generalResponse(
            'success',
            false,
            [
                'is_available' => count($data) > 0 ? false : true,
            ],
        );
    }

    protected function getDetailEmployee(string $uid, string $select)
    {
        $relation = [
            'position:id,name,uid',
            'user:id,employee_id,email',
            'branch:id,name',
        ];

        $data = $this->repo->show($uid, $select, $relation);

        if ($data['address'] && $data['current_address'] == null) {
            $data['is_residence_same'] = true;
        } else {
            $data['is_residence_same'] = false;
        }

        // get projects and tasks if any
        $projects = [];
        $asPicProjects = $this->projectRepo->list('id,name,uid,project_date,created_at', '', [], [
            [
                'relation' => 'personInCharges',
                'query' => 'pic_id = '.$data->id,
            ],
        ]);
        $asPicProjects = collect((object) $asPicProjects)->map(function ($item) {
            return [
                'id' => $item->uid,
                'name' => $item->name,
                'position' => __('global.asPicProject'),
                'project_date' => date('d F Y', strtotime($item->project_date)),
                'assign_at' => date('d F Y', strtotime($item->created_at)),
                'detail_task' => [],
            ];
        })->toArray();
        $projects = array_merge($projects, $asPicProjects);

        $asPicTaskRaw = $this->taskRepo->list('id,project_id,name,created_at,start_working_at,uid,created_at', '', ['project:id,name,uid,project_date'], [
            [
                'relation' => 'pics',
                'query' => 'employee_id = '.$data->id,
            ],
        ])->groupBy('project_id')->all();
        $asPicTask = [];
        $a = 0;
        foreach ($asPicTaskRaw as $projectId => $value) {
            foreach ($value as $task) {
                $asPicTask[$a] = [
                    'name' => $task->project->name,
                    'id' => $task->project->uid,
                    'position' => __('global.haveCountTask', ['countTask' => $value->count()]),
                    'project_date' => date('d F Y', strtotime($task->project->project_date)),
                    'assign_at' => date('d F Y', strtotime($task->created_at)),
                    'detail_task' => collect($value)->map(function ($detailTask) {
                        return [
                            'name' => $detailTask->name,
                            'id' => $detailTask->uid,
                            'start_working_at' => $detailTask->start_working_at ? date('d F Y, H:i', strtotime($detailTask->start_working_at)) : null,
                            'assign_at' => date('d F Y', strtotime($detailTask->created_at)),
                        ];
                    })->toArray(),
                ];
            }

            $a++;
        }
        $projects = array_merge($projects, $asPicTask);
        $data['project_detail'] = $projects;

        $data['current_address'] = $data->is_residence_same ? $data->address : $data->current_address;

        $data['join_date_format'] = date('d F Y', strtotime($data->join_date));
        $data['length_of_service'] = getLengthOfService($data->join_date);

        $data['level_staff_text'] = \App\Enums\Employee\LevelStaff::generateLabel('staff');

        $data['basic_salary'] = number_format($data->basic_salary, 0, '', '');

        $branch = $data->branch;
        unset($data['branch']);
        $data['branch'] = $branch ? $branch->name : '-';

        $data['boss_uid'] = null;
        $data['approval_line'] = null;
        if ($data->boss_id) {
            $bossData = $this->repo->show('dummy', 'id,uid,name', [], 'id = '.$data->boss_id);
            $data['boss_uid'] = $bossData->uid;
            $data['approval_line'] = $bossData->name;
        }

        $currentJobLevelId = $data['job_level_id'] != null ? $data['job_level_id'] : 0;
        $jobLevel = $this->jobLevelRepo->show(
            uid: 0,
            select: 'id,uid',
            where: 'id = '.$currentJobLevelId
        );
        $data['job_level_uid'] = $jobLevel ? $jobLevel->uid : null;

        return $data->toArray();
    }

    /**
     * Get specific data by id
     */
    public function show(
        string $uid,
        string $select = '*',
        array $relation = []
    ): array {
        try {
            // validate permission
            $user = auth()->user();
            $employeeId = getIdFromUid($uid, new \Modules\Hrd\Models\Employee);

            if (! $employeeId) {
                throw new EmployeeNotFound;
            }

            if (
                $user->email != config('app.root_email') &&
                ! $user->is_director &&
                ! isSuperUserRole() &&
                ! isHrdRole()
            ) {
                if ($user->employee_id != $employeeId) { // only its user can access their information
                    return errorResponse('not allowed', ['redirect' => '/admin/dashboard'], 403);
                }
            }

            $data = $this->getDetailEmployee($uid, $select);

            return generalResponse(
                'Success',
                false,
                $data
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $positionData = $this->positionRepo->show(uid: $data['position_id'], select: 'id,division_id', relation: ['division:id,uid']);
            $data['division_id'] = $positionData->division->uid;
            $data['position_uid'] = $data['position_id'];
            $data['job_level_uid'] = $data['job_level_id'];
            $data['position_id'] = $this->generalService->getIdFromUid($data['position_id'], new PositionBackup);
            if (! empty($data['boss_id'])) {
                $data['boss_id'] = $this->generalService->getIdFromUid($data['boss_id'], new Employee);
            }

            $jobLevel = $this->jobLevelRepo->show(uid: $data['job_level_id'], select: 'id,name');
            $data['job_level_id'] = $jobLevel->id;
            $data['level_staff'] = $jobLevel->name;
            $data['avatar_color'] = $this->generalService->generateRandomColor($data['email']);

            $employee = $this->repo->store(
                collect($data)->except(['password', 'invite_to_erp', 'invite_to_talenta'])->toArray()
            );

            // invite to ERP if needed
            if (
                (isset($data['invite_to_erp'])) &&
                ($data['invite_to_erp'] == 1)
            ) {

                $user = $this->userService->mainServiceStoreUser(
                    collect($data)->only([
                        'password',
                        'email',
                        'role_id',
                    ])
                        ->merge(['employee_id' => $employee->uid, 'is_external_user' => 0])
                        ->toArray()
                );

                // update user id
                $this->repo->update([
                    'user_id' => $user->id,
                ], $employee->uid);
            }

            // invite to Talenta
            // if ((isset($data['invite_to_talenta'])) && ($data['invite_to_talenta'])) {
            //     $this->talentaService->setUrl('store_employee');
            //     $this->talentaService->setUrlParams($this->talentaService->buildEmployeePayload($data));
            //     $response = $this->talentaService->makeRequest();

            //     // Throw error when it failed
            //     if ($response['message'] != 'success') {
            //         logging('ERROR SAVING TALENT', $response);
            //         throw new Exception(__('notification.failedSaveToTalenta'));
            //     }

            //     // update talenta user ID
            //     $this->talentaService->setUrl('detail_employee');
            //     $this->talentaService->setUrlParams(['email' => $data['email']]);
            //     $currentTalentaEmployee = $this->talentaService->makeRequest();

            //     $talentaUserId = $currentTalentaEmployee['data']['employees'][0]['user_id'];

            //     $this->repo->update([
            //         'talenta_user_id' => $talentaUserId
            //     ], $employee->uid);
            // }

            DB::commit();

            return generalResponse(
                message: __('notification.successCreateEmployee'),
                error: false,
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update personal data - basic info
     */
    public function updateBasicInfo(array $payload, string $employeeUid): array
    {
        try {
            $this->repo->update($payload, $employeeUid);

            // get detail to refresh data in the front page
            $data = $this->getDetailEmployee($employeeUid, '*');

            return generalResponse(
                __('global.successEditEmployeeData'),
                false,
                $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update personal data - identity & address
     */
    public function updateIdentity(array $payload, string $employeeUid): array
    {
        try {
            $this->repo->update($payload, $employeeUid);

            // get detail to refresh data in the front page
            $data = $this->getDetailEmployee($employeeUid, '*');

            return generalResponse(
                __('global.successEditEmployeeData'),
                false,
                $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update data
     */
    public function update(
        array $data,
        string $uid = '',
        string $where = ''
    ): array {
        DB::beginTransaction();
        try {
            $data['position_id'] = $this->generalService->getIdFromUid($data['position_id'], new PositionBackup);
            if (! empty($data['boss_id'])) {
                $data['boss_id'] = $this->generalService->getIdFromUid($data['boss_id'], new Employee);
            }

            // if ((isset($data['is_residence_same'])) && ($data['is_residence_same'])) {
            //     $data['current_address'] = $data['address'];
            // }

            $data['job_level_id'] = $this->generalService->getIdFromUid($data['job_level_id'], new JobLevel);

            $this->repo->update(
                collect($data)->except(['password', 'invite_to_erp', 'invite_to_talenta'])->toArray(),
                $uid
            );

            \Illuminate\Support\Facades\Cache::forget('maximumProjectPerPM');

            DB::commit();

            return generalResponse(
                __('global.successUpdateEmployee'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Delete selected data
     */
    public function delete(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, 'id,name,uid', [
                'projects:id,project_id,pic_id',
            ]);

            $employeeErrorStatus = false;

            if (count($data->projects) > 0) {
                $employeeErrorRelation[] = 'projects';
                $employeeErrorStatus = true;
            }

            if ($employeeErrorStatus) {
                throw new EmployeeException(__('global.employeeRelationFound', [
                    'name' => $data->name,
                    'relation' => implode(' and ', $employeeErrorRelation),
                ]));
            }

            $this->repo->delete($uid);

            \Illuminate\Support\Facades\Cache::forget('maximumProjectPerPM');

            return generalResponse(
                __('global.successDeletePosition'),
                false,
                [],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    public function validateRelation() {}

    /**
     * Delete bulk data
     */
    public function bulkDelete(array $ids): array
    {
        DB::beginTransaction();

        try {
            foreach ($ids as $id) {
                $employee = $this->repo->show(
                    uid: $id,
                    select: 'id,name,email',
                    relation: [
                        'tasks:id,project_task_id,employee_id',
                        'user:id,employee_id,uid',
                        'projects:id,project_id,pic_id',
                    ],
                );

                if ($employee->projects->count() > 0 || $employee->tasks->count() > 0) {
                    DB::rollBack();

                    return errorResponse(__('notification.cannotDeleteEmployeeBcsRelation'));
                }

                $this->repo->update([
                    'status' => Status::Deleted->value,
                    'email' => $employee->email.'_deleted',
                ], uid: $id);
            }

            // TODO: Check all equipments

            // remove access to system
            if ($employee->user) {
                $this->userService->bulkDelete(
                    ids: [$employee->user->uid]
                );
            }

            // TODO:: Delete talenta access

            DB::commit();

            return generalResponse(
                message: __('global.successDeleteEmployee'),
                error: false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get total project manager in company
     * And stored to cache
     */
    protected function getMaximumProjectPerPM()
    {
        $data = \Illuminate\Support\Facades\Cache::get('maximumProjectPerPM');

        if (! $data) {
            $projectManagerPosition = json_decode(getSettingByKey('position_as_project_manager'), true);

            $projectManagerPosition = collect($projectManagerPosition)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
            })->toArray();

            $condition = implode("','", $projectManagerPosition);
            $condition = "('".$condition."')";

            $employees = $this->repo->list('id,name', 'position_id in '.$condition);

            $data = \Illuminate\Support\Facades\Cache::rememberForever('maximumProjectPerPM', function () use ($employees) {
                return count($employees);
            });
        }

        return $data;
    }

    public function getProjectManagers()
    {
        $whereHas = [];

        $date = request('date') ? date('Y-m-d', strtotime(request('date'))) : '';
        $month = request('date') ? date('Y-m', strtotime(request('date'))) : '';

        $projectManagerCount = $this->getMaximumProjectPerPM();
        $maximumProjectPerPM = $projectManagerCount - 1;

        $relation = [
            'projects:id,pic_id,project_id',
            'projects.project:id,name,project_date',
        ];

        if (! empty($month)) {
            $endDateOfMonth = Carbon::createFromDate((int) date('Y', strtotime(request('date'))), (int) date('m', strtotime(request('date'))), 1)
                ->endOfMonth()
                ->format('d');

            $startDate = date('Y-m', strtotime(request('date'))).'-01';
            $endDate = date('Y-m', strtotime(request('date'))).'-'.$endDateOfMonth;

            $relation = [
                'projects' => function ($query) use ($startDate, $endDate) {
                    $query->selectRaw('id,pic_id,project_id')
                        ->whereHas('project', function ($q) use ($startDate, $endDate) {
                            $q->whereRaw("project_date >= '".$startDate."' and project_date <= '".$endDate."'");
                        });
                },
            ];
        }

        $positionAsProjectManager = json_decode(getSettingByKey('position_as_project_manager'), true);

        if ($positionAsProjectManager) {
            $positionCondition = implode("','", $positionAsProjectManager);
            $positionCondition = "('".$positionCondition."')";
            $whereHas[] = [
                'relation' => 'position',
                'query' => 'uid IN '.$positionCondition,
            ];
        }

        $data = $this->repo->list(
            'id, uid as value, name as title',
            'status != '.\App\Enums\Employee\Status::Inactive->value,
            $relation,
            '',
            '',
            $whereHas
        );

        $employees = collect($data)->map(function ($item) use ($date, $month, $maximumProjectPerPM) {
            $projects = collect($item->projects)->pluck('project.project_date')->values();
            $item['workload_on_date'] = 0;
            if (! empty($date)) {
                $filter = collect($projects)->filter(function ($filter) use ($date, $month) {
                    $dateStart = date('Y-m-d', strtotime($month.'-01'));

                    return $filter == $date;
                })->values();
            }

            $totalProject = $item->projects->count();

            // coloring options based on project manager maximum project
            if ($totalProject > $maximumProjectPerPM) {
                $coloring = 'red';
            } elseif ($totalProject == $maximumProjectPerPM) {
                $coloring = 'orange-darken-4';
            } elseif (
                ($totalProject - $maximumProjectPerPM) &&
                ($totalProject - $maximumProjectPerPM == 1)
            ) {
                $coloring = 'red-lighten-2';
            } else {
                $coloring = 'green-accent-3';
            }

            $item['workload_on_date'] = $totalProject;

            return [
                'value' => $item->value,
                'title' => $item->title,
                'workload_on_date' => $item->workload_on_date,
                'coloring' => $coloring,
            ];
        })->sortBy('workload_on_date', SORT_NATURAL)->values();

        return generalResponse(
            'success',
            false,
            $employees->toArray(),
        );
    }

    public function readFile($file)
    {
        $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\EmployeeImport, $file);

        $response = $data['Fulltime Compile'];

        [
            $nipKey, $nameKey, $nicknameKey, $companyKey, $jobNameKey, $levelKey, $statusKey, $joinDateKey, $startReviewProbationKey,
            $probationStatusKey, $endProbationKey, $exitDate, $genderKey, $phoneKey, $emailKey, $educationKey, $schoolNameKey, $majorKey,
            $graduationYearKey, $idNumberKey, $bankNameKey, $bankAccountKey, $accountHolderNameKey, $pobKey, $dobKey, $religionKey, $martialKey,
            $addressKey, $postalCodeKey, $currentAddressKey, $bloodTypeKey, $contactNumberKey, $contactNameKey, $contactRelationKey, $placementKey, $referalKey, $bossIdKey] = [
                2, 4, 5, 6, 7, 8, 9, 10, 11,
                13, 14, 19, 20, 21, 22, 23, 24, 25,
                26, 27, 33, 34, 35, 36, 37, 38, 39,
                41, 42, 43, 44, 45, 46, 47, 49, 50, 51,
            ];

        $employees = [];
        foreach ($response as $key => $row) {
            $jobName = ltrim(rtrim($row[$jobNameKey]));
            $positionData = \Modules\Company\Models\Position::select('id')
                ->whereRaw("lower(name) = '".strtolower($jobName)."'")
                ->first();

            $employees[] = [
                'employee_id' => $row[$nipKey],
                'name' => $row[$nameKey],
                'nickname' => $row[$nicknameKey],
                'email' => $row[$emailKey],
                'phone' => $row[$phoneKey] ?? 0,
                'id_number' => $row[$idNumberKey] ?? 0,
                'religion_raw' => $row[$religionKey],
                'religion' => $row[$religionKey] ? \App\Enums\Employee\Religion::generateReligion($row[$religionKey]) : \App\Enums\Employee\Religion::Islam->value,
                'martial_status_raw' => $row[$martialKey],
                'martial_status' => $row[$martialKey] ? \App\Enums\Employee\MartialStatus::generateMartial($row[$martialKey]) : null,
                'address' => $row[$addressKey] ?? 'belum diisi',
                'postal_code' => $row[$postalCodeKey] ?? 0,
                'current_address' => $row[$currentAddressKey],
                'blood_type' => $row[$bloodTypeKey],
                'date_of_birth' => $row[$dobKey] ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $row[$dobKey])->format('Y-m-d') : '1970-01-01',
                'place_of_birth' => $row[$pobKey] ?? 'belum diisi',
                'dependant' => '',
                'gender_raw' => $row[$genderKey],
                'gender' => $row[$genderKey] ? \App\Enums\Employee\Gender::generateGender($row[$genderKey]) : null,
                'bank_detail' => [
                    [
                        'bank_name' => $row[$bankNameKey],
                        'account_number' => $row[$bankAccountKey],
                        'account_holder_name' => $row[$accountHolderNameKey],
                        'is_active' => true,
                    ],
                ],
                'relation_contact' => [
                    'name' => $row[$contactNameKey],
                    'phone' => $row[$contactNumberKey],
                    'relation' => $row[$contactRelationKey],
                ],
                'education_raw' => $row[$educationKey],
                'education' => $row[$educationKey] ? \App\Enums\Employee\Education::generateEducation($row[$educationKey]) : null,
                'education_name' => $row[$schoolNameKey],
                'education_major' => $row[$majorKey],
                'education_year' => $row[$graduationYearKey],
                'position_raw' => $row[$jobNameKey],
                'position_id' => $positionData->id ?? 0,
                'boss_id' => $row[$bossIdKey],
                'level_staff_raw' => $row[$levelKey],
                'level_staff' => $row[$levelKey] ? \App\Enums\Employee\LevelStaff::generateLevel($row[$levelKey]) : null,
                'status_raw' => $row[$statusKey],
                'status' => $row[$statusKey] ? \App\Enums\Employee\Status::generateStatus($row[$statusKey]) : null,
                'placement' => $row[$placementKey],
                'join_date' => $row[$joinDateKey] ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $row[$joinDateKey])->format('Y-m-d') : null,
                'start_review_probation_date' => $row[$startReviewProbationKey] ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $row[$startReviewProbationKey])->format('Y-m-d') : null,
                'probation_status_raw' => $row[$probationStatusKey],
                'probation_status' => $row[$probationStatusKey] ? \App\Enums\Employee\ProbationStatus::generateStatus($row[$probationStatusKey]) : null,
                'end_probation_date' => $row[$endProbationKey] ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $row[$endProbationKey])->format('Y-m-d') : null,
                'company_name' => $row[$companyKey],
            ];
        }

        unset($employees[0]);

        return array_values(array_filter($employees));
    }

    protected function employeeRequirementList()
    {
        return [
            'employee_id',
            'name',
            'email',
            'phone',
            'id_number',
            'religion',
            'martial_status',
            'address',
            'postal_code',
            'date_of_birth',
            'place_of_birth',
            'gender',
            'education',
            'education_name',
            'education_major',
            'education_year',
            'position_id',
            'level_staff',
            'status',
            'join_date',
        ];
    }

    /**
     * Function to handle import data
     * Create a new one if not exists
     * And edit if exists
     *
     * Handle Boss id in the last process
     */
    public function submitImport(array $response): array
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $response = collect($response)->map(function ($item) {
                $item['bank_detail'] = json_encode($item['bank_detail']);
                $item['relation_contact'] = json_encode($item['relation_contact']);

                return $item;
            })->filter(function ($filter) {
                return ! $filter['wrong_format'];
            })->values()->toArray();

            foreach ($response as $employee) {
                unset($employee['level_staff_raw']);
                unset($employee['probation_status_raw']);
                unset($employee['status_raw']);
                unset($employee['levet_staff_raw']);
                unset($employee['gender_raw']);
                unset($employee['martial_status_raw']);
                unset($employee['religion_raw']);
                unset($employee['education_raw']);
                unset($employee['position_raw']);

                $employee['boss_id'] = null;

                $check = $this->repo->show('dummy', 'id', [], "lower(employee_id) = '".strtolower($employee['employee_id'])."'");

                if ($check) {
                    $this->repo->update(collect($employee)->except(['boss_id', 'wrong_format', 'wrong_data'])->toArray(), '', "lower(employee_id) = '".strtolower($employee['employee_id'])."'");
                } else {
                    $this->repo->store(collect($employee)->except(['boss_id', 'wrong_format', 'wrong_data'])->toArray());
                }
            }

            // handle boss id
            foreach ($response as $employee) {
                if ($employee['boss_id']) {
                    $bossId = $this->repo->show('dummy', 'id,employee_id', [], "lower(employee_id) = '".strtolower($employee['boss_id'])."'");

                    if ($bossId) {
                        $this->repo->update(
                            ['boss_id' => $bossId->id],
                            'dummy',
                            "lower(employee_id) = '".strtolower($employee['employee_id'])."'"
                        );
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return generalResponse(
                __('global.successImportData'),
                false,
            );
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollBack();

            return errorResponse($th);
        }
    }

    public function import($file)
    {
        $response = $this->readFile($file);

        // validate data
        $output = [];
        foreach ($response as $key => $employee) {
            $output[$key] = $employee;
            $output[$key]['wrong_format'] = false;

            $wrong = [];

            foreach ($this->employeeRequirementList() as $requirement) {
                if (
                    (isset($employee[$requirement])) &&
                    (
                        ! $employee[$requirement] ||
                        empty($employee[$requirement]) ||
                        $employee[$requirement] == null ||
                        $employee[$requirement] == 'null'
                    )
                ) {
                    $output[$key]['wrong_format'] = true;
                    $message = 'global.'.snakeToCamel($requirement).'Required';
                    array_push($wrong, trans($message));
                }

                if (! isset($employee[$requirement])) {
                    $output[$key]['wrong_format'] = true;
                    $message = 'global.'.snakeToCamel($requirement).'Required';
                    array_push($wrong, trans($message));
                }
            }

            // position validation
            if (
                (isset($employee['position_id'])) &&
                ($employee['position_id'] == 0)
            ) {
                $output[$key]['wrong_format'] = true;
                array_push($wrong, __('global.positionNotRegistered'));
            }

            // banks validation
            if (
                (isset($employee['bank_detail'])) &&
                (count($employee['bank_detail']) > 0) &&
                (
                    empty($employee['bank_detail'][0]['bank_name']) ||
                    empty($employee['bank_detail'][0]['account_number']) ||
                    empty($employee['bank_detail'][0]['account_holder_name'])
                )
            ) {
                $output[$key]['wrong_format'] = true;
                array_push($wrong, __('global.bankRequired'));
            }

            // relation validation
            if (
                (isset($employee['relation_contact'])) &&
                (count($employee['relation_contact']) > 0) &&
                (
                    empty($employee['relation_contact']['phone']) ||
                    empty($employee['relation_contact']['name']) ||
                    empty($employee['relation_contact']['relation'])
                )
            ) {
                $output[$key]['wrong_format'] = true;
                array_push($wrong, __('global.relationContactRequired'));
            }

            $output[$key]['wrong_data'] = $wrong;
        }

        return generalResponse(
            'Success',
            false,
            $output
        );
    }

    public function downloadTemplate()
    {
        try {
            return \Illuminate\Support\Facades\Storage::download('static-file/employee.xlsx');
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to store employee family membet
     */
    public function storeFamily(array $payload, string $employeeUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee);

            $payload['employee_id'] = $employeeId;

            $this->employeeFamilyRepo->store($payload);

            DB::commit();

            return generalResponse(
                __('global.successAddFamily'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to store employee family membet
     *
     * @param  string  $employeeUid
     */
    public function updateFamily(array $payload, string $familyUid): array
    {
        DB::beginTransaction();
        try {
            $this->employeeFamilyRepo->update($payload, $familyUid);

            DB::commit();

            return generalResponse(
                __('global.successUpdateFamily'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get family list of each employee
     */
    public function initFamily(string $employeeUid): array
    {
        $employeeId = $this->generalService->getIdFromUid($employeeUid, new Employee);
        $data = $this->employeeFamilyRepo->list(
            select: '*',
            where: "employee_id = {$employeeId}"
        );

        $output = collect((object) $data)->map(function ($item) {
            return [
                'uid' => $item->uid,
                'name' => $item->name,
                'relationship' => $item->relationship_text,
                'date_of_birth' => date('d F Y', strtotime($item->date_of_birth)),
                'id_number' => $item->id_number,
                'gender' => $item->gender_text,
                'job' => $item->job,
                'religion' => $item->religion_text,
                'martial_status' => $item->martial_status_status,
            ];
        })->values()
            ->toArray();

        return generalResponse(
            message: 'success',
            error: false,
            data: $data->toArray()
        );
    }

    /**
     * Delete family data
     */
    public function deleteFamily(string $familyUid): array
    {
        try {
            $this->employeeFamilyRepo->delete($familyUid);

            return generalResponse(
                __('global.successDeleteFamily'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get family list of each employee
     */
    public function initEmergency(string $employeeUid): array
    {
        $data = $this->employeeEmergencyRepo->list('*', 'employee_id = '.getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee));

        $output = collect((object) $data)->map(function ($item) {
            return [
                'uid' => $item->uid,
                'name' => $item->name,
                'relation' => $item->relation,
                'phone' => $item->phone,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    /**
     * Function to store employee emergency contact
     */
    public function storeEmergency(array $payload, string $employeeUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee);

            $payload['employee_id'] = $employeeId;
            $this->employeeEmergencyRepo->store($payload);

            DB::commit();

            return generalResponse(
                __('global.successAddEmergencyContact'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to store employee emergency contact
     *
     * @param  string  $employeeUid
     */
    public function updateEmergency(array $payload, string $emergencyUid): array
    {
        DB::beginTransaction();
        try {
            $this->employeeEmergencyRepo->update($payload, $emergencyUid);

            DB::commit();

            return generalResponse(
                __('global.successUpdateEmergencyContact'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Delete emergency contact
     *
     * @param  string  $familyUid
     */
    public function deleteEmergency(string $emergencyUid): array
    {
        try {
            $this->employeeEmergencyRepo->delete($emergencyUid);

            return generalResponse(
                __('global.successDeleteEmergencyContact'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * update employment data
     *
     * @param  array  $data
     */
    public function updateEmployment(array $payload, string $employeeUid): array
    {
        try {
            $payload['position_id'] = getIdFromUid($payload['position_id'], new \Modules\Company\Models\PositionBackup);
            if (
                (isset($payload['boss_id'])) &&
                ($payload['boss_id'])
            ) {
                $payload['boss_id'] = getIdFromUid($payload['boss_id'], new \Modules\Hrd\Models\Employee);
            }

            $this->repo->update(collect($payload)->except(['level'])->toArray(), $employeeUid);

            // get detail to refresh data in the front page
            $data = $this->getDetailEmployee($employeeUid, '*');

            return generalResponse(
                __('global.successUpdateEmployment'),
                false,
                $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * This function is consumed by cron job. Check Modules\Hrd\app\Console\CheckEmployeeResign.php
     */
    public function checkEmployeeWhoResignToday(): void
    {
        DB::beginTransaction();

        try {
            $data = $this->employeeResignRepo->list(
                select: 'id,employee_id',
                where: 'resign_date = NOW()',
                relation: [
                    'employee:id,uid',
                ]
            );

            foreach ($data as $employee) {
                $this->turnOffEmployee(
                    resignDate: date('Y-m-d'),
                    employeeUid: $employee->employee->uid,
                    employeeId: $employee->employee_id,
                    afterCommit: true
                );
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            errorMessage($th);
        }
    }

    /**
     * Turn off employee
     * This function will be deactive user, and then send notification to the root user to delete office email in the server manually
     */
    public function turnOffEmployee(
        string $resignDate,
        string $employeeUid,
        string|int $employeeId,
        bool $afterCommit = false
    ): void {
        $employee = $this->repo->show(uid: $employeeUid, select: 'email');

        $this->repo->update(
            data: [
                'status' => Status::Inactive->value,
                'end_date' => $resignDate,
            ],
            uid: $employeeUid
        );

        // then change user_status in users table to false
        $this->userRepo->update(
            data: [
                'user_status' => false,
            ],
            key: 'employee_id',
            value: $employeeId
        );

        // notify root user to delete office email in the server
        $this->deleteOfficeEmailQueueRepo->store(data: [
            'employee_id' => $employeeId,
            'email' => $employee->email,
        ]);

        if (! $afterCommit) {
            DeleteOfficeEmailJob::dispatch();
        } else {
            DeleteOfficeEmailJob::dispatch()->afterCommit();
        }
    }

    /**
     * Main logic for employee resignation
     *
     * @param  array  $data  With these following structure
     *                       - string $reason
     *                       - string $resign_date
     *                       - string $severance
     *                       - string $employee_uid
     */
    public function mainResignLogic(
        array $data,
        bool $useTransaction = false,
        bool $notifyAfterCommit = false
    ): array {
        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            $employeeId = $this->generalService->getIdFromUid($data['employee_uid'], new Employee);

            $employee = $this->repo->show(uid: $data['employee_uid'], select: 'id,position_id,status,name');

            // validate employee work, check relation to the production task
            $tasks = $this->taskRepo->list(
                select: 'id',
                whereHas: [
                    [
                        'relation' => 'pics',
                        'query' => "employee_id = {$employee->id}",
                    ],
                ],
                where: 'status = '.TaskStatus::OnProgress->value
            );

            if ($tasks->isNotEmpty()) {
                return errorResponse(message: __('notification.employeeHasOngoingTasks'));
            }

            $this->employeeResignRepo->store(data: [
                'employee_id' => $employeeId,
                'reason' => $data['reason'],
                'resign_date' => date('Y-m-d', strtotime($data['resign_date'])),
                'severance' => $data['severance'],
                'current_position_id' => $employee->position_id,
                'current_employee_status' => $employee->status,
            ]);

            // if today date is the same with resign_date, change employee status otherwise do not change anything in employee model
            $resignDate = Carbon::parse($data['resign_date']);
            $now = Carbon::now();
            $diff = $now->diffInDays($resignDate, false);

            // define proper message
            $message = __('notification.resignHasBeenOnScheduled');

            if ($diff <= 0) {
                $this->turnOffEmployee(
                    resignDate: date('Y-m-d', strtotime($data['resign_date'])),
                    employeeUid: $data['employee_uid'],
                    employeeId: $employeeId,
                    afterCommit: $notifyAfterCommit
                );

                $message = __('notification.successResign', ['name' => $employee->name]);
            }

            if ($useTransaction) {
                DB::commit();
            }

            return generalResponse(
                message: $message
            );
        } catch (\Throwable $th) {
            if ($useTransaction) {
                DB::rollBack();
            }

            return errorResponse($th);
        }
    }

    /**
     * Employee is resign
     *
     * @param  array<string, string>  $data
     * @param string employeeUid
     * @return array
     */
    public function resign(array $data, string $employeeUid)
    {
        DB::beginTransaction();
        try {
            $data['employee_uid'] = $employeeUid;

            $resign = $this->mainResignLogic(data: $data, notifyAfterCommit: true); // will return message

            DB::commit();

            return $resign;
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get employment detail as a chart option that can be comsumed on the frontend
     * Frontend is used Apexchart
     *
     * Here we only show 3 Employee status like Permanent, Contract and Probation
     */
    public function getEmploymentChart(object $employees): array
    {
        try {
            $output = Cache::get(CacheKey::HrDashboardEmploymentStatus->value);
            if (! $output) {
                $output = Cache::rememberForever(CacheKey::HrDashboardEmploymentStatus->value, function () use ($employees) {
                    $statuses = [
                        Status::Permanent->value,
                        Status::Contract->value,
                        Status::Probation->value,
                    ];

                    $series = [];
                    $table = [
                        ['title' => 'Total', 'value' => $employees->count(), 'type' => 'header'],
                    ];
                    foreach ($statuses as $status) {
                        $totalPerStatus = collect((object) $employees)->filter(function ($filter) use ($status) {
                            return $filter->status->value == $status;
                        })->count();

                        // create series configuration
                        $series[] = [
                            'name' => Status::generateLabel($status),
                            'data' => [$totalPerStatus],
                            'color' => Status::generateChartColor($status),
                        ];

                        // add more $table configuration
                        $percentage = $totalPerStatus / $employees->count() * 100;
                        $table[] = [
                            'title' => Status::generateLabel($status),
                            'value' => $totalPerStatus,
                            'valuePercentage' => number_format(num: $percentage, decimals: 0).'%',
                            'color' => Status::generateChartColor($status),
                            'type' => 'body',
                        ];
                    }

                    // called chart service function to create stacked bar option
                    $options = $this->chart->buildStackedBarOptions();

                    return [
                        'series' => $series,
                        'table' => $table,
                        'options' => $options,
                    ];
                });
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get length of service as a chart option that can be comsumed on the frontend
     * Frontend is used Apexchart
     *
     * Here we divide by 3 categories: 0-1 yr, 1-3 yr, 3-5 yr, 5-10 yr
     */
    public function getLengthOfServiceChart(object $employees): array
    {
        try {
            $output = Cache::get(CacheKey::HrDashboardLoS->value);
            if (! $output) {
                $output = Cache::rememberForever(CacheKey::HrDashboardLoS->value, function () use ($employees) {
                    // 0 - 1 year
                    $firstData = collect($employees)->filter(function ($filter) {
                        return $filter->length_of_service_year <= 1;
                    })->count();

                    // 1 - 3 year
                    $secondData = collect($employees)->filter(function ($filter) {
                        return $filter->length_of_service_year >= 1.1 && $filter->length_of_service_year <= 3;
                    })->count();

                    // 3 - 5 year
                    $thirdData = collect($employees)->filter(function ($filter) {
                        return $filter->length_of_service_year >= 3.1 && $filter->length_of_service_year <= 5;
                    })->count();

                    // 5 - 10
                    $lastData = collect($employees)->filter(function ($filter) {
                        return $filter->length_of_service_year >= 5.1;
                    })->count();

                    $series = $this->chart->buildBarSeries(name: 'Length of Service', data: [$firstData, $secondData, $thirdData, $lastData]);

                    $options = $this->chart->buildBarOptions(xaxisCategories: ['0-1 yr', '1-3 yr', '3-5 yr', '5-10 yr']);

                    return [
                        'series' => $series,
                        'options' => $options,
                    ];
                });
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get active staff as a chart option that can be comsumed on the frontend
     * Frontend is used Apexchart
     *
     * Here we only displays data for the past 3 months
     */
    public function getActiveStaffChart(): array
    {
        try {
            $now = Carbon::now();
            $months = [
                $now,
                Carbon::now()->subMonths(1),
                Carbon::now()->subMonths(2),
            ];

            $data = [];
            foreach ($months as $month) {
                // get data per month
                $active = $this->employeeActiveRepo->show(uid: 'select', select: 'id,number_of_employee', where: "month = {$month->format('m')} AND year = {$month->format('Y')}");

                $data[] = $active->number_of_employee ?? 0;
            }

            $series = $this->chart->buildBarSeries(name: 'Length of Service', data: $data);

            $options = $this->chart->buildBarOptions(xaxisCategories: collect($months)->map(function ($item) {
                return $item->format('M');
            })->toArray());

            return generalResponse(
                message: 'Success',
                data: [
                    'series' => $series,
                    'options' => $options,
                    'months' => $months,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get gender diversity as a chart option that can be comsumed on the frontend
     * Frontend is used Apexchart
     */
    public function getGenderDiversityChart(object $employees): array
    {
        try {
            $male = collect($employees)->filter(function ($filter) {
                return $filter->gender->value == Gender::Male->value;
            })->count();

            $female = collect($employees)->filter(function ($filter) {
                return $filter->gender->value == Gender::Female->value;
            })->count();

            $series = [$male, $female];

            $options = [
                'chart' => [
                    'width' => 200,
                    'height' => 200,
                    'type' => 'pie',
                ],
                'labels' => [Gender::Male->label(), Gender::Female->label()],
                'legend' => [
                    'show' => true,
                ],
            ];

            $total = array_sum([$male, $female]);
            $table = [
                ['title' => 'Total', 'value' => $total, 'type' => 'header'],
                ['title' => Gender::Male->label(), 'value' => $male, 'valuePercentage' => number_format(num: $male / $total * 100), 'color' => '#009bde', 'type' => 'body'],
                ['title' => Gender::Female->label(), 'value' => $female, 'valuePercentage' => number_format(num: $female / $total * 100), 'color' => '#f96d01', 'type' => 'body'],
            ];

            return generalResponse(
                message: 'Success',
                data: [
                    'series' => $series,
                    'table' => $table,
                    'options' => $options,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all job level of the company as a chart option that can be comsumed on the frontend
     * Frontend is used Apexchart
     */
    public function getJobLevelChart(object $employees): array
    {
        try {
            $output = Cache::get(CacheKey::HrDashboardJobLevel->value);
            if (! $output || empty($output)) {
                $output = Cache::rememberForever(CacheKey::HrDashboardJobLevel->value, function () use ($employees) {
                    $jobLevels = $this->jobLevelRepo->list(
                        select: 'id,name'
                    );

                    $series = [];
                    $table = [
                        ['title' => 'Total', 'value' => $employees->count(), 'type' => 'header'],
                    ];
                    foreach ($jobLevels as $jobLevel) {
                        $numberOfJob = collect($employees)->filter(function ($filter) use ($jobLevel) {
                            return $filter->job_level_id == $jobLevel->id;
                        })->count();

                        // generate color of each job level
                        $color = generateRandomColor($jobLevel->name);

                        // create series
                        $series[] = [
                            'name' => $jobLevel->name,
                            'data' => [$numberOfJob],
                            'color' => $color,
                        ];

                        // create table data
                        $table[] = [
                            'title' => $jobLevel->name,
                            'value' => $numberOfJob,
                            'valuePercentage' => number_format($numberOfJob / $employees->count() * 100).'%',
                            'color' => $color,
                            'type' => 'body',
                        ];
                    }

                    // called chart service function to create stacked bar option
                    $options = $this->chart->buildStackedBarOptions();

                    return [
                        'series' => $series,
                        'table' => $table,
                        'options' => $options,
                    ];
                });
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get who is off today
     */
    public function getEmployeeOffChart(): array
    {
        try {
            $firstMonthUnixTimestamp = Carbon::now()->startOfMonth()->timestamp;
            $lastMonthUnixTimestamp = Carbon::now()->endOfMonth()->timestamp;
            $todayUnixTimestamp = Carbon::yesterday()->timestamp;

            // get all timeoff in this month
            $timeoffs = $this->employeeTimeoffRepo->list(
                select: 'id,time_off_id,talenta_user_id,policy_name,request_type,file_url,start_date,end_date,status,UNIX_TIMESTAMP(start_date) AS start_timestamp,UNIX_TIMESTAMP(end_date) AS end_timestamp',
                relation: [
                    'employee:id,employee_id,nickname,name,talenta_user_id',
                ],
                where: "UNIX_TIMESTAMP(start_date) >= {$firstMonthUnixTimestamp} AND UNIX_TIMESTAMP(end_date) <= {$lastMonthUnixTimestamp}"
            );

            // get today timeoff
            $todayTimeoff = collect((object) $timeoffs)->filter(function ($filter) use ($todayUnixTimestamp) {
                return $todayUnixTimestamp >= $filter->start_timestamp && $todayUnixTimestamp <= $filter->end_timestamp;
            })->values();

            return generalResponse(
                message: 'Success',
                data: [
                    'today' => $todayTimeoff,
                    // 'timeoff' => $timeoffs,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get age average chart -> Bar Chart
     */
    public function getAgeAverageChart(object $employees): array
    {
        try {
            $output = Cache::get(CacheKey::HrDashboardAgeAverage->value);
            if (! $output) {
                $output = Cache::rememberForever(CacheKey::HrDashboardAgeAverage->value, function () use ($employees) {
                    // < 18 yr
                    $firstData = collect($employees)->filter(function ($filter) {
                        return $filter->human_age < 18;
                    })->count();

                    // 18 - 24
                    $secondData = collect($employees)->filter(function ($filter) {
                        return $filter->human_age >= 18 && $filter->human_age < 24;
                    })->count();

                    // 25 - 34
                    $thirdData = collect($employees)->filter(function ($filter) {
                        return $filter->human_age > 24 && $filter->human_age <= 34;
                    })->count();

                    // 35 - 49 yr
                    $fourthData = collect($employees)->filter(function ($filter) {
                        return $filter->human_age > 34 && $filter->human_age <= 49;
                    })->count();

                    // 50++ yr
                    $lastData = collect($employees)->filter(function ($filter) {
                        return $filter->human_age > 49;
                    })->count();

                    $series = $this->chart->buildBarSeries(name: 'Age Average', data: [$firstData, $secondData, $thirdData, $fourthData, $lastData]);

                    $options = $this->chart->buildBarOptions(xaxisCategories: ['< 18', '18 - 24', '25 - 34', '35 - 49', '50+']);

                    return [
                        'series' => $series,
                        'options' => $options,
                    ];
                });
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Cancel resign of selected employee
     *
     * What to do in this function:
     * 1. Delete resign reason
     * 2. Delete resign_dae in the employees table
     *
     * This function only worked when resign date is greater than now
     */
    public function cancelResign(string $employeeUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = $this->generalService->getIdFromUid($employeeUid, new Employee);

            $employee = $this->repo->show(uid: $employeeUid, select: 'id,email,status');

            // validate data only active employee can be used this action
            if ($employee->status == Status::Inactive || $employee->status == Status::Deleted) {
                return errorResponse(
                    message: __('notification.cannotCancelResignationInactiveOrDeleted')
                );
            }

            $this->repo->update(
                data: [
                    'end_date' => null,
                    'resign_reason' => null,
                ],
                uid: $employeeUid
            );

            $this->employeeResignRepo->delete(
                id: 0,
                where: "employee_id = {$employeeId}"
            );

            $this->deleteOfficeEmailQueueRepo->delete(id: 0, where: "email = '{$employee->email}'");

            DB::commit();

            return generalResponse(
                message: __('notification.resignationHasBeenCanceled')
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * This function will return who have the mosh event number in current year
     */
    public function getTheHighestEventNumberInPic(): array
    {
        try {
            $status = [
                Status::Deleted->value,
                Status::Inactive->value,
                Status::Freelance->value,
                Status::Probation->value,
                Status::WaitingHR->value,
            ];
            $status = collect($status)->implode(',');
            $results = DB::select('CALL get_highest_event_number_for_pic(?)', [$status]);

            if ($results) {
                $output = $results[0]->pic_id;
            } else {
                // get random pic
                $picEmployeeIds = ProjectPersonInCharge::select('pic_id')
                    ->distinct()
                    ->pluck('pic_id')
                    ->toArray();

                // Get random active employee from PICs
                $randomEmployee = Employee::whereIn('id', $picEmployeeIds)
                    ->whereNotIn('status', [0, 5, 6, 7, 8])
                    ->inRandomOrder()
                    ->first(['uid', 'name']); // Get both uid and name if needed

                $output = $randomEmployee ? $randomEmployee->uid : null;
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'uid' => $output,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
