<?php

namespace Modules\Hrd\Services;

use App\Enums\Employee\ProbationStatus;
use App\Enums\Employee\Status;
use App\Enums\ErrorCode\Code;
use App\Exceptions\EmployeeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;
use Modules\Company\Models\Position;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use \PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;

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

    public function __construct()
    {
        $this->repo = new EmployeeRepository;

        $this->positionRepo = new PositionRepository;

        $this->userRepo = new \App\Repository\UserRepository();

        $this->taskRepo = new \Modules\Production\Repository\ProjectTaskRepository();

        $this->projectRepo = new \Modules\Production\Repository\ProjectRepository();

        $this->projectVjRepo = new \Modules\Production\Repository\ProjectVjRepository();

        $this->projectPicRepo = new \Modules\Production\Repository\ProjectPersonInChargeRepository();

        $this->projectTaskHistoryRepo = new \Modules\Production\Repository\ProjectTaskPicHistoryRepository();

        $this->employeeFamilyRepo = new \Modules\Hrd\Repository\EmployeeFamilyRepository();

        $this->employeeEmergencyRepo = new \Modules\Hrd\Repository\EmployeeEmergencyContactRepository();
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
    ) : array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $search = request('search');
            $where = '';
            if (!empty($search)) {
                if (!empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "lower(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = $search['name'];
                    $where .= " AND lower(name) LIKE '%{$name}%'";
                }

                if (!empty($search['employee_id']) && empty($where)) {
                    $employee_id = strtolower($search['employee_id']);
                    $where = "lower(employee_id) = '{$employee_id}'";
                } else if (!empty($search['employee_id']) && !empty($where)) {
                    $employee_id = $search['employee_id'];
                    $where .= " AND lower(employee_id) = '{$employee_id}'";
                }

                if (!empty($search['position_id'])) {
                    if (empty($where)) {
                        $where = "position_id IN (";
                    } else if (!empty($where)) {
                        $where .= " AND position_id IN (";
                    }

                    foreach ($search['position_id'] as $positionId) {
                        $posId = getIdFromUid($positionId, new \Modules\Company\Models\Position());
                        $where .= "'{$posId}',";
                    }

                    $where = rtrim($where, ',') . ")";
                }

                if (!empty($search['level_staff'])) {
                    if (empty($where)) {
                        $where = "level_staff IN (";
                    } else if (!empty($where)) {
                        $where .= " AND level_staff IN (";
                    }

                    foreach ($search['level_staff'] as $levelStaff) {
                        $where .= "'{$levelStaff}',";
                    }

                    $where = rtrim($where, ',') . ")";
                }

                if (!empty($search['join_date']) && !empty($search['join_date_condition'])) {
                    $condition = $search['join_date_condition'];
                    if ($condition == 'equal') {
                        $accessor = '=';
                    } else if ($condition == 'less_than') {
                        $accessor = '<=';
                    } else {
                        $accessor = '>=';
                    }

                    if (empty($where)) {
                        $where = "join_date {$accessor} '{$search['join_date']}'";
                    } else if (!empty($where)) {
                        $where .= " AND join_date {$accessor} '{$search['join_date']}'";
                    }
                }
            }
            
            if (!empty($search['status'])) {
                if (empty($where)) {
                    $where = "status = {$search['status']}";
                } else {
                    $where .= " AND status = {$search['status']}";
                }
            } else {
                if (empty($where)) {
                    $where = 'status != ' . \App\Enums\Employee\Status::Inactive->value;
                } else {
                    $where .= " and status != " . \App\Enums\Employee\Status::Inactive->value;
                }
            }


            $order = '';
            $sortBy = request('sortBy');
            if(!empty($sortBy)) {
                foreach ($sortBy as $item) {
                    if($item['key'] == 'position.name') {
                        $item['key'] = 'position_id';
                    } else if ($item['key'] == 'nip') {
                        $item['key'] = 'employee_id';
                    }
                    $orderBy[] = $item['key']." ".$item['order'];
                }
                $order = implode(', ', $orderBy);
            } else {
                $order = 'employee_id asc';
            }


            $employees = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $order
            );

            $paginated = collect($employees)->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'name' => $item->name,
                    'email' => $item->email,
                    'position' => $item->position->name,
                    'level_staff' => __("global.{$item->level_staff}"),
                    'status' => $item->status_text,
                    'status_color' => $item->status_color,
                    'join_date' => date('d F Y', strtotime($item->join_date)),
                    'phone' => $item->phone,
                    'placement' => $item->placement,
                    'employee_id' => $item->employee_id,
                    'user' => $item->user,
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
         *
         */
        $idNumberLength = 3;
        $prefix = 'DF';
        $numbering = $prefix . str_pad($count, $idNumberLength, 0, STR_PAD_LEFT);

        return generalResponse(
            'success',
            false,
            [
                'employee_id' => $numbering,
            ],
        );
    }

    public function getVJ(string $projectUid)
    {
        $positionAsVJ = json_decode(getSettingByKey('position_as_visual_jokey'), true);

        $output = [];
        
        if ($positionAsVJ) {
            $positionAsVJ = collect($positionAsVJ)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\Position());
            })->toArray();

            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $project = $this->projectRepo->show($projectUid, 'id,project_date');

            $position = implode(',', $positionAsVJ);

            $data = $this->repo->list('uid,name,id', "position_id IN (" . $position . ") and status != " . \App\Enums\Employee\Status::Inactive->value)->toArray();

            $output = collect($data)->map(function ($employee) use ($project) {
                // check the calendar
                $calendar = $this->projectVjRepo->list('id,project_id', 'employee_id = ' . $employee['id'], [
                    'project:id,project_date'
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
                    'date' => count($selectedDate)
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
     * Function to get all data
     *
     * @return array
     */
    public function getAll()
    {
        $where = '';
        // $levelStaffOrder = \App\Enums\Employee\LevelStaff::levelStaffOrder();
        $levelStaffOrder = [
            "manager",
            "lead",
            "staff",
            "junior staff"
        ];                             

        $key = request()->min_level;

        if (!empty(request()->min_level)) {
            $search = array_search($key, $levelStaffOrder);

            if ($search > 0) {
                $splice = array_splice($levelStaffOrder, 0, $search);

                $splice = collect($splice)->map(function ($item) {
                    return "'{$item}'";
                })->toArray();
    
                $where = "level_staff IN (". implode(',', $splice) .")";
            }

        }

        if (!empty(request('name'))) {
            if (empty($where)) {
                $where = "lower(name) like '%". strtolower(request('name')) ."%'";
            } else {
                $where .= " and lower(name) like '%". strtolower(request('name')) ."%'";
            }
        }

        if (!empty($where)) {
            $where .= " and status != " . \App\Enums\Employee\Status::Inactive->value;
        } else {
            $where = "status != " . \App\Enums\Employee\Status::Inactive->value;
        }

        $data = $this->repo->list(
            'uid,id,name',
            $where
        );

        $data = collect($data)->map(function ($item) {
            return [
                'value' => $item->uid,
                'title' => $item->name,
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
        $encrypter = new \App\Services\EncryptionService();

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
     * @param string $id
     * @return array
     */
    public function addAsUser(string $id)
    {
        DB::beginTransaction();
        try {
            $user = $this->repo->show($id, 'id,email,name');

            // generate random password
            $password = generateRandomPassword();
            $userId = $this->userRepo->store([
                'email' => $user->email,
                'password' => $password,
            ]);

            $this->repo->update([
                'user_id' => $userId->id
            ], $id);

            \Modules\Hrd\Jobs\SendEmailActivationJob::dispatch($password, $userId)->afterCommit();

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
     * @param string $key
     * @param string $value
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
        ];

        $data = $this->repo->show($uid, $select, $relation);

        // get projects and tasks if any
        $projects = [];
        $asPicProjects = $this->projectRepo->list('id,name,uid,project_date,created_at', '', [], [
            [
                'relation' => 'personInCharges',
                'query' => "pic_id = " . $data->id,
            ],
        ]);
        $asPicProjects = collect((object) $asPicProjects)->map(function ($item) {
            return [
                'id' => $item->uid,
                'name' => $item->name,
                'position' => __("global.asPicProject"),
                'project_date' => date('d F Y', strtotime($item->project_date)),
                'assign_at' => date('d F Y', strtotime($item->created_at)),
                'detail_task' => [],
            ];
        })->toArray();
        $projects = array_merge($projects, $asPicProjects);

        $asPicTaskRaw = $this->taskRepo->list('id,project_id,name,created_at,start_working_at,uid,created_at', '', ['project:id,name,uid,project_date'], [
            [
                'relation' => 'pics',
                'query' => 'employee_id = ' . $data->id,
            ]
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

        $data['bank_detail'] = json_decode($data->bank_detail, true);
        $data['emergency_contact'] = json_decode($data->relation_contact, true);

        $data['join_date_format'] = date('d F Y', strtotime($data->join_date));
        $data['length_of_service'] = getLengthOfService($data->join_date);

        $data['level_staff_text'] = \App\Enums\Employee\LevelStaff::generateLabel('staff');
        
        $data['boss_uid'] = null;
        $data['approval_line'] = null;
        if ($data->boss_id) {
            $bossData = $this->repo->show('dummy', 'id,uid,name', [], 'id = ' . $data->boss_id);
            $data['boss_uid'] = $bossData->uid;
            $data['approval_line'] = $bossData->name;
        }

        return $data->toArray();
    }

    /**
     * Get specific data by id
     *
     * @param string $uid
     * @param string $select
     * @param array $relation
     *
     * @return array
     */
    public function show(
        string $uid,
        string $select = '*',
        array $relation = []
    ): array
    {
        try {
            // validate permission
            $user = auth()->user();
            $employeeId = getIdFromUid($uid, new \Modules\Hrd\Models\Employee());
            if (
                $user->email != config('app.root_email') &&
                !$user->is_director
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
     *
     * @param array $data
     *
     * @return array
     */
    public function store(array $data): array
    {
        try {
            $data['bank_detail'] = json_encode($data['banks']);
            $data['relation_contact'] = json_encode($data['relation']);
            $data['education'] = $data['educations']['education'];
            $data['education_name'] = $data['educations']['education_name'];
            $data['education_major'] = $data['educations']['education_major'];
            $data['education_year'] = $data['educations']['graduation_year'];
            $data['level_staff'] = $data['level'];
            $data['join_date'] = date('Y-m-d', strtotime($data['join_date']));
            $data['start_review_probation_date'] = isset($data['start_review_date']) ? date('Y-m-d', strtotime($data['start_review_date'])) : null;
            $data['end_probation_date'] = isset($data['end_probation_date']) ? date('Y-m-d', strtotime($data['end_probation_date'])) : null;
            $data['company_name'] = $data['company'];
            $data['date_of_birth'] = $data['date_of_birth'] != 'null' ? date('Y-m-d', strtotime($data['date_of_birth'])) : null;
            $data['position_id'] = getIdFromUid($data['position_id'], new Position());
            $data['province_id'] = isset($data['province_id']) ? $data['province_id'] : null;
            $data['city_id'] = isset($data['city_id']) ? $data['city_id'] : null;
            $data['district_id'] = isset($data['district_id']) ? $data['district_id'] : null;
            $data['village_id'] = isset($data['village_id']) ? $data['village_id'] : null;
            $data['dependant'] = $data['dependents'] != null ? $data['dependents'] : null;

            if (isset($data['boss_id'])) {
                $data['boss_id'] = getIdFromUid($data['boss_id'], new \Modules\Hrd\Models\Employee());
            }

            if (
                (isset($data['id_number_photo'])) &&
                ($data['id_number_photo'])
            ) {
                $this->idCardPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['id_number_photo']
                );
                $data['id_number_photo'] = $this->idCardPhotoTmp;
            }

            if (
                (isset($data['npwp_photo'])) && 
                ($data['npwp_photo'])
            ) {
                $this->npwpPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['npwp_photo']
                );
                $data['npwp_photo'] = $this->npwpPhotoTmp;
            }

            if (
                (isset($data['bpjs_photo'])) &&
                ($data['bpjs_photo'])
            ) {
                $this->bpjsPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['bpjs_photo']
                );
                $data['bpjs_photo'] = $this->bpjsPhotoTmp;
            }

            if (
                (isset($data['kk_photo'])) &&
                ($data['kk_photo'])
            ) {
                $this->kkPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['kk_photo']
                );
                $data['kk_photo'] = $this->kkPhotoTmp;
            }

            $this->repo->store(collect($data)->except([
                'banks',
                'relation',
                'educations',
                'level',
                'start_review_date',
                'company',
                'id_card_photo'
            ])->toArray());

            \Illuminate\Support\Facades\Cache::forget('maximumProjectPerPM');

            return generalResponse(
                __("global.successCreateEmployee"),
                false,
                [],
            );
        } catch (\Throwable $th) {
            if ($this->idCardPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->idCardPhotoTmp));
            }
            if ($this->npwpPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->npwpPhotoTmp));
            }
            if ($this->bpjsPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->bpjsPhotoTmp));
            }
            if ($this->kkPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->kkPhotoTmp));
            }

            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Update personal data - basic info
     *
     * @param array $payload
     * @param string $employeeUid
     * @return array
     */
    public function updateBasicInfo(array $payload, string $employeeUid): array
    {
        try {
            $this->repo->update($payload, $employeeUid);

            // get detail to refresh data in the front page
            $data = $this->getDetailEmployee($employeeUid, '*');

            return generalResponse(
                __("global.successEditEmployeeData"),
                false,
                $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update personal data - identity & address
     *
     * @param array $payload
     * @param string $employeeUid
     * @return array
     */
    public function updateIdentity(array $payload, string $employeeUid): array
    {
        try {
            $this->repo->update($payload, $employeeUid);

            // get detail to refresh data in the front page
            $data = $this->getDetailEmployee($employeeUid, '*');

            return generalResponse(
                __("global.successEditEmployeeData"),
                false,
                $data
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update data
     *
     * @param string $uid
     * @param array $data
     * @return array
     */
    public function update(
        array $data,
        string $uid='',
        string $where=''
    ): array
    {
        try {
            $deletedImages = $data['deleted_image'] ?? [];
            // $employee = $this->repo->show($uid, 'id,id_number_photo,npwp_photo,kk_photo,bpjs_photo');

            $data['bank_detail'] = json_encode($data['banks']);
            $data['relation_contact'] = json_encode($data['relation']);
            $data['education'] = $data['educations']['education'];
            $data['education_name'] = $data['educations']['education_name'];
            $data['education_major'] = $data['educations']['education_major'];
            $data['education_year'] = $data['educations']['graduation_year'];
            $data['current_address'] = $data['current_address'] != 'null' ? $data['current_address'] : null;
            $data['level_staff'] = $data['level'];
            $data['join_date'] = date('Y-m-d', strtotime($data['join_date']));
            $data['start_review_probation_date'] = (isset($data['start_review_date']) && ($data['start_review_date'] != 'null')) ? date('Y-m-d', strtotime($data['start_review_date'])) : null;
            $data['end_probation_date'] = (isset($data['end_probation_date']) && $data['end_probation_date'] != 'null') ? date('Y-m-d', strtotime($data['end_probation_date'])) : null;
            $data['company_name'] = $data['company'];
            $data['date_of_birth'] = $data['date_of_birth'] != 'null' ? date('Y-m-d', strtotime($data['date_of_birth'])) : null;
            $data['position_id'] = getIdFromUid($data['position_id'], new Position());
            $data['province_id'] = isset($data['province_id']) ? $data['province_id'] : null;
            $data['city_id'] = isset($data['city_id']) ? $data['city_id'] : null;
            $data['district_id'] = isset($data['district_id']) ? $data['district_id'] : null;
            $data['village_id'] = isset($data['village_id']) ? $data['village_id'] : null;
            $data['dependant'] = $data['dependents'] != null ? $data['dependents'] : null;

            if (isset($data['boss_id'])) {
                $data['boss_id'] = getIdFromUid($data['boss_id'], new \Modules\Hrd\Models\Employee());
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['id_number_photo']))) {
                $data['id_number_photo'] = null;
            }
            if (isset($data['id_number_photo'])) {
                $this->idCardPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['id_number_photo']
                );
                $data['id_number_photo'] = $this->idCardPhotoTmp;
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['npwp_photo']))) {
                $data['npwp_photo'] = null;
            }
            if (isset($data['npwp_photo'])) {
                $this->npwpPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['npwp_photo']
                );
                $data['npwp_photo'] = $this->npwpPhotoTmp;
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['bpjs_photo']))) {
                $data['bpjs_photo'] = null;
            }
            if (isset($data['bpjs_photo'])) {
                $this->bpjsPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['bpjs_photo']
                );
                $data['bpjs_photo'] = $this->bpjsPhotoTmp;
            }

            if ((count($deletedImages) > 0) && (isset($deletedImages['kk_photo']))) {
                $data['kk_photo'] = null;
            }
            if (isset($data['kk_photo'])) {
                $this->kkPhotoTmp = uploadImageandCompress(
                    'employees',
                    10,
                    $data['kk_photo']
                );
                $data['kk_photo'] = $this->kkPhotoTmp;
            }

            logging('employee payload', $data);

            $payloadUpdate = collect($data)->except([
                'banks',
                'relation',
                'educations',
                'level',
                'start_review_date',
                'company',
                'id_card_photo',
                'is_residence_same',
                'dependents',
                'deleted_image'
            ])->toArray();

            $this->repo->update($payloadUpdate, $uid);

            \Illuminate\Support\Facades\Cache::forget('maximumProjectPerPM');

            \Modules\Hrd\Jobs\DeleteImageJob::dispatch($deletedImages);

            return generalResponse(
                __("global.successUpdateEmployee"),
                false,
                [
                    'payload' => $payloadUpdate,
                    'data' => $data,
                ],
            );
        } catch (\Throwable $th) {
            if ($this->idCardPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->idCardPhotoTmp));
            }
            if ($this->npwpPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->npwpPhotoTmp));
            }
            if ($this->bpjsPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->bpjsPhotoTmp));
            }
            if ($this->kkPhotoTmp) {
                deleteImage(storage_path('app/public/employees/' . $this->kkPhotoTmp));
            }

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
     *
     * @param string $uid
     *
     * @return array
     */
    public function delete(string $uid): array
    {
        try {
            $data = $this->repo->show($uid,'id,name,uid', [
                'projects:id,project_id,pic_id'
            ]);

            $employeeErrorStatus = false;

            if (count($data->projects) > 0) {
                $employeeErrorRelation[] = 'projects';
                $employeeErrorStatus = true;
            }

            if ($employeeErrorStatus) {
                throw new EmployeeException(__("global.employeeRelationFound", [
                    'name' => $data->name,
                    'relation' => implode(' and ',$employeeErrorRelation)
                ]));
            }

            $this->repo->delete($uid);

            \Illuminate\Support\Facades\Cache::forget('maximumProjectPerPM');

            return generalResponse(
                __("global.successDeletePosition"),
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
            $images = [];
            foreach ($ids as $id) {
                $employee = $this->repo->show($id, 'id,id_number_photo,npwp_photo,kk_photo,bpjs_photo');
                // get relation to project
                $asPic = $this->projectPicRepo->show('dummy', 'id', [], 'pic_id = ' . $employee->id);

                // get as task pic
                $asTaskPic = $this->projectTaskHistoryRepo->show('dummy', 'id', [], 'employee_id = ' . $employee->id);

                $employeeErrorStatus = false;

                if ($asPic || $asTaskPic) {
                    $employeeErrorRelation[] = 'projects';
                    $employeeErrorStatus = true;
                }

                if ($employeeErrorStatus) {
                    DB::rollBack();
                    throw new EmployeeException(__("global.employeeRelationFound", [
                        'name' => $employee->name,
                        'relation' => implode(' and ',$employeeErrorRelation)
                    ]));
                }

                if ($employee->id_number_photo) {
                    $images[] = $employee->id_number_photo;
                }
                if ($employee->nwp_photo) {
                    $images[] = $employee->npwp_photo;
                }
                if ($employee->kk_photo) {
                    $images[] = $employee->kk_photo;
                }
                if ($employee->bpjs_photo) {
                    $images[] = $employee->bpjs_photo;
                }
            }

            // $this->repo->bulkDelete($ids, 'uid');

            \Illuminate\Support\Facades\Cache::forget('maximumProjectPerPM');

            \Modules\Hrd\Jobs\DeleteImageJob::dispatch($images)->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.successDeleteEmployee'),
                false,
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

        if (!$data) {
            $projectManagerPosition = json_decode(getSettingByKey('position_as_project_manager'), true);

            $projectManagerPosition = collect($projectManagerPosition)->map(function ($item ) {
                return getIdFromUid($item, new \Modules\Company\Models\Position());
            })->toArray();

            $condition = implode("','", $projectManagerPosition);
            $condition = "('" . $condition . "')";

            $employees = $this->repo->list('id,name', "position_id in " . $condition);

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
        $maximumProjectPerPM = $projectManagerCount -1;

        $relation = [
            'projects:id,pic_id,project_id',
            'projects.project:id,name,project_date'
        ];

        if (!empty($month)) {
            $endDateOfMonth = Carbon::createFromDate((int) date('Y', strtotime(request('date'))), (int) date('m', strtotime(request('date'))), 1)
            ->endOfMonth()
            ->format('d');

            $startDate = date('Y-m', strtotime(request('date'))) . '-01';
            $endDate = date('Y-m', strtotime(request('date'))) . '-' . $endDateOfMonth;

            $relation = [
                'projects' => function ($query) use ($startDate, $endDate) {
                    $query->selectRaw('id,pic_id,project_id')
                        ->whereHas('project', function($q) use ($startDate, $endDate) {
                            $q->whereRaw("project_date >= '" . $startDate . "' and project_date <= '" . $endDate . "'");
                        });
                }
            ];
        }

        $positionAsProjectManager = json_decode(getSettingByKey('position_as_project_manager'), true);
        
        if ($positionAsProjectManager) {
            $positionCondition = implode("','", $positionAsProjectManager);
            $positionCondition = "('" . $positionCondition . "')";
            $whereHas[] = [
                'relation' => 'position',
                'query' => "uid IN " . $positionCondition,
            ];
        }

        $data = $this->repo->list(
            'id, uid as value, name as title', 
            'status != ' . \App\Enums\Employee\Status::Inactive->value, 
            $relation, 
            '', 
            '', 
            $whereHas
        );

        $employees = collect($data)->map(function ($item) use ($date, $month, $maximumProjectPerPM) {
            $projects = collect($item->projects)->pluck('project.project_date')->values();
            $item['workload_on_date'] = 0;
            if (!empty($date)) {
                $filter = collect($projects)->filter(function ($filter) use ($date, $month) {
                    $dateStart = date('Y-m-d', strtotime($month . '-01'));
                    return $filter == $date;
                })->values();
            }

            $totalProject = $item->projects->count();

            // coloring options based on project manager maximum project
            if ($totalProject > $maximumProjectPerPM) {
                $coloring = 'red';
            } else if ($totalProject == $maximumProjectPerPM) {
                $coloring = 'orange-darken-4';
            } else if (
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
            41, 42, 43, 44, 45, 46, 47, 49, 50, 51
        ];

        $employees = [];
        foreach ($response as $key => $row) {
            $jobName = ltrim(rtrim($row[$jobNameKey]));
            $positionData = \Modules\Company\Models\Position::select('id')
                ->whereRaw("lower(name) = '" . strtolower($jobName) . "'")
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
     * 
     * @param array $response
     * 
     * @return array
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
                return !$filter['wrong_format'];
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

                $check = $this->repo->show('dummy', 'id', [], "lower(employee_id) = '" . strtolower($employee['employee_id']) . "'");

                if ($check) {
                    $this->repo->update(collect($employee)->except(['boss_id', 'wrong_format', 'wrong_data'])->toArray(), '', "lower(employee_id) = '" . strtolower($employee['employee_id']) . "'");
                } else {
                    $this->repo->store(collect($employee)->except(['boss_id', 'wrong_format', 'wrong_data'])->toArray());
                }
            }
    
            // handle boss id
            foreach ($response as $employee) {
                if ($employee['boss_id']) {
                    $bossId = $this->repo->show('dummy', 'id,employee_id', [], "lower(employee_id) = '" . strtolower($employee['boss_id']) . "'");
    
                    if ($bossId) {
                        $this->repo->update(
                            ['boss_id' => $bossId->id],
                            'dummy',
                            "lower(employee_id) = '" . strtolower($employee['employee_id']) . "'"
                        );
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return generalResponse(
                __("global.successImportData"),
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
                        !$employee[$requirement] ||
                        empty($employee[$requirement]) ||
                        $employee[$requirement] == null ||
                        $employee[$requirement] == 'null'
                    )
                ) {
                    $output[$key]['wrong_format'] = true;
                    $message = "global." . snakeToCamel($requirement) . 'Required';
                    array_push($wrong, trans($message));
                }

                if (!isset($employee[$requirement])) {
                    $output[$key]['wrong_format'] = true;
                    $message = "global." . snakeToCamel($requirement) . 'Required';
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
            "Success",
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
     *
     * @param array $payload
     * @param string $employeeUid
     * @return array
     */
    public function storeFamily(array $payload, string $employeeUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee());

            $payload['employee_id'] = $employeeId;
            logging('payload', $payload);
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
     * @param array $payload
     * @param string $employeeUid
     * @return array
     */
    public function updateFamily(array $payload, string $familyUid): array
    {
        DB::beginTransaction();
        try {
            $this->employeeFamilyRepo->update($payload, $familyUid,);

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
     *
     * @param string $employeeUid
     * @return array
     */
    public function initFamily(string $employeeUid): array
    {
        $data = $this->employeeFamilyRepo->list('*', 'employee_id = ' . getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee()));

        $family = \App\Enums\Employee\RelationFamily::cases();

        $output = collect((object) $data)->map(function ($item) use ($family) {
            $relation = '-';
            foreach ($family as $f) {
                if ($item->relation == $f->value) {
                    $relation = $f->label();
                }
            }

            return [
                'uid' => $item->uid,
                'name' => $item->name,
                'relation' => $relation,
                'relation_raw' => $item->relation,
                'id_number' => $item->id_number,
                'date_of_birth' => $item->date_of_birth ? date('d F Y', strtotime($item->date_of_birth)) : '-',
                'date_of_birth_raw' => $item->date_of_birth,
                'gender' => $item->gender,
                'job' => $item->job,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    /**
     * Delete family data
     *
     * @param string $familyUid
     * @return array
     */
    public function deleteFamily(string $familyUid): array
    {
        try {
            $this->employeeFamilyRepo->delete($familyUid);

            return generalResponse(
                __("global.successDeleteFamily"),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get family list of each employee
     *
     * @param string $employeeUid
     * @return array
     */
    public function initEmergency(string $employeeUid): array
    {
        $data = $this->employeeEmergencyRepo->list('*', 'employee_id = ' . getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee()));

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
     *
     * @param array $payload
     * @param string $employeeUid
     * @return array
     */
    public function storeEmergency(array $payload, string $employeeUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee());

            $payload['employee_id'] = $employeeId;
            logging('payload', $payload);
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
     * @param array $payload
     * @param string $employeeUid
     * @return array
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
     * @param string $familyUid
     * @return array
     */
    public function deleteEmergency(string $emergencyUid): array
    {
        try {
            $this->employeeEmergencyRepo->delete($emergencyUid);

            return generalResponse(
                __("global.successDeleteEmergencyContact"),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * update employment data
     *
     * @param array $data
     * @param string $employeeUid
     * @return array
     */
    public function updateEmployment(array $payload, string $employeeUid): array
    {
        try {
            $payload['level_staff'] = $payload['level'];
            $payload['position_id'] = getIdFromUid($payload['position_id'], new \Modules\Company\Models\Position());
            $payload['boss_id'] = getIdFromUid($payload['boss_id'], new \Modules\Hrd\Models\Employee());

            $this->repo->update(collect($payload)->except(['level'])->toArray(), $employeeUid);

            return generalResponse(
                __('global.successUpdateEmployment'),
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Employee is resign
     * 
     * @param array<string, string> $data
     * @param string employeeUid
     * 
     * @return array
     */
    public function resign(array $data, string $employeeUid)
    {
        $employeeId = getIdFromUid($employeeUid, new \Modules\Hrd\Models\Employee());

        $this->repo->update([
            'end_date' => date('Y-m-d'),
            'resign_reason' => $data['reason'],
            'status' => \App\Enums\Employee\Status::Inactive->value,
        ], $employeeUid);

        \App\Models\User::where('employee_id', $employeeId)->delete();

        return generalResponse(
            __('notification.successResign'),
            false
        );
    }
}
