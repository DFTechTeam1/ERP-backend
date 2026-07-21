<?php

namespace Modules\Hrd\Services;

use App\Enums\Cache\CacheKey;
use App\Enums\Employee\Education;
use App\Enums\Employee\Gender;
use App\Enums\Employee\LevelStaff;
use App\Enums\Employee\MartialStatus;
use App\Enums\Employee\OutOfSyncStatus;
use App\Enums\Employee\ProbationStatus;
use App\Enums\Employee\Religion;
use App\Enums\Employee\SalaryType;
use App\Enums\Employee\Status;
use App\Enums\ErrorCode\Code;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Exceptions\EmployeeException;
use App\Exports\EmployeeExport;
use App\Imports\EmployeeImport;
use App\Models\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Services\ChartService;
use App\Services\EncryptionService;
use App\Services\GeneralService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Company\Models\JobLevel;
use Modules\Company\Models\Position;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Notifications\ResignationNotification;
use Modules\Company\Repository\JobLevelRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Data\Employee\BulkSyncEmployeeData;
use Modules\Hrd\Data\Employee\EmployeeChangeSyncData;
use Modules\Hrd\Data\Employee\LinkEmployeeData;
use Modules\Hrd\Data\Resign\ResignData;
use Modules\Hrd\Exceptions\EmployeeNotFound;
use Modules\Hrd\Jobs\DeleteOfficeEmailJob;
use Modules\Hrd\Jobs\SendEmailActivationJob;
use Modules\Hrd\Jobs\SendResignationNotificationJob;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\GreatdayCompany;
use Modules\Hrd\Models\GreatdayCostCenter;
use Modules\Hrd\Models\GreatdayJobGrade;
use Modules\Hrd\Models\GreatdayJobStatus;
use Modules\Hrd\Models\GreatdayWorkLocation;
use Modules\Hrd\Models\OutOfSyncEmployee;
use Modules\Hrd\Repository\DeleteOfficeEmailQueueRepository;
use Modules\Hrd\Repository\EmployeeActiveReportRepository;
use Modules\Hrd\Repository\EmployeeEmergencyContactRepository;
use Modules\Hrd\Repository\EmployeeFamilyRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeResignRepository;
use Modules\Hrd\Repository\EmployeeTimeoffRepository;
use Modules\Hrd\Repository\GreatdayCompanyRepository;
use Modules\Hrd\Repository\GreatdayCostCenterRepository;
use Modules\Hrd\Repository\GreatdayEmploymentStatusRepository;
use Modules\Hrd\Repository\GreatdayJobGradeRepository;
use Modules\Hrd\Repository\GreatdayJobStatusRepository;
use Modules\Hrd\Repository\GreatdayNationalityRepository;
use Modules\Hrd\Repository\GreatdayReligionRepository;
use Modules\Hrd\Repository\GreatdayResignReasonRepository;
use Modules\Hrd\Repository\GreatdayResignTypeRepository;
use Modules\Hrd\Repository\GreatdayShiftPatternRepository;
use Modules\Hrd\Repository\GreatdayTimezoneRepository;
use Modules\Hrd\Repository\GreatdayWorkLocationRepository;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectVjRepository;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeService
{
    /**
     * Change-sync fields that cannot be applied while the employee still has active tasks,
     * because they move the employee's division or approval line mid-work.
     *
     * @var array<int, string>
     */
    private const TASK_GUARDED_CHANGE_FIELDS = ['division', 'boss'];

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

    private $greatdayService;

    private $greatdayTimezoneRepo;

    private $greatdayCostCenterRepo;

    private $greatdayReligionRepo;

    private $greatdayJobGradeRepo;

    private $greatdayEmploymentStatusRepo;

    private $greatdayWorkLocationRepo;

    private $greatdayShiftPatternRepo;

    private $greatdayJobStatusRepo;

    private $greatdayNationalityRepo;

    private $greatdayCompanyRepo;

    private GreatdayResignTypeRepository $greatdayResignTypeRepo;

    private GreatdayResignReasonRepository $greatdayResignReasonRepo;

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
        ProjectTaskPicRepository $projectTaskPicRepo,
        GreatdayService $greatdayService,
        GreatdayTimezoneRepository $greatdayTimezoneRepo,
        GreatdayCostCenterRepository $greatdayCostCenterRepo,
        GreatdayReligionRepository $greatdayReligionRepo,
        GreatdayJobGradeRepository $greatdayJobGradeRepo,
        GreatdayEmploymentStatusRepository $greatdayEmploymentStatusRepo,
        GreatdayWorkLocationRepository $greatdayWorkLocationRepo,
        GreatdayShiftPatternRepository $greatdayShiftPatternRepo,
        GreatdayJobStatusRepository $greatdayJobStatusRepo,
        GreatdayNationalityRepository $greatdayNationalityRepo,
        GreatdayCompanyRepository $greatdayCompanyRepo,
        GreatdayResignTypeRepository $greatdayResignTypeRepo,
        GreatdayResignReasonRepository $greatdayResignReasonRepo
    ) {
        $this->greatdayResignTypeRepo = $greatdayResignTypeRepo;

        $this->greatdayResignReasonRepo = $greatdayResignReasonRepo;

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

        $this->greatdayService = $greatdayService;

        $this->greatdayTimezoneRepo = $greatdayTimezoneRepo;

        $this->greatdayCostCenterRepo = $greatdayCostCenterRepo;

        $this->greatdayReligionRepo = $greatdayReligionRepo;

        $this->greatdayJobGradeRepo = $greatdayJobGradeRepo;

        $this->greatdayEmploymentStatusRepo = $greatdayEmploymentStatusRepo;

        $this->greatdayWorkLocationRepo = $greatdayWorkLocationRepo;

        $this->greatdayShiftPatternRepo = $greatdayShiftPatternRepo;

        $this->greatdayJobStatusRepo = $greatdayJobStatusRepo;

        $this->greatdayNationalityRepo = $greatdayNationalityRepo;

        $this->greatdayCompanyRepo = $greatdayCompanyRepo;
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
                // $filterNames = collect($search['filters'])->pluck('field')->values()->toArray();

                // // append status filter when user is not search for filter. This status filter will be as default filter
                // if (! in_array('status', $filterNames)) {
                //     $search['filters'] = collect($search['filters'])->merge([
                //         [
                //             'field' => 'status',
                //             'condition' => 'not_contain',
                //             'value' => Status::Deleted->value,
                //             'data_type' => 'integer',
                //         ],
                //         [
                //             'field' => 'status',
                //             'condition' => 'not_contain',
                //             'value' => Status::Inactive->value,
                //             'data_type' => 'integer',
                //         ],
                //     ])->toArray();
                // }

                // $where = formatSearchConditions($search['filters'], $where);

                $where = "name like '%{$search}%' and status != ".Status::Inactive->value.' and status != '.Status::Deleted->value;
            } else {
                $where = 'status != '.Status::Deleted->value.' and status != '.Status::Inactive->value;
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
                    'nickname' => $item->nickname,
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
            $position = $this->positionRepo->show(uid: 0, select: 'id', where: "name = 'Modeller'");

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

    public function getVJ(string $projectUid): array
    {
        $positionAsVJ = json_decode(getSettingByKey('position_as_visual_jokey'), true);

        $output = [];

        if ($positionAsVJ) {
            $positionAsVJ = collect($positionAsVJ)->map(function ($item) {
                return getIdFromUid($item, new PositionBackup);
            })->toArray();

            $projectId = getIdFromUid($projectUid, new Project);

            $project = $this->projectRepo->show($projectUid, 'id,project_date');

            $position = implode(',', $positionAsVJ);

            $where = 'position_id IN ('.$position.') and status != '.Status::Inactive->value;

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
            $where .= ' and status != '.Status::Inactive->value;
        } else {
            $where = 'status != '.Status::Inactive->value;
        }

        if (! empty(request('is_production'))) {
            $positionAsProduction = DB::table('settings')
                ->where('key', 'position_as_production')
                ->first();

            if ($positionAsProduction) {
                $SanitizePositionAsProduction = str_replace(
                    ['[', ']'],
                    '',
                    $positionAsProduction->value
                );
                $position = (new PositionRepository)->list(select: 'id', where: "uid IN ({$SanitizePositionAsProduction})");

                if ($position->isNotEmpty()) {
                    $positionIds = $position->pluck('id')->join(',');
                    if (empty($where)) {
                        $where = "position_id IN ({$positionIds})";
                    } else {
                        $where .= " and position_id IN ({$positionIds})";
                    }
                }
            }
        }

        $data = $this->repo->list(
            select: 'uid,id,name,email,avatar,position_id,phone',
            where: $where,
            relation: [
                'position:id,name',
            ]
        );

        $data = collect((object) $data)->map(function ($item) {
            return [
                'value' => $item->uid,
                'title' => $item->name,
                'email' => $item->email,
                'phone' => $item->phone,
                'avatar' => $item->avatar,
                'position' => $item->position ? $item->position->name : '-',
            ];
        })->values()->toArray();

        return generalResponse(
            'success',
            false,
            $data
        );
    }

    public function activateAccount(string $key)
    {
        $encrypter = new EncryptionService;

        $email = $encrypter->decrypt($key, env('SALT_KEY'));

        $this->userRepo->update([
            'email_verified_at' => Carbon::now(),
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

            SendEmailActivationJob::dispatch($userData, $payload['password'])->afterCommit();

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

        $data['level_staff_text'] = LevelStaff::generateLabel('staff');

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
            $employeeId = getIdFromUid($uid, new Employee);

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

            Cache::forget('maximumProjectPerPM');

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

            Cache::forget('maximumProjectPerPM');

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
        $data = Cache::get('maximumProjectPerPM');

        if (! $data) {
            $projectManagerPosition = json_decode(getSettingByKey('position_as_project_manager'), true);

            $projectManagerPosition = collect($projectManagerPosition)->map(function ($item) {
                return getIdFromUid($item, new PositionBackup);
            })->toArray();

            $condition = implode("','", $projectManagerPosition);
            $condition = "('".$condition."')";

            $employees = $this->repo->list('id,name', 'position_id in '.$condition);

            $data = Cache::rememberForever('maximumProjectPerPM', function () use ($employees) {
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

        $whereEmployee = 'status != '.Status::Inactive->value.' and status != '.Status::Deleted->value;

        $data = $this->repo->list(
            select: 'id, uid as value, name as title',
            where: $whereEmployee,
            relation: $relation,
            whereHas: $whereHas
        );

        $employees = collect((object) $data)->map(function ($item) use ($date, $month, $maximumProjectPerPM) {
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
        $data = Excel::toArray(new EmployeeImport, $file);

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
            $positionData = Position::select('id')
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
                'religion' => $row[$religionKey] ? Religion::generateReligion($row[$religionKey]) : Religion::Islam->value,
                'martial_status_raw' => $row[$martialKey],
                'martial_status' => $row[$martialKey] ? MartialStatus::generateMartial($row[$martialKey]) : null,
                'address' => $row[$addressKey] ?? 'belum diisi',
                'postal_code' => $row[$postalCodeKey] ?? 0,
                'current_address' => $row[$currentAddressKey],
                'blood_type' => $row[$bloodTypeKey],
                'date_of_birth' => $row[$dobKey] ? Date::excelToDateTimeObject((int) $row[$dobKey])->format('Y-m-d') : '1970-01-01',
                'place_of_birth' => $row[$pobKey] ?? 'belum diisi',
                'dependant' => '',
                'gender_raw' => $row[$genderKey],
                'gender' => $row[$genderKey] ? Gender::generateGender($row[$genderKey]) : null,
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
                'education' => $row[$educationKey] ? Education::generateEducation($row[$educationKey]) : null,
                'education_name' => $row[$schoolNameKey],
                'education_major' => $row[$majorKey],
                'education_year' => $row[$graduationYearKey],
                'position_raw' => $row[$jobNameKey],
                'position_id' => $positionData->id ?? 0,
                'boss_id' => $row[$bossIdKey],
                'level_staff_raw' => $row[$levelKey],
                'level_staff' => $row[$levelKey] ? LevelStaff::generateLevel($row[$levelKey]) : null,
                'status_raw' => $row[$statusKey],
                'status' => $row[$statusKey] ? Status::generateStatus($row[$statusKey]) : null,
                'placement' => $row[$placementKey],
                'join_date' => $row[$joinDateKey] ? Date::excelToDateTimeObject((int) $row[$joinDateKey])->format('Y-m-d') : null,
                'start_review_probation_date' => $row[$startReviewProbationKey] ? Date::excelToDateTimeObject((int) $row[$startReviewProbationKey])->format('Y-m-d') : null,
                'probation_status_raw' => $row[$probationStatusKey],
                'probation_status' => $row[$probationStatusKey] ? ProbationStatus::generateStatus($row[$probationStatusKey]) : null,
                'end_probation_date' => $row[$endProbationKey] ? Date::excelToDateTimeObject((int) $row[$endProbationKey])->format('Y-m-d') : null,
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
        DB::beginTransaction();
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

            DB::commit();

            return generalResponse(
                __('global.successImportData'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
            return Storage::download('static-file/employee.xlsx');
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
            $employeeId = getIdFromUid($employeeUid, new Employee);

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
        $data = $this->employeeEmergencyRepo->list('*', 'employee_id = '.getIdFromUid($employeeUid, new Employee));

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
            $employeeId = getIdFromUid($employeeUid, new Employee);

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
            $payload['position_id'] = getIdFromUid($payload['position_id'], new PositionBackup);
            if (
                (isset($payload['boss_id'])) &&
                ($payload['boss_id'])
            ) {
                $payload['boss_id'] = getIdFromUid($payload['boss_id'], new Employee);
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
                where: 'resign_date = CURDATE()',
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

        // remove the employee's login access entirely (soft delete the user record)
        $this->userRepo->bulkDelete([$employeeId], 'employee_id');

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
     * Main logic for employee resignation.
     *
     * Flow:
     * 1. Guard: block if the employee still has on-progress production tasks or already has a resignation record.
     * 2. Persist the resignation record (maps the Greatday reason code + remark).
     * 3. Optionally push a TERMINATION transaction to Greatday. A Greatday failure never blocks the ERP
     *    resignation — we proceed and flag the developer via Slack instead.
     * 4. If the resign date is today or in the past, deactivate the employee immediately; otherwise the
     *    scheduled command (hrd:check-resign-employee) picks it up on the resign date.
     * 5. Notify the employee, their boss (if any) and the HR team via email + realtime in-app notification.
     */
    public function mainResignLogic(
        ResignData $data,
        string $employeeUid,
        bool $useTransaction = false,
        bool $notifyAfterCommit = false
    ): array {
        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            $employee = $this->repo->show(
                uid: $employeeUid,
                select: 'id,uid,name,email,position_id,status,employee_id'
            );

            if (! $employee) {
                return errorResponse(message: __('notification.employeeNotFound'));
            }

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

            // Check if current employee already has a resignation record
            $check = $this->employeeResignRepo->show(
                uid: '',
                select: 'id',
                where: "employee_id = {$employee->id}"
            );

            if ($check) {
                return errorResponse(message: __('notification.employeeAlreadyHasResignationRecord'));
            }

            $resignDate = date('Y-m-d', strtotime($data->resign_date));

            $this->employeeResignRepo->store(data: [
                'created_by' => auth()->id(),
                'employee_id' => $employee->id,
                'reason' => $data->remark,
                'resign_date' => $resignDate,
                'greatday_resign_reason' => $data->resign_reason_code,
                'current_position_id' => $employee->position_id,
                'current_employee_status' => $employee->status,
            ]);

            // push the termination to the Greatday third party (non-blocking)
            if ($data->sync_greatday) {
                $this->syncResignToGreatday($employee, $resignDate);
            }

            // if today date is the same with resign_date (or earlier), change employee status,
            // otherwise the cron job will handle it on the resign date
            $diff = Carbon::now()->diffInDays(Carbon::parse($data->resign_date), false);

            $message = __('notification.resignHasBeenOnScheduled');

            if ($diff <= 0) {
                $this->turnOffEmployee(
                    resignDate: $resignDate,
                    employeeUid: $employee->uid,
                    employeeId: $employee->id,
                    afterCommit: $notifyAfterCommit
                );

                $message = __('notification.successResign', ['name' => $employee->name]);
            }

            // notify the employee, their boss and the HR team (email + in-app)
            $notification = SendResignationNotificationJob::dispatch($employee->id, $resignDate, $data->remark);
            if ($notifyAfterCommit) {
                $notification->afterCommit();
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
     * Push a TERMINATION transaction to Greatday. Any failure is swallowed and reported to the
     * developer via Slack so it never blocks the ERP resignation.
     */
    protected function syncResignToGreatday(object $employee, string $resignDate): void
    {
        try {
            if (empty($employee->employee_id)) {
                throw new \RuntimeException('Employee has no Greatday employee number (empNo).');
            }

            $response = $this->greatdayService->terminateEmployee(
                empNo: $employee->employee_id,
                effectiveDate: $resignDate,
            );

            if ($response->failed()) {
                throw new \RuntimeException('Greatday responded with status '.$response->status().': '.$response->body());
            }

            $this->notifyDeveloperResignResult($employee, true);
        } catch (\Throwable $th) {
            logging('Greatday resign sync failed', [$th->getMessage()]);

            $this->notifyDeveloperResignResult($employee, false, $th->getMessage());
        }
    }

    /**
     * Flag the developer via Slack about the outcome of the Greatday resignation push.
     */
    protected function notifyDeveloperResignResult(object $employee, bool $success, ?string $errorMessage = null): void
    {
        $developer = User::where('email', config('app.developer_email'))->first();

        if (! $developer) {
            return;
        }

        $developer->notify(new ResignationNotification(
            success: $success,
            employeeName: $employee->name,
            employeeEmail: $employee->email,
            errorMessage: $errorMessage,
        ));
    }

    /**
     * Employee is resign
     *
     * @return array<string, mixed>
     */
    public function resign(ResignData $data, string $employeeUid): array
    {
        DB::beginTransaction();
        try {
            $resign = $this->mainResignLogic(
                data: $data,
                employeeUid: $employeeUid,
                notifyAfterCommit: true
            ); // will return message

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

    protected function isGreatdayResponseSuccess(Response $response): bool
    {
        return $response->status() < 300 && isset($response->json()['data']);
    }

    protected function getGreatdayRepositoryData(string $type)
    {
        $type = ucfirst($type);

        return "greatday{$type}Repo";
    }

    public function greatdayListData(string $select, string $type, string $searchAbleColumn = 'name', string $secondSearchAbleColumn = '')
    {
        try {
            $repo = $this->getGreatdayRepositoryData($type);
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $sortBy = request('sortBy') ?? [];

            $search = request('search');

            $where = '';

            if (! empty($search)) {
                $where = "{$searchAbleColumn} like '%{$search}%'";

                if (! empty($secondSearchAbleColumn)) {
                    $where .= " OR {$secondSearchAbleColumn} like '%{$search}%'";
                }
            }

            $data = $this->$repo->pagination(
                select: $select,
                where: $where,
                relation: [],
                itemsPerPage: $itemsPerPage,
                page: $page,
                orderBy: $sortBy
            );

            $totalData = $this->$repo->list('id', $where)->count();

            return [
                'data' => $data,
                'totalData' => $totalData,
            ];
        } catch (\Throwable $th) {
            return [
                'data' => collect([]),
                'totalData' => 0,
            ];
        }
    }

    /**
     * Get list cost centers
     */
    public function listCostCenter(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name_en,name_id,updated_at',
                type: 'costCenter',
                searchAbleColumn: 'name_en',
                secondSearchAbleColumn: 'name_id'
            );
            $costCenters = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            $currentLocale = App::currentLocale();
            foreach ($costCenters as $costCenter) {
                $name = "name_{$currentLocale}";
                $output[] = [
                    'code' => $costCenter['code'],
                    'display_name' => $costCenter[$name],
                    'updated_at' => date('d F Y, H:i', strtotime($costCenter['updated_at'])),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get timezones
     */
    public function listTimezones(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'timezone_id,name,updated_at,gmt_ref_hour,gmt_ref_minute,gmt_plus_min',
                type: 'timezone',
                searchAbleColumn: 'name',
            );
            $timezones = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($timezones as $timezone) {
                $output[] = [
                    'name' => "(GMT{$timezone->gmt_plus_min}{$timezone->gmt_ref_hour}:{$timezone->gmt_ref_minute}) {$timezone->name}",
                    'updated_at' => date('d F Y, H:i', strtotime($timezone->updated_at)),
                    'timezone_id' => $timezone->timezone_id,
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get timezones
     */
    public function listReligions(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,updated_at',
                type: 'religion',
                searchAbleColumn: 'name',
            );
            $religions = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($religions as $religion) {
                $output[] = [
                    'religion_code' => $religion->code,
                    'name' => $religion->name,
                    'updated_at' => date('d F Y, H:i', strtotime($religion->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get job grades
     */
    public function listJobGrades(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,updated_at',
                type: 'jobGrade',
                searchAbleColumn: 'name',
            );
            $religions = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($religions as $religion) {
                $output[] = [
                    'job_grade_code' => $religion->code,
                    'name' => $religion->name,
                    'updated_at' => date('d F Y, H:i', strtotime($religion->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get Employment status
     */
    public function listEmploymentStatuses(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,updated_at,need_employment_date',
                type: 'employmentStatus',
                searchAbleColumn: 'name',
            );
            $employmentStatuses = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($employmentStatuses as $status) {
                $output[] = [
                    'employment_status_code' => $status->code,
                    'name' => $status->name,
                    'need_employment_date' => $status->need_employment_date,
                    'updated_at' => date('d F Y, H:i', strtotime($status->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get Work Location
     */
    public function listWorkLocations(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,address,max_radius',
                type: 'workLocation',
                searchAbleColumn: 'name',
            );
            $workLocations = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($workLocations as $location) {
                $output[] = [
                    'work_location_code' => $location->code,
                    'name' => $location->name,
                    'address' => $location->address,
                    'max_radius' => $location->max_radius,
                    'updated_at' => date('d F Y, H:i', strtotime($location->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get Shift Pattern
     */
    public function listShiftPatterns(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,total_working_hour_per_day,total_day_off_per_week,note,updated_at',
                type: 'shiftPattern',
                searchAbleColumn: 'name',
            );
            $shiftPatterns = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($shiftPatterns as $pattern) {
                $output[] = [
                    'shift_pattern_code' => $pattern->code,
                    'name' => $pattern->name,
                    'total_working_hour_per_day' => $pattern->total_working_hour_per_day,
                    'total_day_off_per_week' => $pattern->total_day_off_per_week,
                    'note' => $pattern->note,
                    'updated_at' => date('d F Y, H:i', strtotime($pattern->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get Job Status
     */
    public function listJobStatuses(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,updated_at',
                type: 'jobStatus',
                searchAbleColumn: 'name',
            );
            $jobStatuses = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($jobStatuses as $status) {
                $output[] = [
                    'job_status_code' => $status->code,
                    'name' => $status->name,
                    'updated_at' => date('d F Y, H:i', strtotime($status->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get Nationality
     */
    public function listNationalities(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,updated_at',
                type: 'nationality',
                searchAbleColumn: 'name',
            );
            $nationalities = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($nationalities as $nationality) {
                $output[] = [
                    'nationality_code' => $nationality->code,
                    'name' => $nationality->name,
                    'updated_at' => date('d F Y, H:i', strtotime($nationality->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get Companies
     */
    public function listCompanies(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,updated_at,company_id',
                type: 'company',
                searchAbleColumn: 'name',
            );
            $companies = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($companies as $company) {
                $output[] = [
                    'company_code' => $company->code,
                    'name' => $company->name,
                    'company_id' => $company->company_id,
                    'updated_at' => date('d F Y, H:i', strtotime($company->updated_at)),
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get greatday timezone and store in the database, this function is used to sync timezone data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayTimezones(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/timezone', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $costCenters = $response->json()['data'];

                $payload = [];
                foreach ($costCenters as $costCenter) {
                    $payload[] = [
                        'timezone_id' => $costCenter['gmtId'],
                        'name' => $costCenter['gmtcountry'],
                        'gmt_ref_hour' => $costCenter['gmtrefhour'],
                        'gmt_ref_minute' => $costCenter['gmtrefminute'],
                        'gmt_plus_min' => $costCenter['gmtplusmin'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }

                $this->greatdayTimezoneRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['timezone_id'],
                    updateColumns: ['name', 'gmt_ref_hour', 'gmt_ref_minute', 'gmt_plus_min']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday cost center and store in the database, this function is used to sync cost center data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayCostCenter(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/costcenter', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $costCenters = $response->json()['data'];

                $payload = [];
                foreach ($costCenters as $costCenter) {
                    if ($costCenter['status'] == 1 && $costCenter['depth'] == 1) {
                        $payload[] = [
                            'code' => $costCenter['costcenterCode'],
                            'name_en' => $costCenter['costcenterNameEn'],
                            'name_id' => $costCenter['costcenterNameId'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }

                $this->greatdayCostCenterRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name_en', 'name_id']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday religion and store in the database, this function is used to sync religion data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayReligion(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/religion', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $religions = $response->json()['data'];

                $payload = [];
                foreach ($religions as $religion) {
                    $payload[] = [
                        'code' => $religion['religionCode'],
                        'name' => $religion['religionNameEn'],
                    ];
                }

                $this->greatdayReligionRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday job grade and store in the database, this function is used to sync job grade data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayJobGrade(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/jobgrade', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $jobGrades = $response->json()['data'];

                $payload = [];
                foreach ($jobGrades as $jobGrade) {
                    $payload[] = [
                        'code' => $jobGrade['gradeCode'],
                        'name' => $jobGrade['gradeName'],
                    ];
                }

                $this->greatdayJobGradeRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday employment status and store in the database, this function is used to sync employment status data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayEmploymentStatus(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/employmentstatus', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $employmentStatuses = $response->json()['data'];

                $payload = [];
                foreach ($employmentStatuses as $employmentStatus) {
                    $payload[] = [
                        'code' => $employmentStatus['employmentstatusCode'],
                        'name' => $employmentStatus['employmentstatusNameEn'],
                        'need_employment_date' => $employmentStatus['reqEmploymentdate'],
                    ];
                }

                $this->greatdayEmploymentStatusRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday work location and store in the database, this function is used to sync work location data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayWorkLocation(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/worklocation', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $workLocations = $response->json()['data'];

                $payload = [];
                foreach ($workLocations as $workLocation) {
                    $payload[] = [
                        'code' => $workLocation['worklocationCode'],
                        'name' => $workLocation['worklocationName'],
                        'address' => $workLocation['worklocationAddress'] ?? null,
                        'max_radius' => (float) $workLocation['maxRadius'],
                    ];
                }

                $this->greatdayWorkLocationRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name', 'address', 'max_radius']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday shift pattern and store in the database, this function is used to sync shift pattern data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayShiftPattern(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/attendances/shift-pattern', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $shiftPatterns = $response->json()['data'];

                $payload = [];
                foreach ($shiftPatterns as $shift) {
                    $groups = $shift['ttarshiftgroupdailys'];
                    $totalOff = collect($groups)->where('shiftdailycode', '=', 'OFF')->count();
                    $activeDay = collect($groups)->where('shiftdailycode', '!=', 'OFF')->first();
                    $remark = null;
                    if ($activeDay && isset($activeDay['ttamshiftdaily']) && (isset($activeDay['ttamshiftdaily']['remark']))) {
                        $remark = $activeDay['ttamshiftdaily']['remark'];
                    }
                    $payload[] = [
                        'code' => $shift['shiftgroupcode'],
                        'name' => $shift['shiftgroupname'],
                        'total_working_hour_per_day' => 0,
                        'total_day_off_per_week' => $totalOff,
                        'note' => $remark,
                    ];
                }

                $this->greatdayShiftPatternRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name', 'total_working_hour_per_day', 'total_day_off_per_week', 'note']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday job status and store in the database, this function is used to sync job status data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayJobStatus(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/jobstatus', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $jobStatuses = $response->json()['data'];

                $payload = [];
                foreach ($jobStatuses as $jobStatus) {
                    $payload[] = [
                        'code' => $jobStatus['jobstatuscode'],
                        'name' => $jobStatus['jobstatusnameEn'],
                    ];
                }

                $this->greatdayJobStatusRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday nationality and store in the database, this function is used to sync nationality data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayNationality(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/nationality', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $jobStatuses = $response->json()['data'];

                $payload = [];
                foreach ($jobStatuses as $jobStatus) {
                    $payload[] = [
                        'code' => $jobStatus['nationalityCode'],
                        'name' => $jobStatus['nationalityNameEn'],
                    ];
                }

                $this->greatdayNationalityRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday companies and store in the database, this function is used to sync companies data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayCompanies(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/company', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $jobStatuses = $response->json()['data'];

                $payload = [];
                foreach ($jobStatuses as $jobStatus) {
                    $payload[] = [
                        'code' => $jobStatus['companyCode'],
                        'name' => $jobStatus['companyName'],
                        'company_id' => $jobStatus['companyId'],
                        'nickname' => $jobStatus['nickName'],
                        'is_base_office' => $jobStatus['isbase'],
                        'address' => $jobStatus['companyAddress'],
                        'address2' => $jobStatus['companyAddress2'],
                    ];
                }

                $this->greatdayCompanyRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code', 'company_id'],
                    updateColumns: ['name', 'nickname', 'is_base_office', 'address', 'address2']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Resend email verification to unverified employee
     *
     * @param  array<string, string>  $payload  -> will be ['email', 'note']
     * @return array
     */
    public function resendVerification(array $payload, string $employeeId)
    {
        try {
            $requestEmail = $payload['email'];
            $employee = $this->repo->show(
                uid: '',
                select: 'id,email,user_id',
                where: "employee_id = '{$employeeId}' and email = '{$requestEmail}'",
                relation: [
                    'user:id,email,email_verified_at,employee_id',
                ]
            );

            if (! $employee) {
                return errorResponse(__('notification.employeeNotFound'));
            }

            if (! $employee->user) {
                return errorResponse(__('notification.userNotFound'));
            }

            if ($employee->user->email_verified_at) {
                return errorResponse(__('notification.emailAlreadyVerified'));
            }

            // Update password
            $newPassword = generateRandomPassword(10);
            $this->userRepo->update(
                data: [
                    'password' => Hash::make($newPassword),
                ],
                key: 'id',
                value: $employee->user_id
            );

            $user = $this->userRepo->detail(
                id: $employee->user_id,
            );

            SendEmailActivationJob::dispatch($user, $newPassword)->afterCommit();

            return generalResponse(
                message: __('notification.successSendEmailActivation'),
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get timezones
     */
    public function listResignTypes(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,id',
                type: 'resignType',
                searchAbleColumn: 'name',
            );
            $resignTypes = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($resignTypes as $resignType) {
                $output[] = [
                    'name' => $resignType->name,
                    'code' => $resignType->code,
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get resign reasons
     */
    public function listResignReasons(): array
    {
        try {
            $data = $this->greatdayListData(
                select: 'code,name,id,resign_type',
                type: 'resignReason',
                searchAbleColumn: 'name',
            );
            $resignReasons = $data['data'];
            $totalData = $data['totalData'];

            $output = [];
            foreach ($resignReasons as $resignReason) {
                $output[] = [
                    'name' => $resignReason->name,
                    'code' => $resignReason->code,
                    'resign_type' => $resignReason->resign_type,
                ];
            }

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get greatday resign types and store in the database, this function is used to sync resign type data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayResignType(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/resigntype', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $resignTypes = $response->json()['data'];

                $payload = [];
                foreach ($resignTypes as $resignType) {
                    $payload[] = [
                        'code' => $resignType['code'],
                        'name' => $resignType['nameEn'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }

                $this->greatdayResignTypeRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get greatday resign reasons and store in the database, this function is used to sync resign reasons data from greatday to our database, so we can use it later when we want to integrate with greatday for attendance feature
     */
    public function getGreatdayResignReason(): array
    {
        DB::beginTransaction();
        try {
            $accessToken = $this->greatdayService->login();

            $response = Http::withToken($accessToken)->post($this->greatdayService->getBaseUrl().'/company/resignreason', [
                'page' => 1,
                'limit' => 100,
            ]);

            if ($this->isGreatdayResponseSuccess($response)) {
                $resignReasons = $response->json()['data'];

                $payload = [];
                foreach ($resignReasons as $resignReason) {
                    $payload[] = [
                        'code' => $resignReason['reasonCode'],
                        'name' => $resignReason['reasonDescEn'],
                        'resign_type' => $resignReason['resignation'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }

                $this->greatdayResignReasonRepo->upsert(
                    payload: $payload,
                    uniqueBy: ['code', 'resign_type'],
                    updateColumns: ['name']
                );
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Reconcile the out_of_sync_employees staging table against Greatday.
     *
     * This is the API counterpart of the app:out-of-sync-employee console command.
     * Two directions are handled:
     *  1. ADD    — an employee that exists in Greatday but has no matching greatday_emp_id
     *              (nor email) on a non-terminal local employee is stored as out of sync.
     *  2. PRUNE  — an existing out_of_sync_employees row whose Greatday employee is no longer
     *              returned by Greatday at all is removed: it is neither a real ERP employee
     *              nor present in Greatday anymore, so it is no longer "out of sync".
     *
     * Pruning only runs once every Greatday page has been collected (a partial fetch would
     * make present employees look missing) and never runs on an empty Greatday set, so a
     * failed/empty fetch can never wipe the whole staging table.
     *
     * @return array<string, mixed>
     */
    public function getOutOfSyncEmployees(): array
    {
        try {
            $terminalStatus = EmploymentStatus::select('id')
                ->where('is_terminal', true)
                ->first();

            if (! $terminalStatus) {
                return errorResponse('Terminal status not found');
            }

            $limit = 100;
            $page = 1;
            $totalOutOfSync = 0;
            $greatdayEmpIds = [];

            while (true) {
                $greatdayEmployees = $this->fetchGreatdayEmployees($limit, $page);

                if (empty($greatdayEmployees)) {
                    break;
                }

                $greatdayEmpIds = array_merge(
                    $greatdayEmpIds,
                    collect($greatdayEmployees)->pluck('empId')->all()
                );

                $totalOutOfSync += $this->storeOutOfSyncEmployees($greatdayEmployees, (int) $terminalStatus->id);

                $page++;
            }

            $totalRemoved = $this->pruneMissingOutOfSyncEmployees($greatdayEmpIds);

            return generalResponse(
                message: 'Success',
                data: [
                    'total_out_of_sync' => $totalOutOfSync,
                    'total_removed' => $totalRemoved,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Remove out_of_sync_employees rows whose Greatday employee is no longer present in Greatday.
     *
     * Guarded: an empty Greatday set (e.g. a failed or empty fetch) prunes nothing, so the whole
     * staging table can never be wiped by a transient Greatday outage.
     *
     * @param  array<int, mixed>  $greatdayEmpIds  Every empId returned by Greatday this run
     * @return int Number of staging rows removed
     */
    protected function pruneMissingOutOfSyncEmployees(array $greatdayEmpIds): int
    {
        if (empty($greatdayEmpIds)) {
            return 0;
        }

        return OutOfSyncEmployee::whereNotIn('greatday_employee_id', $greatdayEmpIds)->delete();
    }

    /**
     * Bulk-register out-of-sync Greatday employees into the ERP.
     *
     * For each selected employee this creates the ERP Employee record, creates the linked User
     * account (with role + activation email via UserService::mainServiceStoreUser), and flips the
     * source out_of_sync_employees row to Synced.
     *
     * The whole batch is all-or-nothing: any failure (e.g. an unresolved position code) rolls back
     * every employee. An employee that already exists in the ERP (by email, employee_id or Greatday
     * id) is skipped — not recreated — but its out_of_sync row is still marked Synced.
     *
     * @return array<string, mixed>
     */
    public function syncEmployeesFromGreatday(BulkSyncEmployeeData $data): array
    {
        DB::beginTransaction();
        try {
            $synced = [];
            $skipped = [];

            foreach ($data->employees as $item) {
                $existing = Employee::where('email', $item->email)
                    ->orWhere('employee_id', $item->employee_no)
                    ->orWhere('greatday_emp_id', $item->greatday_employee_id)
                    ->first();

                if ($existing) {
                    $this->markOutOfSyncAsSynced($item->out_of_sync_id);
                    $skipped[] = $item->email;

                    continue;
                }

                $position = PositionBackup::where('uid', $item->position)->first();

                if (! $position) {
                    throw new EmployeeException(__('notification.positionNotFound', [
                        'position' => $item->position,
                        'email' => $item->email,
                    ]));
                }

                $employmentStatus = EmploymentStatus::where('code', $item->employment_status)->first();
                $boss = Employee::where('uid', $item->supervisor)->first();

                $employee = $this->repo->store([
                    'name' => $this->buildFullName($item->first_name, $item->middle_name, $item->last_name),
                    'nickname' => $item->nickname,
                    'email' => $item->email,
                    'employee_id' => $item->employee_no,
                    'id_number' => $item->id_number,
                    'phone' => $this->normalizePhone($item->mobile_phone),
                    'address' => $item->address,
                    'date_of_birth' => Carbon::parse($item->birth_day)->format('Y-m-d'),
                    'place_of_birth' => $item->birth_place,
                    'gender' => $this->mapGender($item->gender),
                    'religion' => $this->resolveReligion($item->religion),
                    'martial_status' => $this->mapMaritalStatus($item->marital_status),
                    'position_id' => $position->id,
                    'employment_status_id' => $employmentStatus?->id,
                    'boss_id' => $boss?->id,
                    'status' => Status::Permanent->value,
                    'salary_type' => SalaryType::Monthly->value,
                    'join_date' => Carbon::parse($item->join_date)->format('Y-m-d'),
                    'end_date' => ! empty($item->end_date) ? Carbon::parse($item->end_date)->format('Y-m-d') : null,
                    'bank_detail' => [
                        [
                            'bank_name' => $item->bank_name,
                            'account_number' => $item->bank_account_number,
                            'account_holder_name' => $item->bank_account_holder_name,
                            'is_active' => true,
                        ],
                    ],
                    'avatar_color' => $this->generalService->generateRandomColor($item->email),

                    // greatday raw values
                    'greatday_emp_id' => $item->greatday_employee_id,
                    'greatday_nationality' => $item->nationality,
                    'greatday_job_grade' => $item->job_grade,
                    'greatday_cost_center' => $item->cost_center,
                    'greatday_employment_status' => $item->employment_status,
                    'greatday_work_location' => $item->work_location,
                    'greatday_religion' => $item->religion,
                    'greatday_timezone' => $item->timezone_id,
                    'greatday_shift_pattern' => $item->shift_pattern,
                    'greatday_job_status' => $item->job_status,
                    'greatday_company' => $item->company_id,
                    'greatday_marital_status' => $item->marital_status,
                ]);

                // create the linked user account, assign the role and send the activation email
                $this->userService->mainServiceStoreUser([
                    'email' => $item->email,
                    'password' => generateRandomPassword(10),
                    'is_external_user' => 0,
                    'employee_id' => $employee->uid,
                    'role_id' => $item->role,
                ]);

                $this->markOutOfSyncAsSynced($item->out_of_sync_id);

                $synced[] = $item->email;
            }

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: [
                    'total_synced' => count($synced),
                    'total_skipped' => count($skipped),
                    'synced' => $synced,
                    'skipped' => $skipped,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Flag an out_of_sync_employees row as resolved once its employee exists in the ERP.
     */
    protected function markOutOfSyncAsSynced(int $outOfSyncId): void
    {
        OutOfSyncEmployee::where('id', $outOfSyncId)->update([
            'status' => OutOfSyncStatus::Synced,
        ]);
    }

    /**
     * Join the Greatday name parts into a single ERP employee name.
     */
    protected function buildFullName(string $firstName, ?string $middleName, ?string $lastName): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$firstName, $middleName, $lastName]))));
    }

    /**
     * Map the Greatday gender code to an ERP gender value (1 = Male, 0 = Female).
     */
    protected function mapGender(string $code): ?string
    {
        return match ($code) {
            '1' => Gender::Male->value,
            '0' => Gender::Female->value,
            default => null,
        };
    }

    /**
     * Map the Greatday marital code to an ERP marital status (0 = Single, 1 = Married,
     * 2 = Widow, 3 = Widower). The ERP only models single/married, so widow/widower —
     * who are not currently married — are recorded as single.
     */
    protected function mapMaritalStatus(string $code): string
    {
        return $code === '1' ? MartialStatus::Married->value : MartialStatus::Single->value;
    }

    /**
     * Map a religion name (any case, e.g. "ISLAM") to an ERP religion value, defaulting to
     * Islam when unknown. Religion::generateReligion() returns the string '-' (not null) for
     * unrecognised values.
     */
    protected function resolveReligion(string $religion): string
    {
        $resolved = Religion::generateReligion(ucfirst(strtolower(trim($religion))));

        return $resolved instanceof Religion ? $resolved->value : Religion::Islam->value;
    }

    /**
     * Preview field-level changes for employees that exist in both Greatday and the ERP.
     *
     * Read-only: matches by greatday_emp_id and returns, per employee, only the fields whose
     * incoming Greatday value differs from the ERP value (as display strings). Mutates nothing.
     *
     * @return array<string, mixed>
     */
    public function previewEmployeeChanges(): array
    {
        try {
            $greatdayEmployees = $this->fetchAllGreatdayEmployees();
            $maps = $this->buildChangeSyncMaps();
            $specs = $this->changeSyncFieldSpecs($maps);

            $erpEmployees = Employee::whereNotNull('greatday_emp_id')->get()->keyBy('greatday_emp_id');

            $changed = [];

            foreach ($greatdayEmployees as $greatday) {
                $empId = $greatday['empId'] ?? null;
                $employee = $empId ? $erpEmployees->get($empId) : null;

                if (! $employee) {
                    continue;
                }

                $changes = $this->collectEmployeeChanges($employee, $greatday, $specs);

                if (! empty($changes)) {
                    // A division or supervisor move is blocked while the employee still holds active tasks.
                    $guardedChange = collect($changes)->contains(
                        fn ($change) => in_array($change['field'], self::TASK_GUARDED_CHANGE_FIELDS, true)
                    );
                    $blocked = $guardedChange && $this->hasActiveProductionTasks($employee);

                    $changed[] = [
                        'employee_uid' => $employee->uid,
                        'greatday_emp_id' => $empId,
                        'name' => $employee->name,
                        'blocked' => $blocked,
                        'blocked_reason' => $blocked
                            ? __('notification.employeeActiveTaskDivisionBlock', ['name' => $employee->name])
                            : null,
                        'changes' => $changes,
                    ];
                }
            }

            return generalResponse(
                message: 'Success',
                data: ['changed' => $changed]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Apply the confirmed Greatday → ERP employee changes.
     *
     * All-or-nothing: re-fetches Greatday as the source of truth and, for each confirmed empId,
     * writes every changed field. Any failure rolls back the whole batch. The linked User email
     * is intentionally NOT touched here (email is out of this feature's scope).
     *
     * @return array<string, mixed>
     */
    public function applyEmployeeChanges(EmployeeChangeSyncData $data): array
    {
        try {
            // Fetch external data + build lookups BEFORE opening the transaction, so the DB
            // transaction is never held open during the (slow) Greatday HTTP call.
            $greatdayEmployees = collect($this->fetchAllGreatdayEmployees())->keyBy('empId');
            $maps = $this->buildChangeSyncMaps();
            $specs = $this->changeSyncFieldSpecs($maps);

            // Preload every confirmed employee in a single query (no per-row lookup).
            $employees = Employee::whereIn('greatday_emp_id', $data->employees)
                ->get()
                ->keyBy('greatday_emp_id');

            $updated = DB::transaction(function () use ($data, $greatdayEmployees, $employees, $specs) {
                $done = [];

                foreach ($data->employees as $empId) {
                    $greatday = $greatdayEmployees->get($empId);
                    $employee = $employees->get($empId);

                    if (! $greatday || ! $employee) {
                        throw new EmployeeException(__('notification.greatdayEmployeeNotFound', ['id' => $empId]));
                    }

                    $update = [];
                    $guardedChange = false;
                    foreach ($specs as $spec) {
                        if (($spec['changed'])($employee, $greatday)) {
                            if (in_array($spec['field'], self::TASK_GUARDED_CHANGE_FIELDS, true)) {
                                $guardedChange = true;
                            }
                            $update = array_merge($update, ($spec['columns'])($greatday));
                        }
                    }

                    // guard: a division / supervisor move cannot be applied while the employee has active tasks
                    if ($guardedChange && $this->hasActiveProductionTasks($employee)) {
                        throw new EmployeeException(__('notification.employeeActiveTaskDivisionBlock', ['name' => $employee->name]));
                    }

                    if (! empty($update)) {
                        $this->repo->update($update, $employee->uid);
                    }

                    $done[] = $empId;
                }

                return $done;
            });

            return generalResponse(
                message: 'Success',
                data: [
                    'total_updated' => count($updated),
                    'updated' => $updated,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $specs
     * @return array<int, array<string, mixed>>
     */
    protected function collectEmployeeChanges(Employee $employee, array $greatday, array $specs): array
    {
        $changes = [];

        foreach ($specs as $spec) {
            if (($spec['changed'])($employee, $greatday)) {
                $changes[] = [
                    'field' => $spec['field'],
                    'label' => $spec['label'],
                    'sensitive' => $spec['sensitive'] ?? false,
                    'from' => ($spec['from'])($employee),
                    'to' => ($spec['to'])($greatday),
                ];
            }
        }

        return $changes;
    }

    /**
     * Fetch every Greatday employee across all pages (stops on the first empty page).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException when any page fails to load
     */
    protected function fetchAllGreatdayEmployees(): array
    {
        $page = 1;
        $all = [];

        while (true) {
            $response = $this->greatdayService->authedPost('/employees', [
                'page' => $page,
                'limit' => 100,
            ]);

            if ($response->failed() || ! isset($response->json()['data'])) {
                throw new \RuntimeException("Greatday employee fetch failed on page {$page}.");
            }

            $data = $response->json()['data'];

            if (empty($data)) {
                break;
            }

            $all = array_merge($all, $data);
            $page++;
        }

        return $all;
    }

    /**
     * Preload the lookup maps used to resolve/display relational fields during a change sync.
     *
     * @return array<string, array<int|string, mixed>>
     */
    protected function buildChangeSyncMaps(): array
    {
        $positions = PositionBackup::with('division:id,name')->select('id', 'name', 'greatday_code', 'division_id')->get();
        $employmentStatuses = EmploymentStatus::select('id', 'name', 'code')->get();
        $employees = Employee::select('id', 'name', 'greatday_emp_id')->get();

        return [
            'positionsByCode' => $positions->whereNotNull('greatday_code')->keyBy('greatday_code')->map->id->toArray(),
            'positionsById' => $positions->keyBy('id')->map->name->toArray(),
            'divisionByPositionId' => $positions->keyBy('id')->map(fn ($p) => $p->division?->name)->toArray(),
            'employmentByCode' => $employmentStatuses->whereNotNull('code')->keyBy('code')->map->id->toArray(),
            'employmentById' => $employmentStatuses->keyBy('id')->map->name->toArray(),
            'employeesByGreatdayEmpId' => $employees->whereNotNull('greatday_emp_id')->keyBy('greatday_emp_id')->map->id->toArray(),
            'employeesById' => $employees->keyBy('id')->map->name->toArray(),

            // Greatday master data (code -> human readable name) for display
            'jobGradeByCode' => GreatdayJobGrade::pluck('name', 'code')->toArray(),
            'costCenterByCode' => GreatdayCostCenter::pluck('name_en', 'code')->toArray(),
            'workLocationByCode' => GreatdayWorkLocation::pluck('name', 'code')->toArray(),
            'jobStatusByCode' => GreatdayJobStatus::pluck('name', 'code')->toArray(),
            'companyById' => GreatdayCompany::pluck('name', 'company_id')->toArray(),
        ];
    }

    /**
     * The field specs that drive both the change diff and the update payload.
     *
     * Each spec: changed(emp, gd):bool | from(emp):string | to(gd):string | columns(gd):array.
     * A field is only ever offered when Greatday provides a non-null incoming value, so a missing
     * Greatday value never clears an existing ERP value.
     *
     * @param  array<string, array<int|string, mixed>>  $maps
     * @return array<int, array<string, mixed>>
     */
    protected function changeSyncFieldSpecs(array $maps): array
    {
        $specs = [];

        // simple 1:1 fields: [field, label, erp column, greatday key, code->name map (optional)].
        // The comparison + stored value stays on the raw code; only display is resolved to a name.
        $simple = [
            ['nickname', 'Nickname', 'nickname', 'nickName', null],
            ['id_number', 'ID Number', 'id_number', 'identityNo', null],
            ['address', 'Address', 'address', 'address', null],
            ['place_of_birth', 'Place of Birth', 'place_of_birth', 'birthPlace', null],
            ['employee_id', 'Employee No', 'employee_id', 'empNo', null],
            ['job_grade', 'Job Grade', 'greatday_job_grade', 'gradeCode', $maps['jobGradeByCode']],
            ['cost_center', 'Cost Center', 'greatday_cost_center', 'costCode', $maps['costCenterByCode']],
            ['work_location', 'Work Location', 'greatday_work_location', 'worklocationCode', $maps['workLocationByCode']],
            ['job_status', 'Job Status', 'greatday_job_status', 'jobStatus', $maps['jobStatusByCode']],
            ['company', 'Company', 'greatday_company', 'companyId', $maps['companyById']],
        ];

        foreach ($simple as [$field, $label, $column, $key, $displayMap]) {
            $specs[] = [
                'field' => $field,
                'label' => $label,
                'sensitive' => false,
                'changed' => fn (Employee $e, array $gd): bool => $this->normalizeValue($gd[$key] ?? null) !== null
                    && $this->normalizeValue($gd[$key] ?? null) !== $this->normalizeValue($e->{$column}),
                'from' => fn (Employee $e): string => $this->displayCode($e->{$column}, $displayMap),
                'to' => fn (array $gd): string => $this->displayCode($gd[$key] ?? null, $displayMap),
                'columns' => fn (array $gd): array => [$column => $this->normalizeValue($gd[$key] ?? null)],
            ];
        }

        // date fields: [field, label, column, greatday key]
        $dates = [
            ['date_of_birth', 'Date of Birth', 'date_of_birth', 'birthDate'],
            ['join_date', 'Join Date', 'join_date', 'startDate'],
            ['end_date', 'End Date', 'end_date', 'endDate'],
        ];

        foreach ($dates as [$field, $label, $column, $key]) {
            $specs[] = [
                'field' => $field,
                'label' => $label,
                'sensitive' => false,
                'changed' => fn (Employee $e, array $gd): bool => $this->greatdayDate($gd[$key] ?? null) !== null
                    && $this->greatdayDate($gd[$key] ?? null) !== ($e->{$column} ? Carbon::parse($e->{$column})->format('Y-m-d') : null),
                'from' => fn (Employee $e): string => $e->{$column} ? Carbon::parse($e->{$column})->format('Y-m-d') : '-',
                'to' => fn (array $gd): string => $this->greatdayDate($gd[$key] ?? null) ?? '-',
                'columns' => fn (array $gd): array => [$column => $this->greatdayDate($gd[$key] ?? null)],
            ];
        }

        // phone (normalized to the ERP format: no leading 0, no country code)
        $specs[] = [
            'field' => 'phone',
            'label' => 'Phone',
            'sensitive' => true,
            'changed' => fn (Employee $e, array $gd): bool => ($to = $this->normalizePhone($gd['phone'] ?? null)) !== null
                && $to !== $this->normalizePhone($e->phone),
            'from' => fn (Employee $e): string => (string) ($this->normalizePhone($e->phone) ?? '-'),
            'to' => fn (array $gd): string => (string) ($this->normalizePhone($gd['phone'] ?? null) ?? '-'),
            'columns' => fn (array $gd): array => ['phone' => $this->normalizePhone($gd['phone'] ?? null)],
        ];

        // name (concatenated)
        $specs[] = [
            'field' => 'name',
            'label' => 'Name',
            'sensitive' => false,
            'changed' => fn (Employee $e, array $gd): bool => $this->normalizeValue($this->greatdayFullName($gd)) !== null
                && $this->normalizeValue($this->greatdayFullName($gd)) !== $this->normalizeValue($e->name),
            'from' => fn (Employee $e): string => (string) ($e->name ?? '-'),
            'to' => fn (array $gd): string => $this->greatdayFullName($gd) ?: '-',
            'columns' => fn (array $gd): array => ['name' => $this->greatdayFullName($gd)],
        ];

        // gender (coded -> enum, displayed as label)
        $specs[] = [
            'field' => 'gender',
            'label' => 'Gender',
            'sensitive' => false,
            'changed' => fn (Employee $e, array $gd): bool => ($to = $this->mapGender((string) ($gd['gender'] ?? ''))) !== null
                && $to !== $e->gender?->value,
            'from' => fn (Employee $e): string => $e->gender ? Gender::getGender($e->gender->value) : '-',
            'to' => fn (array $gd): string => Gender::getGender($this->mapGender((string) ($gd['gender'] ?? '')) ?? ''),
            'columns' => fn (array $gd): array => ['gender' => $this->mapGender((string) ($gd['gender'] ?? ''))],
        ];

        // position (posCode -> PositionBackup)
        $specs[] = [
            'field' => 'position',
            'label' => 'Position',
            'sensitive' => true,
            'changed' => fn (Employee $e, array $gd): bool => ($to = $maps['positionsByCode'][$gd['posCode'] ?? ''] ?? null) !== null
                && $to !== $e->position_id,
            'from' => fn (Employee $e): string => $maps['positionsById'][$e->position_id] ?? '-',
            'to' => fn (array $gd): string => (string) ($gd['posNameEn'] ?? '-'),
            'columns' => fn (array $gd): array => ['position_id' => $maps['positionsByCode'][$gd['posCode'] ?? ''] ?? null],
        ];

        // division (derived from the position's division; no own column — position_id carries it).
        // Display + guard only: a division move is blocked when the employee has active tasks.
        $specs[] = [
            'field' => 'division',
            'label' => 'Division',
            'sensitive' => true,
            'changed' => function (Employee $e, array $gd) use ($maps): bool {
                $to = $this->greatdayDivisionName($gd, $maps);

                return $to !== null && $to !== ($maps['divisionByPositionId'][$e->position_id] ?? null);
            },
            'from' => fn (Employee $e): string => $maps['divisionByPositionId'][$e->position_id] ?? '-',
            'to' => fn (array $gd): string => $this->greatdayDivisionName($gd, $maps) ?? '-',
            'columns' => fn (array $gd): array => [], // derived from position_id; nothing to write directly
        ];

        // boss (spvParent -> employee.greatday_emp_id); never cleared when unresolved
        $specs[] = [
            'field' => 'boss',
            'label' => 'Supervisor',
            'sensitive' => true,
            'changed' => function (Employee $e, array $gd) use ($maps): bool {
                $spv = $gd['spvParent'] ?? null;
                if (empty($spv)) {
                    return false;
                }
                $to = $maps['employeesByGreatdayEmpId'][$spv] ?? null;

                return $to !== null && $to !== $e->boss_id;
            },
            'from' => fn (Employee $e): string => $e->boss_id ? ($maps['employeesById'][$e->boss_id] ?? '-') : '-',
            'to' => function (array $gd) use ($maps): string {
                $bossId = $maps['employeesByGreatdayEmpId'][$gd['spvParent'] ?? ''] ?? null;

                return $bossId ? ($maps['employeesById'][$bossId] ?? '-') : (string) ($gd['spvParent'] ?? '-');
            },
            'columns' => fn (array $gd): array => ['boss_id' => $maps['employeesByGreatdayEmpId'][$gd['spvParent'] ?? ''] ?? null],
        ];

        // employment status (code -> EmploymentStatus, also stores the raw code)
        $specs[] = [
            'field' => 'employment_status',
            'label' => 'Employment Status',
            'sensitive' => false,
            'changed' => function (Employee $e, array $gd) use ($maps): bool {
                $code = $gd['employmentStatusCode'] ?? null;
                if (empty($code)) {
                    return false;
                }
                $to = $maps['employmentByCode'][$code] ?? null;

                return $to !== null && $to !== $e->employment_status_id;
            },
            'from' => fn (Employee $e): string => $e->employment_status_id ? ($maps['employmentById'][$e->employment_status_id] ?? '-') : '-',
            'to' => fn (array $gd): string => (string) ($gd['employmentStatus'] ?? $gd['employmentStatusCode'] ?? '-'),
            'columns' => fn (array $gd): array => [
                'employment_status_id' => $maps['employmentByCode'][$gd['employmentStatusCode'] ?? ''] ?? null,
                'greatday_employment_status' => $gd['employmentStatusCode'] ?? null,
            ],
        ];

        // bank account (bankCode/bankAccount/bankAccountName -> bank_detail json)
        $specs[] = [
            'field' => 'bank',
            'label' => 'Bank Account',
            'sensitive' => false,
            'changed' => function (Employee $e, array $gd): bool {
                $to = $this->greatdayBankAccount($gd);
                if ($to === null) {
                    return false;
                }

                return $this->bankSignature($this->firstBankDetail($e)) !== $this->bankSignature($to);
            },
            'from' => fn (Employee $e): string => $this->bankDisplay($this->firstBankDetail($e)),
            'to' => fn (array $gd): string => $this->bankDisplay($this->greatdayBankAccount($gd)),
            'columns' => fn (array $gd): array => ['bank_detail' => [$this->greatdayBankAccount($gd)]],
        ];

        return $specs;
    }

    protected function greatdayFullName(array $greatday): string
    {
        return $this->buildFullName(
            $greatday['firstName'] ?? '',
            $greatday['middleName'] ?? null,
            $greatday['lastName'] ?? null
        );
    }

    /**
     * The division name the Greatday position (posCode) maps to, or null when unresolvable.
     *
     * @param  array<string, mixed>  $greatday
     * @param  array<string, array<int|string, mixed>>  $maps
     */
    protected function greatdayDivisionName(array $greatday, array $maps): ?string
    {
        $positionId = $maps['positionsByCode'][$greatday['posCode'] ?? ''] ?? null;

        return $positionId !== null ? ($maps['divisionByPositionId'][$positionId] ?? null) : null;
    }

    /**
     * Whether the employee currently holds a production task that is waiting approval or in
     * progress. Used to block a division move that would strand active work.
     */
    protected function hasActiveProductionTasks(Employee $employee): bool
    {
        return $employee->tasks()
            ->whereIn('status', [
                TaskPicStatus::Approved->value,
                TaskPicStatus::WaitingApproval->value,
                TaskPicStatus::Revise->value,
            ])
            ->exists();
    }

    /**
     * Build an ERP bank_detail row from the Greatday bank fields, or null when Greatday has none.
     *
     * @return array<string, mixed>|null
     */
    protected function greatdayBankAccount(array $greatday): ?array
    {
        if (empty($greatday['bankAccount']) && empty($greatday['bankCode'])) {
            return null;
        }

        return [
            'bank_name' => $greatday['bankCode'] ?? null,
            'account_number' => $greatday['bankAccount'] ?? null,
            'account_holder_name' => $greatday['bankAccountName'] ?? null,
            'is_active' => true,
        ];
    }

    /**
     * The employee's first bank_detail row as an array, tolerant of a stored value that is a
     * JSON string (or double-encoded JSON) rather than an already-cast array.
     *
     * @return array<string, mixed>|null
     */
    protected function firstBankDetail(Employee $employee): ?array
    {
        $detail = $employee->bank_detail;

        while (is_string($detail)) {
            $detail = json_decode($detail, true);
        }

        return is_array($detail) ? ($detail[0] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>|null  $bank
     */
    protected function bankSignature(?array $bank): ?string
    {
        if (empty($bank)) {
            return null;
        }

        return ($bank['bank_name'] ?? '').'|'.($bank['account_number'] ?? '');
    }

    /**
     * @param  array<string, mixed>|null  $bank
     */
    protected function bankDisplay(?array $bank): string
    {
        if (empty($bank)) {
            return '-';
        }

        return trim(($bank['bank_name'] ?? '').' '.($bank['account_number'] ?? ''));
    }

    /**
     * Normalize a phone number to the ERP canonical format: digits only, with the leading
     * zero(s) and the Indonesian country code (62) stripped.
     * e.g. "08579333333", "+628579333333", "628579333333" all become "8579333333".
     */
    protected function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // Strip leading zero(s) and the 62 country code repeatedly — source data is sometimes
        // double/triple prefixed (e.g. "+626285795327357" = +62 + 62 + 85795327357).
        do {
            $before = $digits;
            $digits = ltrim($digits, '0');

            if (str_starts_with($digits, '62')) {
                $digits = substr($digits, 2);
            }
        } while ($digits !== $before);

        return $digits === '' ? null : $digits;
    }

    /**
     * Resolve a code to its human-readable name via a code->name map for display, falling back
     * to the raw code when the master data has no match, and to '-' when empty.
     *
     * @param  array<string, string>|null  $map
     */
    protected function displayCode(mixed $value, ?array $map): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $value = (string) $value;

        return $map[$value] ?? $value;
    }

    /**
     * Normalize a scalar for comparison: trim, and treat empty string as null.
     */
    protected function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Format a Greatday date string to Y-m-d, or null when empty.
     */
    protected function greatdayDate(?string $value): ?string
    {
        return ! empty($value) ? Carbon::parse($value)->format('Y-m-d') : null;
    }

    /**
     * Fetch a single page of employees from the Greatday API.
     *
     * @return array<int, array<string, mixed>> Empty array when there is no more data to process.
     */
    protected function fetchGreatdayEmployees(int $limit, int $page): array
    {
        $token = $this->greatdayService->login();

        $response = Http::withToken($token)
            ->post($this->greatdayService->getBaseUrl().'/employees', [
                'page' => $page,
                'limit' => $limit,
            ]);

        if (! $this->isGreatdayResponseSuccess($response)) {
            return [];
        }

        return $response->json()['data'];
    }

    /**
     * Store the Greatday employees that are missing from the local database into out_of_sync_employees.
     *
     * @param  array<int, array<string, mixed>>  $greatdayEmployees
     * @return int Number of out of sync records processed on this page.
     */
    protected function storeOutOfSyncEmployees(array $greatdayEmployees, int $terminalStatusId): int
    {
        $localGreatdayEmpId = Employee::selectRaw('id,employee_id,greatday_emp_id')
            ->whereNotIn('employment_status_id', [$terminalStatusId])
            ->whereNotNull('greatday_emp_id')
            ->pluck('greatday_emp_id')
            ->toArray();

        $greatdayEmployeesResult = collect($greatdayEmployees)->pluck('empId')->toArray();

        $outOfSyncEmpIds = array_diff($greatdayEmployeesResult, $localGreatdayEmpId);

        $processed = 0;

        foreach ($outOfSyncEmpIds as $empId) {
            $employeeData = collect($greatdayEmployees)->where('empId', $empId)->first();

            if (Str::contains($employeeData['email'], 'resign')) {
                continue;
            }

            $checkEmployee = Employee::where('email', $employeeData['email'])->first();

            if ($checkEmployee) {
                continue;
            }

            // updateOrCreate (not firstOrCreate) so an existing row is refreshed with the
            // latest Greatday values on every sync — e.g. an endDate that Greatday added
            // after the row was first created.
            OutOfSyncEmployee::updateOrCreate(
                [
                    'greatday_employee_id' => $employeeData['empId'],
                ],
                [
                    'first_name' => $employeeData['firstName'],
                    'middle_name' => $employeeData['middleName'],
                    'last_name' => $employeeData['lastName'],
                    'email' => $employeeData['email'],
                    'employee_id' => $employeeData['empNo'],
                    'position_code' => $employeeData['posCode'],
                    'position_name' => $employeeData['posNameEn'],
                    'employment_status' => $employeeData['employmentStatus'],
                    'employment_status_code' => $employeeData['employmentStatusCode'],
                    'start_working_date' => ! empty($employeeData['startDate']) ? Carbon::parse($employeeData['startDate']) : null,
                    'end_working_date' => ! empty($employeeData['endDate']) ? Carbon::parse($employeeData['endDate']) : null,
                    'company_id' => $employeeData['companyId'],
                    'address' => $employeeData['address'],
                    'phone' => $employeeData['phone'],
                    'job_status' => $employeeData['jobStatus'],
                    'work_location_code' => $employeeData['worklocationCode'],
                    'cost_center_code' => $employeeData['costCode'],
                    'org_unit' => $employeeData['orgUnit'],
                    'employment_start_date' => ! empty($employeeData['employmentStartDate']) ? Carbon::parse($employeeData['employmentStartDate']) : null,
                    'status' => OutOfSyncStatus::OutOfSync,
                ]
            );

            $processed++;
        }

        return $processed;
    }

    /**
     * Preflight for linking an ERP employee to Greatday. Read-only.
     *
     * Returns one of three states: already_linked; a MATCH exists (needs_create = false, no
     * form); or the employee must be created and some required create-fields are missing.
     *
     * @return array<string, mixed>
     */
    public function checkEmployeeGreatdayLink(string $employeeUid): array
    {
        try {
            $employee = $this->loadEmployeeForLink($employeeUid);

            if (! $employee) {
                return errorResponse(__('notification.employeeNotFound'));
            }

            if (! empty($employee->greatday_emp_id)) {
                return generalResponse(message: 'Success', data: [
                    'already_linked' => true,
                    'greatday_emp_id' => $employee->greatday_emp_id,
                ]);
            }

            $match = $this->matchGreatdayEmployee($employee, $this->fetchAllGreatdayEmployees());

            if ($match) {
                return generalResponse(message: 'Success', data: [
                    'already_linked' => false,
                    'needs_create' => false,
                    'ready' => true,
                    'missing' => [],
                    'match' => [
                        'greatday_emp_id' => $match['empId'] ?? null,
                        'email' => $match['email'] ?? null,
                        'emp_no' => $match['empNo'] ?? null,
                    ],
                ]);
            }

            $missing = $this->detectMissingCreateFields($employee, []);

            return generalResponse(message: 'Success', data: [
                'already_linked' => false,
                'needs_create' => true,
                'ready' => empty($missing),
                'missing' => $missing,
            ]);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Link an ERP employee to Greatday: reuse an existing Greatday record (match on email or
     * empNo) or create one, then store greatday_emp_id locally.
     *
     * Filled create-fields from the form are persisted onto the employee. External calls happen
     * before the local write, which is atomic — no half-linked local state on failure.
     *
     * @return array<string, mixed>
     */
    public function linkEmployeeToGreatday(string $employeeUid, LinkEmployeeData $data): array
    {
        try {
            $employee = $this->loadEmployeeForLink($employeeUid);

            if (! $employee) {
                return errorResponse(__('notification.employeeNotFound'));
            }

            if (! empty($employee->greatday_emp_id)) {
                return generalResponse(message: 'Success', data: [
                    'linked' => true,
                    'created' => false,
                    'greatday_emp_id' => $employee->greatday_emp_id,
                ]);
            }

            $provided = $data->filled();

            $match = $this->matchGreatdayEmployee($employee, $this->fetchAllGreatdayEmployees());

            if ($match) {
                $greatdayEmpId = $match['empId'];
                $created = false;
            } else {
                $missing = $this->detectMissingCreateFields($employee, $provided);

                if (! empty($missing)) {
                    return errorResponse(__('notification.greatdayLinkMissingFields'), ['missing' => $missing], Code::BadRequest->value);
                }

                $response = $this->greatdayService->addEmployee($this->buildGreatdayAddPayload($employee, $provided));
                $result = $response->json()[0] ?? null;

                if ($response->failed() || ! ($result['success'] ?? false)) {
                    throw new EmployeeException($result['message'] ?? __('notification.greatdayAddEmployeeFailed'));
                }

                // the add response has no empId; re-fetch and resolve it by empNo
                $newMatch = collect($this->fetchAllGreatdayEmployees())->firstWhere('empNo', $employee->employee_id);

                if (! $newMatch) {
                    throw new EmployeeException(__('notification.greatdayAddEmployeeUnresolved'));
                }

                $greatdayEmpId = $newMatch['empId'];
                $created = true;
            }

            DB::transaction(function () use ($employee, $provided, $greatdayEmpId) {
                $update = ['greatday_emp_id' => $greatdayEmpId];

                foreach ($provided as $column => $value) {
                    if ($column === 'boss_id') {
                        $bossId = Employee::where('uid', $value)->value('id');
                        if ($bossId) {
                            $update['boss_id'] = $bossId;
                        }
                    } else {
                        $update[$column] = $value;
                    }
                }

                $this->repo->update($update, $employee->uid);
            });

            return generalResponse(message: 'Success', data: [
                'linked' => true,
                'created' => $created,
                'greatday_emp_id' => $greatdayEmpId,
            ]);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function loadEmployeeForLink(string $employeeUid): ?Employee
    {
        return $this->repo->show(
            uid: $employeeUid,
            select: '*',
            relation: [
                'user:id,employee_id,username',
                'position:id,name,greatday_code',
                'boss:id,employee_id',
            ]
        );
    }

    /**
     * Find the Greatday employee that represents this ERP employee, matched on email or empNo.
     *
     * @param  array<int, array<string, mixed>>  $greatdayEmployees
     * @return array<string, mixed>|null
     */
    protected function matchGreatdayEmployee(Employee $employee, array $greatdayEmployees): ?array
    {
        foreach ($greatdayEmployees as $greatday) {
            $emailMatch = ! empty($greatday['email']) && ! empty($employee->email)
                && strcasecmp($greatday['email'], $employee->email) === 0;
            $empNoMatch = ! empty($greatday['empNo']) && ! empty($employee->employee_id)
                && $greatday['empNo'] === $employee->employee_id;

            if ($emailMatch || $empNoMatch) {
                return $greatday;
            }
        }

        return null;
    }

    /**
     * Required Greatday create-fields that may be empty on the employee: [column, label, source].
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    protected function greatdayCreateRequiredFields(): array
    {
        return [
            ['greatday_nationality', 'Nationality', 'nationality'],
            ['greatday_company', 'Company', 'company'],
            ['greatday_job_grade', 'Job Grade', 'job_grade'],
            ['greatday_cost_center', 'Cost Center', 'cost_center'],
            ['greatday_employment_status', 'Employment Status', 'employment_status'],
            ['greatday_work_location', 'Work Location', 'work_location'],
            ['greatday_religion', 'Religion', 'religion'],
            ['greatday_timezone', 'Timezone', 'timezone'],
            ['greatday_shift_pattern', 'Shift Pattern', 'shift_pattern'],
            ['boss_id', 'Supervisor', 'employee'],
            ['position', 'Position', 'position'],
        ];
    }

    /**
     * Which required create-fields are still empty, considering both the employee and any values
     * provided in the link form.
     *
     * @param  array<string, mixed>  $provided
     * @return array<int, array<string, string>>
     */
    protected function detectMissingCreateFields(Employee $employee, array $provided): array
    {
        $missing = [];

        foreach ($this->greatdayCreateRequiredFields() as [$field, $label, $source]) {
            $present = match ($field) {
                'position' => ! empty($employee->position?->greatday_code),
                'boss_id' => ! empty($employee->boss_id) || ! empty($provided['boss_id']),
                default => ! empty($employee->{$field}) || ! empty($provided[$field]),
            };

            if ($present) {
                continue;
            }

            // `fixable` = can be supplied by the link form. `position` cannot — it needs the
            // position to be linked to Greatday first (Position Sync), so it's a blocker.
            $item = [
                'field' => $field,
                'label' => $label,
                'source' => $source,
                'fixable' => $field !== 'position',
                'hint' => null,
            ];

            if ($field === 'position') {
                $item['hint'] = __('notification.positionNotLinkedToGreatday', [
                    'position' => $employee->position?->name ?? '-',
                ]);
            }

            $missing[] = $item;
        }

        return $missing;
    }

    /**
     * Build the Greatday AddEmployeeRequestItem from the ERP employee, with form values overriding.
     *
     * @param  array<string, mixed>  $provided
     * @return array<string, mixed>
     */
    protected function buildGreatdayAddPayload(Employee $employee, array $provided): array
    {
        $value = fn (string $column) => $provided[$column] ?? $employee->{$column};
        [$first, $middle, $last] = $this->splitName((string) $employee->name);
        $supervisorEmpNo = $this->greatdaySupervisorEmpNo($employee, $provided);
        $bank = $this->firstBankDetail($employee);

        return array_filter([
            'employeeNo' => $employee->employee_id,
            'companyId' => $this->resolveGreatdayCompanyId($value('greatday_company')),
            'userName' => $employee->user?->username ?? $employee->employee_id,
            'email' => $employee->email,
            'employeeNameFirst' => $first,
            'employeeNameMiddle' => $middle ?: null,
            'employeeNameLast' => $last ?: null,
            'nationality' => $value('greatday_nationality'),
            'position' => $employee->position?->greatday_code,
            'joinDate' => $employee->join_date ? Carbon::parse($employee->join_date)->format('Y-m-d') : null,
            'gender' => $employee->gender?->value === Gender::Male->value ? 1 : 0,
            'birthDay' => $employee->date_of_birth ? Carbon::parse($employee->date_of_birth)->format('Y-m-d') : null,
            'grade' => $value('greatday_job_grade'),
            'costCenter' => $value('greatday_cost_center'),
            'status' => $value('greatday_employment_status'),
            'workLocation' => $value('greatday_work_location'),
            'birthPlace' => $employee->place_of_birth,
            'religion' => $value('greatday_religion'),
            'maritalStatus' => (string) ($employee->martial_status?->value === MartialStatus::Married->value ? 1 : 0),
            'address' => $employee->address,
            'timezoneId' => (int) $value('greatday_timezone'),
            'mobilePhone' => $employee->phone,
            'supervisor' => $supervisorEmpNo,
            'manager' => $supervisorEmpNo,
            'shiftPattern' => $value('greatday_shift_pattern'),
            'allowCreateNewMaster' => true,

            // optional extras
            'idCardNumber' => $employee->id_number,
            'jobStatus' => $employee->greatday_job_status,
            'bankCode' => $bank['bank_name'] ?? null,
            'bankAccount' => $bank['account_number'] ?? null,
            'bankAccountName' => $bank['account_holder_name'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Resolve Greatday's numeric companyId. The employee's greatday_company usually holds the
     * company CODE (e.g. "sfgo11677"), while the Add Employee API needs the numeric id (35532),
     * so we look it up in the greatday_companies master. A value that is already numeric is used
     * as-is; an unresolvable value returns null (create then fails clearly, rather than sending 0).
     */
    protected function resolveGreatdayCompanyId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $company = GreatdayCompany::where('code', $value)->first();

        return $company ? (int) $company->company_id : null;
    }

    /**
     * The supervisor's employee number (empNo) — from the chosen supervisor uid, else the boss.
     *
     * @param  array<string, mixed>  $provided
     */
    protected function greatdaySupervisorEmpNo(Employee $employee, array $provided): ?string
    {
        if (! empty($provided['boss_id'])) {
            return Employee::where('uid', $provided['boss_id'])->value('employee_id');
        }

        return $employee->boss?->employee_id;
    }

    /**
     * Split a single ERP name into [first, middle, last].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    protected function splitName(string $name): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        if (empty($parts)) {
            return ['', '', ''];
        }

        $first = array_shift($parts);
        $last = count($parts) > 0 ? array_pop($parts) : '';
        $middle = implode(' ', $parts);

        return [$first, $middle, $last];
    }

    public function totalActiveEmployee(): array
    {
        try {
            return generalResponse(
                message: 'Success',
                data: [
                    'total' => $this->repo->getTotalActiveEmployee(),
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
