<?php

namespace Modules\Production\Services;

use App\Enums\Employee\Status;
use App\Enums\ErrorCode\Code;
use App\Exceptions\failedToProcess;
use App\Exceptions\NotRegisteredAsUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectReferenceRepository;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectTaskAttachmentRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectTaskLogRepository;
use Modules\Production\Repository\ProjectTaskProofOfWorkRepository;
use Modules\Production\Repository\ProjectTaskWorktimeRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;
use Modules\Production\Repository\ProjectTaskReviseHistoryRepository;
use Modules\Production\Repository\TransferTeamMemberRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Company\Repository\ProjectClassRepository;
use Modules\Company\Repository\PositionRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use DateTime;
use Carbon\Carbon;

class ProjectService
{
    private $repo;

    private $referenceRepo;

    private $employeeRepo;

    private $taskRepo;

    private $boardRepo;

    private $taskPicRepo;

    private $projectEquipmentRepo;

    private $projectTaskAttachmentRepo;

    private $projectPicRepository;

    private $projectTaskLogRepository;

    private $proofOfWorkRepo;

    private $taskWorktimeRepo;

    private $positionRepo;

    private $taskPicLogRepo;

    private $taskReviseHistoryRepo;

    private $transferTeamRepo;

    private $employeeTaskPoint;

    private $taskPicHistory;

    private $customItemRepo;

    private $projectClassRepo;

    private $projectVjRepo;

    private $inventoryItemRepo;

    private $geocoding;

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->geocoding = new \App\Services\Geocoding();

        $this->projectVjRepo = new \Modules\Production\Repository\ProjectVjRepository();

        $this->inventoryItemRepo = new \Modules\Inventory\Repository\InventoryItemRepository();

        $this->projectClassRepo = new ProjectClassRepository;

        $this->repo = new ProjectRepository;

        $this->referenceRepo = new ProjectReferenceRepository;

        $this->employeeRepo = new EmployeeRepository;

        $this->taskRepo = new ProjectTaskRepository;

        $this->boardRepo = new ProjectBoardRepository;

        $this->taskPicRepo = new ProjectTaskPicRepository;

        $this->projectEquipmentRepo = new ProjectEquipmentRepository;

        $this->projectTaskAttachmentRepo = new ProjectTaskAttachmentRepository;

        $this->projectPicRepository = new ProjectPersonInChargeRepository();

        $this->projectTaskLogRepository = new ProjectTaskLogRepository;

        $this->proofOfWorkRepo = new ProjectTaskProofOfWorkRepository;

        $this->taskWorktimeRepo = new ProjectTaskWorktimeRepository;

        $this->positionRepo = new PositionRepository;

        $this->taskPicLogRepo = new ProjectTaskPicLogRepository;

        $this->taskReviseHistoryRepo = new ProjectTaskReviseHistoryRepository;

        $this->transferTeamRepo = new TransferTeamMemberRepository;

        $this->employeeTaskPoint = new \Modules\Hrd\Repository\EmployeeTaskPointRepository;

        $this->taskPicHistory = new ProjectTaskPicHistoryRepository;

        $this->customItemRepo = new CustomInventoryRepository;
    }

    /**
     * Delete bulk data
     *
     * @param array<string> $ids
     *
     * @return array
     */
    public function bulkDelete(array $ids): array
    {
        DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $projectId = getIdFromUid($id, new \Modules\Production\Models\Project());

                // delete all related data with this project
                $this->deleteProjectReference($projectId);
                $this->deleteProjectTasks($projectId);
                $this->deleteProjectEquipmentRequest($projectId);
                $this->deleteProjectPic($projectId);
                $this->deleteProjectBoard($projectId);
            }

            $this->repo->bulkDelete($ids, 'uid');

            DB::commit();

            return generalResponse(
                __('global.successDeleteProject'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

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
    public function removeAllVJ(string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $this->projectVjRepo->delete(0, 'project_id = ' . getIdFromUid($projectUid, new \Modules\Production\Models\Project()));

            DB::commit();

            return generalResponse(
                __('global.allVjisRemoved'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function deleteProjectBoard(int $projectId)
    {
        $this->boardRepo->delete(0, 'project_id = ' . $projectId);
    }

    protected function deleteProjectPic(int $projectId)
    {
        $this->projectPicRepository->delete(0, 'project_id = ' . $projectId);
    }

    protected function deleteProjectEquipmentRequest(int $projectId)
    {
        $data = $this->projectEquipmentRepo->list('id,project_id', 'project_id = ' . $projectId);

        if (count($data) > 0) {
            // send notification
        }

        $this->projectEquipmentRepo->delete(0, 'project_id = ' . $projectId);
    }

    protected function deleteProjectTasks(int $projectId)
    {
        $data = $this->taskRepo->list('id,project_id', 'project_id = ' . $projectId);

        foreach ($data as $task) {
            // delete task attachments
            $taskAttachments = $this->projectTaskAttachmentRepo->list('id,media', 'project_task_id = ' . $task->id);

            if (count($taskAttachments) > 0) {
                foreach ($taskAttachments as $attachment) {
                    $path = storage_path("app/public/projects/{$projectId}/task/{$task->id}/{$attachment->media}");
                    deleteImage($path);

                    $this->projectTaskAttachmentRepo->delete($attachment->id);
                }
            }

            $this->taskRepo->delete($task->id);
        }
    }

    protected function deleteProjectReference(int $projectId)
    {
        $data = $this->referenceRepo->list('id,project_id,media_path', 'project_id = ' . $projectId);

        foreach ($data as $reference) {
            // delete image
            $path = storage_path("app/public/projects/references/{$projectId}/{$reference->media_path}");

            deleteImage($path);

            $this->referenceRepo->delete($reference->id);
        }
    }

    protected function buildFilterResult()
    {
        $filter = [];

        $search = request('search');

        if (count($search) > 0) {
            if (!empty($search['event_type'])) {
                $filter[] = [
                    'type' => 'name',
                    'text' => \App\Enums\Production\EventType::getLabel($search['event_type']),
                ];
            }
        }

        return $filter;
    }

    /**
     * Function to get selected employee task
     *
     * @param object $employee;
     *
     * @return array
     */
    protected function getProjectTaskRelationQuery(object $employee): array
    {
        if (auth()->user()->hasRole('entertainment')) { // just get event for entertainment. Look at transfer_team_members table
            $newWhereHas = [
                [
                    'relation' => 'teamTransfer',
                    'query' => "employee_id = " . auth()->user()->employee_id,
                ],
            ];
        } else { // get based on task
            $taskIds = $this->taskPicLogRepo->list('id,project_task_id', 'employee_id = ' . $employee->id);
            $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

            if (count($taskIds) > 0) {
                $queryNewHas = 'id IN (' . implode(',', $taskIds) . ')';
            } else {
                $queryNewHas = 'id = 0';
            }

            $newWhereHas = [
                [
                    'relation' => 'tasks',
                    'query' => $queryNewHas,
                ],
            ];
        }

        return $newWhereHas;
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
    ): array {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');

            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');
            $whereHas = [];

            $superAdminRole = getSettingByKey('super_user_role');
            $roles = auth()->user()->roles;
            $isSuperAdmin = $roles[0]->id == $superAdminRole ? true : false;

            $productionRoles = json_decode(getSettingByKey('production_staff_role'), true);
            $isProductionRole = in_array($roles[0]->id, $productionRoles);
            $isEntertainmentRole = auth()->user()->hasRole('entertainment');

            $projectManagerRole = getSettingByKey('project_manager_role');
            $isPMRole = $roles[0]->id == $projectManagerRole;

            // $filterResult = $this->buildFilterResult();

            if (request('filter_month') == 'true') {
                $startMonth = date('Y-m') . '-01';
                $endDateOfMonth = Carbon::createFromDate(
                    (int) date('Y'),
                    (int) date('m'),
                    1
                )
                    ->endOfMonth()
                    ->format('d');
                $endMonth = date('Y-m') . '-' . $endDateOfMonth;
                if (empty($where)) {
                    $where = "project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                } else {
                    $where .= " and project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                }
            }

            if (request('filter_today') == 'true') {
                $startDate = date('Y-m-d');
                if (empty($where)) {
                    $where = "project_date = '{$startDate}'";
                } else {
                    $where .= " and project_date = '{$startDate}'";
                }
            }

            if (
                ($search) &&
                (count($search) > 0)
            ) {
                if (!empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "lower(name) LIKE '%{$name}%'";
                } else if (!empty($search['name']) && !empty($where)) {
                    $name = $search['name'];
                    $where .= " AND lower(name) LIKE '%{$name}%'";
                }

                if (!empty($search['event_type']) && empty($where)) {
                    $eventType = strtolower($search['event_type']);
                    $where = "event_type = '{$eventType}'";
                } else if (!empty($search['event_type']) && !empty($where)) {
                    $eventType = $search['event_type'];
                    $where .= " AND event_type = '{$eventType}'";
                }

                if (!empty($search['classification']) && empty($where)) {
                    $classification = strtolower($search['classification']);
                    $where = "classification = '{$classification}'";
                } else if (!empty($search['classification']) && !empty($where)) {
                    $classification = $search['classification'];
                    $where .= " AND classification = '{$classification}'";
                }

                if (!empty($search['start_date']) && empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where = "project_date >= '{$start}'";
                } else if (!empty($search['start_date']) && !empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where .= " AND project_date >= '{$start}'";
                }

                if (!empty($search['end_date']) && empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where = "project_date <= '{$end}'";
                } else if (!empty($search['end_date']) && !empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where .= " AND project_date <= '{$end}'";
                }

                if (!empty($search['pic']) && empty($whereHas)) {
                    $pics = $search['pic'];
                    $pics = collect($pics)->map(function ($pic) {
                        $picId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee());
                        return $picId;
                    })->toArray();
                    $picData = implode(',', $pics);
                    $whereHas = [
                        [
                            'relation' => 'personInCharges',
                            'query' => "pic_id IN ({$picData})",
                        ],
                    ];
                    // if ($isSuperAdmin) {
                    // }
                }
            }

            $employeeId = $this->employeeRepo->show('dummy', 'id,boss_id', [], 'id = ' . auth()->user()->employee_id);

            // get project that only related to authorized user
            if ($isProductionRole || $isEntertainmentRole) {

                if ($employeeId) {
                    // $taskIds = $this->taskPicLogRepo->list('id,project_task_id', 'employee_id = ' . $employeeId->id);
                    // $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

                    // if (count($taskIds) > 0) {
                    //     $queryNewHas = 'id IN (' . implode(',', $taskIds) . ')';
                    // } else {
                    //     $queryNewHas = 'id = 0';
                    // }

                    // $newWhereHas = [
                    //     [
                    //         'relation' => 'tasks',
                    //         'query' => $queryNewHas,
                    //     ],
                    // ];

                    $newWhereHas = $this->getProjectTaskRelationQuery($employeeId);

                    $whereHas = array_merge($whereHas, $newWhereHas);
                }
            }

            $isAssistant = isAssistantPMRole();
            if ($isPMRole || $isAssistant) {
                if ($isAssistant) { // get assistant PM event and his boss event
                    // get boss project
                    $bossId = $employeeId->boss_id;

                    if ($bossId) {
                        $inWhere = "(";
                        $inWhere .= auth()->user()->employee_id;
                        $inWhere .= ",";
                        $inWhere .= $bossId;
                        $inWhere .= ")";

                        $whereHas[] = [
                            'relation' => 'personInCharges',
                            'query' => "pic_id IN {$inWhere}",
                        ];
                    }

                    // get assistant task
//                    $assistantTaskCondition = $this->getProjectTaskRelationQuery($employee);

                } else {
                    $whereHas[] = [
                        'relation' => 'personInCharges',
                        'query' => 'pic_id = ' . auth()->user()->employee_id,
                    ];
                }
            }

            $sorts = '';
            if (!empty(request('sortBy'))) {
                foreach (request('sortBy') as $sort) {
                    if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                        $sorts .= $sort['key'] . ' ' . $sort['order'] . ',';
                    }
                }

                $sorts = rtrim($sorts, ',');
            }

            // condition when user want to see all projects
            $isAllItems = false;
            if ($itemsPerPage < 0) {
                $page = 1;

                $allProjects = $this->repo->list(
                    'id',
                    $where,
                    $relation,
                    $whereHas
                )->count();

                $itemsPerPage = $allProjects;
                $isAllItems = true;
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas,
                $sorts
            );
            $totalData = $this->repo->list('id', $where, [], $whereHas)->count();

            $eventTypes = \App\Enums\Production\EventType::cases();
            $classes = \App\Enums\Production\Classification::cases();
            $statusses = \App\Enums\Production\ProjectStatus::cases();

            $paginated = collect((object) $paginated)->map(function ($item) use ($eventTypes, $classes, $statusses) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name . '(' . $pic->employee->employee_id . ')',
                    ];
                })->pluck('name')->values()->toArray();

                $picEid = collect($item->personInCharges)->pluck('employee.employee_id')->toArray();

                $marketing = $item->marketing ? $item->marketing->name : '-';

                $marketingData = collect($item->marketings)->pluck('marketing.name')->toArray();
                $marketing = $item->marketings[0]->marketing->name;
                if ($item->marketings->count() > 1) {
                    $marketing .= ", and +" . $item->marketings->count() - 1 . " more";
                }

                $eventType = '-';
                foreach ($eventTypes as $et) {
                    if ($et->value == $item->event_type) {
                        $eventType = $et->label();
                    }
                }

                $status = '-';
                $statusColor = '';
                if ($item->status) {
                    foreach ($statusses as $statusData) {
                        if ($statusData->value == $item->status) {
                            $status = $statusData->label();
                            $statusColor = $statusData->color();
                        }
                    }
                } else {
                    $status = __('global.undetermined');
                    $statusColor = 'grey-lighten-1';
                }

                $eventClass = '-';
                $eventClassColor = null;
                foreach ($classes as $class) {
                    if ($class->value == $item->classification) {
                        $eventClass = $class->label();
                        $eventClassColor = $class->color();
                    }
                }

                $vj = '-';

                if ($item->vjs->count() > 0) {
                    $vj = implode(',', collect($item->vjs)->pluck('employee.nickname')->toArray());
                }

                $needReturnEquipment = false;
                if ($item->status == \App\Enums\production\ProjectStatus::Completed->value && $item->equipments->count() > 0) {
                    $needReturnEquipment = true;
                }
                if ($item->equipments->count() > 0) {
                    if ($item->equipments[0]->is_returned) {
                        $needReturnEquipment = false;
                    }
                }

                return [
                    'uid' => $item->uid,
                    'id' => $item->id,
                    'marketing' => $marketing,
                    'pic' => count($pics) > 0  ? implode(', ', $pics) : __('global.undetermined'),
                    'no_pic' => count($pics) == 0 ? true : false,
                    'pic_eid' => $picEid,
                    'name' => $item->name,
                    'project_date' => date('d F Y', strtotime($item->project_date)),
                    'venue' => $item->venue,
                    'event_type' => $eventType,
                    'led_area' => $item->led_area,
                    'event_class' => $item->projectClass->name,
                    'event_class_color' => $item->projectClass->color,
                    'status' => $status,
                    'status_color' => $statusColor,
                    'status_raw' => $item->status,
                    'project_is_complete' => $item->status == \App\Enums\production\ProjectStatus::Completed->value,
                    'vj' => $vj,
                    'have_vj' => $item->vjs->count() > 0 ? true : false,
                    'is_final_check' => $item->status == \App\Enums\Production\ProjectStatus::ReadyToGo->value || $item->status == \App\Enums\Production\ProjectStatus::Completed->value ? true : false,
                    'need_return_equipment' => $needReturnEquipment,
                ];
            });

            return generalResponse(
                'Success',
                false,
                [
                    'sort' => $sorts,
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'itemPerPage' => (int) $itemsPerPage,
                    'isAllItems' => $isAllItems
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all board based related logged user project
     *
     * @return array
     */
    public function getAllBoards(): array
    {
        return [];
    }

    /**
     * Get all project list for scheduler (To assign a PIC to selected project)
     *
     * Default filter is:
     * - Get -7 days and +7 days based on selected project date
     * - Sort ASC by project date
     *
     */
    public function getAllSchedulerProjects(string $projectUid)
    {
        $where = '';
        $filterData = [];

        $project = $this->repo->show($projectUid, 'project_date,latitude,longitude');

        if (request('start_date')) {
            $startDate = date('Y-m-d', strtotime(request('start_date')));
        } else { // set based on selected project date
            $startDate = date('Y-m-d', strtotime('-7 days', strtotime($project->project_date)));
        }

        if (request('filter_month')) {
            $startDate = request('filter_year') . '-' . request('filter_month') . '-01';
        }

        if (empty($where)) {
            $where = "project_date >= '{$startDate}'";
        } else {
            $where .= " and project_date >= '{$startDate}'";
        }

        $filterData['date']['start_date'] = date('Y, F d', strtotime($startDate));
        $filterData['date']['enable'] = true;

        if (request('end_date')) {
            $endDate = date('Y-m-d', strtotime(request('end_date')));
        } else { // set based on selected project date
            $endDate = date('Y-m-d', strtotime('+7 days', strtotime($project->project_date)));
        }

        if (request('filter_month')) {
            $endCarbon = \Carbon\Carbon::parse(request('filter_year') . '-' . request('filter_month') . '-01');
            $endDate = request('filter_year') . '-' . request('filter_month') . '-' . $endCarbon->endOfMonth()->format('d');
        }

        if (empty($where)) {
            $where = "project_date <= '{$endDate}'";
        } else {
            $where .= " and project_date <= '{$endDate}'";
        }

        $filterData['date']['end_date'] = date('Y, F d', strtotime($endDate));
        $filterData['date']['enable'] = true;

        // by venue
        $coordinate = [];
        $orderBy = 'project_date ASC';
        if (request('filter_venue')) {
            $coordinate = [$project->latitude, $project->longitude, $project->latitude];
            $orderBy = 'distance ASC, project_date ASC';
        }

        $data = $this->repo->list(
            "id,uid,name,project_date,venue,event_type,collaboration,status,led_area,led_detail,project_class_id,classification,city_name,(
                       6371 * acos(
                           cos(radians({$project->latitude})) * cos(radians(latitude)) *
                           cos(radians(longitude) - radians({$project->longitude})) +
                           sin(radians({$project->latitude})) * sin(radians(latitude))
                       )
                   ) AS distance",
            $where,
            [
                'projectClass:id,name,color',
                'personInCharges:id,project_id,pic_id',
                'personInCharges.employee:id,nickname',
            ],
            [],
            $orderBy,
            0,
            $coordinate
        );

        $statusses = \App\Enums\Production\ProjectStatus::cases();
        $eventTypes = \App\Enums\Production\EventType::cases();

        $output = collect((object) $data)->map(function ($item) use ($projectUid, $statusses, $eventTypes) {
            $status = __('global.undetermined');
            $statusColor = '';
            foreach ($statusses as $statusData) {
                if ($item->status == $statusData->value) {
                    $status = $statusData->label();
                    $statusColor = $statusData->color();
                }
            }

            foreach ($eventTypes as $type) {
                if ($item->event_type == $type->value) {
                    $eventType = $type->label();
                }
            }

            return [
                'id' => $item->uid,
                'project' => $item->name,
                'status' => $status,
                'event_type' => $eventType,
                'status_color' => $statusColor,
                'name' => count($item->personInCharges) > 0 ? $item->personInCharges[0]->employee->nickname : '-',
                'event_class_color' => $item->projectClass->color,
                'event_class' => $item->projectClass->name,
                'project_date' => $item->project_date,
                'date' => date('Y, F d', strtotime($item->project_date)),
                'selected_project' => $projectUid == $item->uid ? true : false,
                'led_area' => $item->led_area . "m <sup>2</sup>",
                'collaboration' => $item->collaboration,
                'venue' => $item->venue . ', ' . $item->city_name,
                'distance' => $item->distance,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            [
                'projects' => $output,
                'filter' => $filterData,
                'req' => $where,
            ]
        );
    }

    /**
     * Get all project based on user role
     *
     * @return array
     */
    public function getAllProjects(): array
    {
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $employeeId = $user->employee_id;
        $now = date('Y-m-d');
        $isSuperUserRole = isSuperUserRole();

        $whereHas = [];
        if (!$isSuperUserRole) {
            $whereHas[] = [
                'relation' => 'personInCharges',
                'query' => 'pic_id = ' . $employeeId,
            ];
        }

        $data = $this->repo->list(
            'id,uid as value,name as title',
            "project_date > '{$now}'",
            [],
            $whereHas
        );

        $data = collect((object) $data)->map(function ($project) {
            return [
                'title' => $project->title,
                'value' => $project->value,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data,
        );
    }

    /**
     * Get Event Types list
     *
     * @return array
     */
    public function getEventTypes()
    {
        $data = \App\Enums\Production\EventType::cases();

        $out = [];
        foreach ($data as $d) {
            $out[] = [
                'value' => $d->value,
                'title' => ucfirst($d->value),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }

    /**
     * Get Classification list
     *
     * @return array
     */
    public function getClassList()
    {
        $data = \App\Enums\Production\Classification::cases();

        $out = [];
        foreach ($data as $d) {
            $out[] = [
                'value' => $d->value,
                'title' => $d->label(),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }

    public function datatable()
    {
        //
    }

    /**
     * Formating references response
     *
     * @param object $references
     * @return array
     */
    protected function formatingReferenceFiles(object $references, int $projectId)
    {
        $group = [];
        $fileDocumentType = ['doc', 'docx', 'xlsx', 'pdf'];

        foreach ($references as $key => $reference) {
            if ($reference->type == 'link') {
                $group['link'][] = [
                    'media_path' => 'link',
                    'link' => $reference->media_path,
                    'id' => $reference->id,
                ];
            } else if (in_array($reference->type, $fileDocumentType)) {
                $group['pdf'][] = [
                    'id' => $reference->id,
                    'name' => 'document',
                    'media_path' => asset('storage/projects/references/' . $projectId) . '/' . $reference->media_path,
                    'type' => $reference->type,
                ];
            } else {
                $group['files'][] = [
                    'id' => $reference->id,
                    'media_path' => $reference->media_path_text,
                    'name' => $reference->name,
                    'type' => $reference->type,
                ];
            }
        }

        return $group;
    }

    /**
     * Get Team members of selected project
     *
     * @param \Illuminate\Database\Eloquent\Collection $project
     * @return array
     */
    protected function getProjectTeams($project): array
    {
        $where = '';
        $pics = [];
        $teams = [];
        $picIds = [];
        $picUids = [];

        if ($productionPositions = json_decode(getSettingByKey('position_as_production'), true)) {
            $productionPositions = collect($productionPositions)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\Position());
            })->toArray();
        }

        foreach ($project->personInCharges as $key => $pic) {
            $pics[] = $pic->employee->name . '(' . $pic->employee->employee_id . ')';
            $picIds[] = $pic->pic_id;
            $picUids[] = $pic->employee->uid;

            // check persion in charge role
            // if Assistant, then get teams based his team and his boss team
            $userPerson = \App\Models\User::selectRaw('id')->where('employee_id', $pic->employee->id)
                ->first();
            if ($userPerson->hasRole('assistant manager')) {
                // get boss team
                if ($pic->employee->boss_id) {
                    array_push($picIds, $pic->employee->boss_id);
                    array_push($picUids, $pic->employee->uid);
                }
            }
        }

        $picIds = array_values(array_unique($picIds));
        $picUids = array_values(array_unique($picUids));

        // get special position that will be append on each project manager team members
        $specialPosition = getSettingByKey('special_production_position');
        $specialEmployee = [];
        $specialIds = [];
        if ($specialPosition) {
            $specialPosition = getIdFromUid($specialPosition, new \Modules\Company\Models\Position());

            $specialEmployee = $this->employeeRepo->list('id,uid,name,nickname,email,position_id', 'position_id = ' . $specialPosition, ['position:id,name'])->toArray();

            $specialEmployee = collect($specialEmployee)->map(function ($employee) {
                $employee['loan'] = false;

                return $employee;
            })->toArray();

            $specialIds = collect($specialEmployee)->pluck('id')->toArray();
        }

        // get another teams from approved transfer team
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $superUserRole = getSettingByKey('super_user_role');
        $transferCondition = 'status = ' . \App\Enums\Production\TransferTeamStatus::Approved->value . ' and project_id = ' . $project->id . " and is_entertainment = 0";
        if ($roleId != $superUserRole) {
            $transferCondition .= ' and requested_by = ' . $user->employee_id;
        }

        if (count($picIds) > 0) {
            $picId = implode(',', $picIds);
            $employeeCondition = "boss_id IN ($picId)";
        } else {
            $employeeCondition = "boss_id IN (0)";
        }

        $employeeCondition .= " and status != " . \App\Enums\Employee\Status::Inactive->value;

        if (count($specialIds) > 0) {
            $specialId = implode(',', $specialIds);
            $transferCondition .= " and employee_id NOT IN ($specialId)";
            $employeeCondition .= " and id NOT IN ($specialId)";
        }

        $transfers = $this->transferTeamRepo->list('id,employee_id', $transferCondition, ['employee:id,name,nickname,uid,email,employee_id,position_id', 'employee.position:id,name']);

        $transfers = collect((object) $transfers)->map(function ($transfer) {
            return [
                'id' => $transfer->employee->id,
                'uid' => $transfer->employee->uid,
                'email' => $transfer->employee->email,
                'nickname' => $transfer->employee->nickname,
                'name' => $transfer->employee->name,
                'position' => $transfer->employee->position,
                'loan' => true,
                'last_update' => '-',
                'current_task' => '-',
                'image' => asset('images/user.png'),
            ];
        })->toArray();

        if ($productionPositions) {
            $productionPositions = implode(',', $productionPositions);
            $employeeCondition .= " and position_id in ({$productionPositions})";
        }


        $teams = $this->employeeRepo->list(
            'id,uid,name,email,nickname,position_id',
            $employeeCondition,
            ['position:id,name'],
            '',
            '',
            [
                [
                    'relation' => 'position',
                    'query' => "(LOWER(name) not like '%project manager%')",
                ],
                [
                    'relation' => 'position.division',
                    'query' => "LOWER(name) like '%production%'"
                ]
            ]
        );

        if (count($teams) > 0) {
            $teams = collect($teams)->map(function ($team) {
                $team['last_update'] = '-';
                $team['current_task'] = '-';
                $team['loan'] = false;
                $team['image'] = asset('images/user.png');

                return $team;
            })->toArray();

            $teams = collect($teams)->merge($transfers)->toArray();

            $teams = collect($teams)->merge($specialEmployee)->toArray();
        }

        // get task on selected project
        $outputTeam = [];
        foreach ($teams as $key => $team) {
            $task = $this->taskPicHistory->list('id', 'project_id = ' . $project->id . ' and employee_id = ' . $team['id'])->count();

            $outputTeam[$key] = $team;
            $outputTeam[$key]['total_task'] = $task;
        }

        // get entertainment teams
        $entertain = $this->transferTeamRepo->list(
            'id,employee_id,requested_by,alternative_employee_id',
            "project_id = " . $project->id . " and is_entertainment = 1 and employee_id is not null",
            ['employee:id,uid,name,email,position_id', 'employee.position:id,name']
        );

        $outputEntertain = collect((object) $entertain)->map(function ($item) {
            return [
                'id' => $item->employee->id,
                'uid' => $item->employee->uid,
                'name' => $item->employee->name,
                'total_task' => 0,
                'loan' => true,
                'position' => $item->employee->position,
            ];
        })->toArray();

        return [
            'pics' => $pics,
            'teams' => $outputTeam,
            'picUids' => $picUids,
            'entertain' => $outputEntertain,
        ];
    }

    protected function defaultTaskRelation()
    {
        return [
            'revises',
            'project:id,uid,status',
            'pics:id,project_task_id,employee_id,status',
            'pics.employee:id,name,email,uid',
            'medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
            'taskLink:id,project_id,project_task_id,media,display_name,related_task_id,type',
            'proofOfWorks',
            'logs',
            'board',
            'times:id,project_task_id,employee_id,work_type,time_added',
            'times.employee:id,uid,name'
        ];
    }

    protected function formattedDetailTask(string $taskUid, string $where = '')
    {
        if (empty($where)) {
            $taskDetail = $this->taskRepo->show($taskUid, '*', $this->defaultTaskRelation());
        } else {
            $taskDetail = $this->taskRepo->show('dummy', '*', $this->defaultTaskRelation(), $where);
        }

        // format time tracker
        $task = $this->formatSingleTaskPermission($taskDetail);

        return $task;
    }

    protected function formatTimeTracker(array $times)
    {
        // chunk each 3 item
        $chunks = array_chunk($times, 3);

        return $chunks;
    }

    protected function formattedBoards(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        $employeeId = auth()->user()->employee_id ?? 0;
        $superUserRole = isSuperUserRole();

        $data = $this->boardRepo->list('id,project_id,name,sort,based_board_id', 'project_id = ' . $projectId, [
            'tasks',
            'tasks.revises',
            'tasks.project:id,uid,status',
            'tasks.proofOfWorks',
            'tasks.logs',
            'tasks.board',
            'tasks.pics:id,project_task_id,employee_id,status',
            'tasks.pics.employee:id,name,email,uid',
            'tasks.medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
            'tasks.taskLink:id,project_id,project_task_id,media,display_name,related_task_id,type',
            'tasks.times:id,project_task_id,employee_id,work_type,time_added',
            'tasks.times.employee:id,uid,name'
        ]);

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $isDirector = isDirector();

        $out = [];

        foreach ($data as $keyBoard => $board) {
            $out[$keyBoard] = $board;

            $out[$keyBoard]['is_project_pic'] = $isProjectPic;

            $tasks = $board->tasks;

            $outputTask = [];
            foreach ($tasks as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                unset($outputTask[$keyTask]['time_tracker']);

                // check if task already active or not, if not show activating button
                $isActive = false;

                if ($task->project->status != \App\Enums\Production\ProjectStatus::Draft->value) {
                    foreach ($task->pics as $pic) {
                        if ($pic->employee_id == $employeeId) {

                            $isActive = $pic->is_active;

                            break;
                        }
                    }
                }

                $picIds = collect($task->pics)->pluck('employee_id')->toArray();

                $needUserApproval = false;
                if ($task->status == \App\Enums\Production\TaskStatus::WaitingApproval->value && (in_array($employeeId, $picIds) || $isDirector || $isProjectPic)) {
                    $needUserApproval = true;
                }

                $outputTask[$keyTask]['need_user_approval'] = $needUserApproval;

                // override is_active where task status is ON PROGRESS
                if ($task->status == \App\Enums\Production\TaskStatus::OnProgress->value) {
                    $isActive = true;
                }

                $outputTask[$keyTask]['stop_action'] = $task->project->status == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task->status == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker($task->times->toArray());

                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $isDirector;

                if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
                    $isActive = true;
                }

                // check the ownership of task

                $haveTaskAccess = true;
                if (!$superUserRole && !$isProjectPic && !$isDirector && !isAssistantPMRole()) {
                    if (!in_array($employeeId, $picIds)) {
                        $haveTaskAccess = false;
                    }
                }

                $havePermissionToMoveBoard = false;
                if ($superUserRole || $isProjectPic || $isDirector || auth()->user()->hasPermissionTo('move_board', 'sanctum')) {
                    $havePermissionToMoveBoard = true;
                }

                $outputTask[$keyTask]['have_permission_to_move_board'] = $havePermissionToMoveBoard;

                if (
                    (
                        in_array($employeeId, $picIds) ||
                        $superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()
                    ) &&
                    $task->project->status == \App\Enums\Production\ProjectStatus::OnGoing->value &&
                    ($task->status == \App\Enums\Production\TaskStatus::OnProgress->value ||
                    $task->status == \App\Enums\Production\TaskStatus::Revise->value)
                ) {
                    $outputTask[$keyTask]['action_to_complete_task'] = true;
                } else {
                    $outputTask[$keyTask]['action_to_complete_task'] = false;
                }

                $outputTask[$keyTask]['has_task_access'] = $haveTaskAccess;

                $outputTask[$keyTask]['is_active'] = $isActive;
            }

            $out[$keyBoard]['tasks'] = $outputTask;
        }

        return $out;
    }

    protected function formattedBasicData(string $projectUid)
    {
        $project = $this->repo->show($projectUid, 'id,uid,event_type,classification,name,project_date,project_class_id', ['projectClass:id,name,maximal_point']);

        $projectTeams = $this->getProjectTeams($project);
        $teams = $projectTeams['teams'];
        $pics = $projectTeams['pics'];

        $eventTypes = \App\Enums\Production\EventType::cases();
        $classes = \App\Enums\Production\Classification::cases();

        $eventType = '-';
        foreach ($eventTypes as $et) {
            if ($et->value == $project->event_type) {
                $eventType = $et->label();
            }
        }

        return [
            'pics' => $pics,
            'teams' => $teams,
            'name' => $project->name,
            'project_date' => date('d F Y', strtotime($project->project_date)),
            'event_type' => $eventType,
            'event_type_raw' => $project->event_type,
            'event_class_raw' => $project->project_class_id,
            'event_class' => $project->classification,
            'event_class_color' => '',
            'project_maximal_point' => $project->projectClass->maximal_point,
        ];
    }

    protected function formattedProjectProgress($tasks, int $projectId)
    {
        $grouping = [];
        foreach ($tasks as $task) {
            $grouping[$task['project_board_id']][] = $task;
        }

        $groupData = collect($tasks)->groupBy('project_board_id')->toArray();

        $projectBoards = $this->boardRepo->list('id,project_id,name,based_board_id', 'project_id = ' . $projectId);

        $output = [];
        foreach ($projectBoards as $key => $board) {
            $output[$key] = $board;
            $output[$key]['total'] = 0;
            $output[$key]['completed'] = 0;
            $output[$key]['percentage'] = 0;
            $output[$key]['text'] = $board->name;

            if (count($groupData) > 0) {
                foreach ($groupData as $boardId => $value) {
                    if ($boardId == $board->id) {
                        $total = count($value);
                        $completed = collect($value)->where('status', '=', \App\Enums\Production\TaskStatus::Completed->value)->count();;

                        $output[$key]['total'] = $total;
                        $output[$key]['completed'] = $completed;

                        $percentage = ceil($completed / $total * 100);
                        $output[$key]['percentage'] = $percentage;
                    }
                }
            }
        }

        return $output;
    }

    public function formattedEquipments(int $projectId)
    {
        $equipments = $this->projectEquipmentRepo->list('*', 'project_id = ' . $projectId, [
            'inventory:id,name',
            'inventory.image'
        ]);

        $equipments = collect((object) $equipments)->map(function ($item) {
            $canTakeAction = true;
            if (
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ||
                $item->is_checked_pic ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Decline->value ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Return->value ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::CompleteAndNotReturn
            ) {
                $canTakeAction = false;
            }

            $item['is_cancel'] = $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ? true : false;

            $item['can_take_action'] = $canTakeAction;

            return $item;
        })->all();

        return $equipments;
    }

    protected function getTaskTimeTracker(int $taskId)
    {
    }

    public function updateDetailProjectFromOtherService(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $currentData = getCache('detailProject' . $projectId);

        if ($currentData) {
            $boards = $this->formattedBoards($projectUid);
            $currentData['boards'] = $boards;

            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            $currentData = $this->formatTasksPermission($currentData, $projectId);
        }
    }

    /**
     * Get detail data
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            // clearCache('detailProject' . getIdFromUid($uid, new \Modules\Production\Models\Project()));
            $projectId = getIdFromUid($uid, new \Modules\Production\Models\Project());
            $output = getCache('detailProject' . $projectId);

            if (!$output) {
                $data = $this->repo->show($uid, '*', [
                    'marketing:id,name,employee_id',
                    'personInCharges:id,pic_id,project_id',
                    'personInCharges.employee:id,name,employee_id,uid,boss_id',
                    'references:id,project_id,media_path,name,type',
                    'equipments.inventory:id,name',
                    'equipments.inventory.image',
                    'marketings:id,marketing_id,project_id',
                    'marketings.marketing:id,name',
                    'country:id,name',
                    'state:id,name',
                    'city:id,name',
                    'projectClass:id,name,maximal_point'
                ]);

                $progress = $this->formattedProjectProgress($data->tasks, $projectId);

                $eventTypes = \App\Enums\Production\EventType::cases();
                $classes = \App\Enums\Production\Classification::cases();

                // get teams
                $projectTeams = $this->getProjectTeams($data);
                $teams = $projectTeams['teams'];
                $pics = $projectTeams['pics'];
                $picIds = $projectTeams['picUids'];

                $marketing = $data->marketing ? $data->marketing->name : '-';

                $eventType = '-';
                foreach ($eventTypes as $et) {
                    if ($et->value == $data->event_type) {
                        $eventType = $et->label();
                    }
                }

                $eventClass = '-';
                $eventClassColor = null;
                foreach ($classes as $class) {
                    if ($class->value == $data->classification) {
                        $eventClass = $class->label();
                        $eventClassColor = $class->color();
                    }
                }

                $boardsData = $this->formattedBoards($uid);

                $equipments = $this->formattedEquipments($data->id);

                // days to go
                $projectData = new DateTime($data->project_date);
                $diff = date_diff($projectData, new DateTime('now'));
                $daysToGo = $diff->d;

                // check time to upload showreels
                $allowedUploadShowreels = true;
                $currentTasks = [];
                foreach ($boardsData as $board) {
                    foreach ($board['tasks'] as $task) {
                        $currentTasks[] = $task;
                    }
                }
                $currentTaskStatusses = collect($currentTasks)->pluck('status')->count();
                $completedStatus = collect($currentTasks)->filter(function ($filter) {
                    return $filter['status'] == \App\Enums\Production\TaskStatus::Completed->value;
                })->values()->count();
                // if ($currentTaskStatusses == $completedStatus) {
                //     $allowedUploadShowreels = true;
                // }

                $output = [
                    'id' => $data->id,
                    'allowed_upload_showreels' => $allowedUploadShowreels,
                    'uid' => $data->uid,
                    'name' => $data->name,
                    'country_id' => $data->country_id,
                    'state_id' => $data->state_id,
                    'city_id' => $data->city_id,
                    'feedback' => $data->feedback,
                    'event_type' => $eventType,
                    'event_type_raw' => $data->event_type,
                    'event_class_raw' => $data->project_class_id,
                    'event_class' => $data->projectClass->name,
                    'event_class_color' => $eventClassColor,
                    'project_date' => date('d F Y', strtotime($data->project_date)),
                    'days_to_go' => $daysToGo,
                    'venue' => $data->venue,
                    'city_name' => $data->city_name,
                    'marketing' => $marketing,
                    'pic' => implode(', ', $pics),
                    'pic_ids' => $picIds,
                    'collaboration' => $data->collaboration,
                    'note' => $data->note ?? '-',
                    'led_area' => $data->led_area,
                    'led_detail' => json_decode($data->led_detail, true),
                    'client_portal' => $data->client_portal,
                    'status' => $data->status_text,
                    'status_color' => $data->status_color,
                    'status_raw' => $data->status,
                    'references' => $this->formatingReferenceFiles($data->references, $data->id),
                    'boards' => $boardsData,
                    'teams' => $teams,
                    'task_type' => $data->task_type,
                    'task_type_text' => $data->task_type_text,
                    'task_type_color' => $data->task_type_color,
                    'progress' => $progress,
                    'equipments' => $equipments,
                    'showreels' => $data->showreels_path,
                    'person_in_charges' => $data->personInCharges,
                    'project_maximal_point' => $data->projectClass->maximal_point,
                ];

                storeCache('detailProject' . $data->id, $output);
            }

            $output = $this->formatTasksPermission($output, $projectId);

            $serviceEncrypt = new \App\Services\EncryptionService();
            $encrypts = $serviceEncrypt->encrypt(json_encode($output), env('SALT_KEY'));

            $outputData = [
                'data' => $output,
                'detail' => $encrypts,
            ];

            return generalResponse(
                'success',
                false,
                $outputData
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function formatSingleTaskPermission($task)
    {
        $employeeId = auth()->user()->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = ' . $task['project_id']);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $task['is_project_pic'] = $isProjectPic;

        $task['is_director'] = $isDirector;

        $task['need_approval_pm'] = $isProjectPic && $task['status'] == \App\Enums\Production\TaskStatus::CheckByPm->value;

        $task['stop_action'] = $task['project']->status == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

        // check if task already active or not, if not show activating button
        $isActive = false;
        foreach ($task['pics'] as $pic) {
            if ($pic['employee_id'] == $employeeId) {
                $isActive = $pic['is_active'];
            }
        }

        // override is_active where task status is ON PROGRESS
        if ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value) {
            $isActive = true;
        }

        $task['time_tracker'] = $this->formatTimeTracker(collect($task['times'])->toArray());

        // check the ownership of task
        $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
        $haveTaskAccess = true;
        if (!$superUserRole && !$isProjectPic || !$isDirector) {
            if (!in_array($employeeId, $picIds)) {
                $haveTaskAccess = false;
            }
        }
        $task['picIds'] = $picIds;

        if (
            (
                in_array($employeeId, $picIds) ||
                $superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()
            ) &&
            $task['project']->status == \App\Enums\Production\ProjectStatus::OnGoing->value &&
            ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value ||
            $task['status'] == \App\Enums\Production\TaskStatus::Revise->value)
        ) {
            $task['action_to_complete_task'] = true;
        } else {
            $task['action_to_complete_task'] = false;
        }

        if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
            $isActive = true;
            $haveTaskAccess = true;
        }

        $havePermissionToMoveBoard = false;
        if ($superUserRole || $isProjectPic || $isDirector || auth()->user()->hasPermissionTo('move_board', 'sanctum')) {
            $havePermissionToMoveBoard = true;
        }

        $task['have_permission_to_move_board'] = $havePermissionToMoveBoard;

        $task['is_active'] = $isActive;

        $task['has_task_access'] = $haveTaskAccess;

        return $task;
    }

    // TODO: Need to develop
    protected function getEmployeeWorkingTimeReport()
    {

    }

    public function getProjectStatistic($project)
    {
        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $teams = $project['teams'];

        $output = [];
        $resp = [];

        $checkPoint = $this->employeeTaskPoint->list('*', 'project_id = ' . $projectId);

        if ($checkPoint->count() > 0) {
            foreach ($teams as $key => $team) {
                $output[$key] = $team;

                // get points
                $point = $this->employeeTaskPoint->show('dummy', '*', [], 'employee_id = ' . $team['id'] . ' and project_id = ' . $projectId);

                $output[$key]['points'] = [
                    'total_task' => $point ? $point->total_task : 0,
                    'point' => $point ? $point->point : 0,
                    'additional_point' => $point ? $point->additional_point : 0,
                    'total_point' => $point ? $point->additional_point + $point->point : 0,
                ];
            }

            $output = collect($output)->map(function ($item) {
                $name = $item['name'];
                $expName = explode(' ', $name);

                return [
                    'uid' => $item['uid'],
                    'name' => $item['name'],
                    'nickname' => $expName[0],
                    'total_task' => $item['points']['total_task'],
                    'total_point' => $item['points']['total_point'],
                    'point' => $item['points']['point'],
                    'additional_point' => $item['points']['additional_point'],
                ];
            })->toArray();

            if (count($output) > 0) {
                $firstLine = count($output) > 2 ? array_splice($output, 0, 2) : $output;

                $moreLine = count($output) > 2 ? array_splice($output, 2) : [];

                $resp = [
                    'first_line' => $firstLine,
                    'more_line' => $moreLine,
                ];
            }
        }

        return $resp;
    }

    protected function formatTasksPermission($project, int $projectId)
    {
        $output = [];

        $project['report'] = $this->getProjectStatistic($project);

        $project['feedback_given'] = count($project['report']) > 0 ? true : false;

        $user = auth()->user();
        $employeeId = $user->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

        // get teams
        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $personInCharges = $this->projectPicRepository->list('*', 'project_id = ' . $projectId, ['employee:id,uid,name,email,nickname,boss_id,position_id']);
        $project['personInCharges'] = $personInCharges;
        $projectTeams = $this->getProjectTeams((object) $project);

        $entertainTeam = $projectTeams['entertain'];

        $teams = $projectTeams['teams'];
        if (isset($project['personInCharges'])) {
            unset($project['personInCharges']);
        }

        // define permission to complete project
        $nowTime = new DateTime('now');
        $projectDate = new DateTime(date('Y-m-d', strtotime($project['project_date'])));
        $diff = date_diff($nowTime, $projectDate);

        $project['is_time_to_complete_project'] = false;
        if (
            (
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::OnGoing->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value ||
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::ReadyToGo->value
            ) &&
            $diff->invert > 0
        ) {
            $project['is_time_to_complete_project'] = true;
        }

        $project['project_is_complete'] = $project['status_raw'] == \App\Enums\Production\ProjectStatus::Completed->value ? true : false;

        // define show alert coming soon
        $now = time(); // or your date as well
        $projectDateTime = strtotime($project['project_date']);
        $datediff = $projectDateTime - $now;
        $d = round($datediff / (60 * 60 * 24));
        $project['show_alert_coming_soon'] = false;

        $targetRaiseDeadlineAlert = getSettingByKey('days_to_raise_deadline_alert') ?? 2;
        if (
            (
                $d <= $targetRaiseDeadlineAlert &&
                $d >= 0
            ) &&
            $project['status_raw'] != \App\Enums\Production\ProjectStatus::Completed->value
        ) {
            $project['show_alert_coming_soon'] = true;
        }

        $project['show_alert_event_is_done'] = $d < 0 ? true : false;

        $project['teams'] = $teams;

        $project['entertain_teams'] = $entertainTeam;

        $project['is_super_user'] = $superUserRole;

        $project['is_director'] = $user->is_director;

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $project['is_project_pic'] = $isProjectPic;

        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $projectTasks = $this->taskRepo->list('*', 'project_id = ' . $projectId, ['board']);

        $project['progress'] = $this->formattedProjectProgress($projectTasks, $projectId);

        foreach ($project['boards'] as $keyBoard => $board) {
            $output[$keyBoard] = $board;

            $outputTask = [];

            foreach ($board['tasks'] as $keyTask => $task) {
                $outputTask[$keyTask] = $task;

                // stop action when project status is DRAFT
                $outputTask[$keyTask]['stop_action'] = $project['status'] == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

                // check if task already active or not, if not show activating button

                if (in_array($employeeId, collect($task['pics'])->pluck('employee_id')->toArray())) {
                    $search = collect($task['pics'])->filter(function ($filterEmployee) use ($employeeId) {
                        return $filterEmployee['employee_id'] == $employeeId;
                    })->values();
                    $search = $search->toArray()[0];

                    $outputTask[$keyTask]['is_active'] = $search['is_active'];
                }

                // override is_active if task status is on already ON PROGRESS
                if ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value) {
                    $outputTask[$keyTask]['is_active'] = true;
                }

                // foreach ($task['pics'] as $pic) {
                //     if ($pic['employee_id'] == $employeeId) {
                //         logging('TESTING PIC', $pic);

                //         $outputTask[$keyTask]['is_active'] = $pic['is_active'];

                //         break;
                //     } else {
                //         $outputTask[$keyTask]['is_active'] = false;
                //     }
                // }

                // push 'is_project_pic' to task collection
                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $isDirector;

                // define task need approval from project manager or not
                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task['status'] == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker(collect($task['times'])->toArray());

                // check the ownership of task
                $picIds = collect($task['pics'])->pluck('employee_id')->toArray();
                $haveTaskAccess = true;
                if (!$superUserRole && !$isProjectPic && !$isDirector && !isAssistantPMRole()) {
                    if (!in_array($employeeId, $picIds)) { // where logged user is not a in task pic except the project manager
                        $haveTaskAccess = false;
                    }
                }

                $needUserApproval = false;
                if (
                    $task['status'] == \App\Enums\Production\TaskStatus::WaitingApproval->value &&
                    (in_array($employeeId, $picIds) || $isDirector || $isProjectPic)
                ) {
                    $needUserApproval = true;
                }
                $outputTask[$keyTask]['need_user_approval'] = $needUserApproval;

                if (
                    (
                        in_array($employeeId, $picIds) ||
                        $superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()
                    ) &&
                    $project['status_raw'] == \App\Enums\Production\ProjectStatus::OnGoing->value &&
                    ($task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value ||
                    $task['status'] == \App\Enums\Production\TaskStatus::Revise->value)
                ) {
                    $outputTask[$keyTask]['action_to_complete_task'] = true;
                } else {
                    $outputTask[$keyTask]['action_to_complete_task'] = false;
                }

                $outputTask[$keyTask]['picIds'] = $picIds;
                $outputTask[$keyTask]['has_task_access'] = $haveTaskAccess;

                $havePermissionToMoveBoard = false;
                if ($superUserRole || $isProjectPic || $isDirector || auth()->user()->hasPermissionTo('move_board', 'sanctum')) {
                    $havePermissionToMoveBoard = true;
                }

                $outputTask[$keyTask]['have_permission_to_move_board'] = $havePermissionToMoveBoard;

                if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
                    $outputTask[$keyTask]['is_active'] = true;
                }

                // last checker
                if ($project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value || !$project['status_raw']) {
                    $outputTask[$keyTask]['is_active'] = false;
                }
            }

            $output[$keyBoard]['tasks'] = $outputTask;
        }

        Log::debug('check permission', [
            'superuser' => $superUserRole,
            'projectpic' => $isProjectPic,
            'directory' => $isDirector,
            'assistant' => isAssistantPMRole()
        ]);

        $project['boards'] = $output;

        // showreels
        $showreels = $this->repo->show($project['uid'], 'id,showreels');
        $project['showreels'] = $showreels->showreels_path;

        $allowedUploadShowreels = true;
        $currentTasks = [];
        foreach ($project['boards'] as $board) {
            foreach ($board['tasks'] as $task) {
                $currentTasks[] = $task;
            }
        }
        $currentTaskStatusses = collect($currentTasks)->pluck('status')->count();
        $completedStatus = collect($currentTasks)->filter(function ($filter) {
            return $filter['status'] == \App\Enums\Production\TaskStatus::Completed->value;
        })->values()->count();
        // if ($currentTaskStatusses == $completedStatus) {
        //     $allowedUploadShowreels = true;
        // }
        $project['allowed_upload_showreels'] = $allowedUploadShowreels;

        storeCache('detailProject' . $projectId, $project);
        return $project;
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
        DB::beginTransaction();
        try {
            $data['project_date'] = date('Y-m-d', strtotime($data['project_date']));

            $ledDetail = [];
            if ((isset($data['led_detail'])) && (!empty($data['led_detail']))) {
                $ledDetail = $data['led_detail'];
            }
            $data['led_detail'] = json_encode($ledDetail);

            $city = \Modules\Company\Models\City::select('name')->find($data['city_id']);
            $state = \Modules\Company\Models\State::select('name')->find($data['state_id']);

            $coordinate = $this->geocoding->getCoordinate($city->name . ', ' . $state->name);
            if (count($coordinate) > 0) {
                $data['longitude'] = $coordinate['longitude'];
                $data['latitude'] = $coordinate['latitude'];
            }

            $data['project_class_id'] = $data['classification'];

            $classification = $this->projectClassRepo->show($data['classification'], 'id,name');

            $data['classification'] = $classification->name;

            $data['city_name'] = $city->name;

            $project = $this->repo->store(collect($data)->except(['led', 'marketing_id', 'pic', 'seeder'])->toArray());

            $marketings = collect($data['marketing_id'])->map(function ($marketing) {
                return [
                    'marketing_id' => getIdFromUid($marketing, new \Modules\Hrd\Models\Employee()),
                ];
            })->toArray();
            $project->marketings()->createMany($marketings);

            $defaultBoards = json_decode(getSettingByKey('default_boards'), true);
            $defaultBoards = collect($defaultBoards)->map(function ($item) {
                return [
                    'based_board_id' => $item['id'],
                    'sort' => $item['sort'],
                    'name' => $item['name'],
                ];
            })->values()->toArray();
            if ($defaultBoards) {
                $project->boards()->createMany($defaultBoards);
            }

            // auto create request item based on default request item
            if (getSettingByKey('have_default_request_item')) {
                $this->autoAssignRequestItem($project);
            }

            DB::commit();

            return generalResponse(
                __('global.successCreateProject'),
                false,
                $data,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function autoAssignRequestItem($project)
    {
        // 'items.*.inventory_id' => 'required',
        // 'items.*.qty' => 'required',

        $items = $this->customItemRepo->show('dummy', '*', ['items.inventory:id,inventory_id', 'items.inventory.inventory:id,name,uid'], 'default_request_item = 1');

        if (($items) && ($items->items->count() > 0)) {
            $payload = [];
            foreach ($items->items as $item) {
                $payload[] = [
                    'inventory_id' => $item->inventory->inventory->uid,
                    'qty' => $item->qty,
                ];
            }

            $this->requestEquipment([
                'items' => $payload,
            ], $project->uid);
        }

    }

    /**
     * Update more detail section
     *
     * @param array $data
     * @param string $id
     * @return array
     */
    public function updateMoreDetail(array $data, string $id)
    {
        DB::beginTransaction();
        try {
            $city = \Modules\Company\Models\City::select('name')->find($data['city_id']);
            $state = \Modules\Company\Models\State::select('name')->find($data['state_id']);

            $coordinate = $this->geocoding->getCoordinate($city->name . ', ' . $state->name);
            if (count($coordinate) > 0) {
                $data['longitude'] = $coordinate['longitude'];
                $data['latitude'] = $coordinate['latitude'];
            }

            $data['city_name'] = $city->name;

            $ledDetail = [];
            if ((isset($data['led_detail'])) && (!empty($data['led_detail']))) {
                $ledDetail = $data['led_detail'];
            }
            $data['led_detail'] = json_encode($ledDetail);

            $this->repo->update(collect($data)->except(['pic'])->toArray(), $id);
            $projectId = getIdFromUid($id, new \Modules\Production\Models\Project());

            if (
                (isset($ata['pic'])) &&
                (count($data['pic']) > 0)
            ) {
                foreach ($data['pic'] as $pic) {
                    $employeeId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee());

                    $this->projectPicRepository->delete(0, 'project_id = ' . $projectId);

                    $this->projectPicRepository->store([
                        'pic_id' => $employeeId,
                        'project_id' => $projectId,
                    ]);
                }
            }

            $project = $this->repo->show($id, 'id,client_portal,collaboration,event_type,note,status,venue,country_id,state_id,city_id,led_detail,led_area', [
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id,uid,boss_id',
            ]);

            $projectTeams = $this->getProjectTeams($project);
            $teams = $projectTeams['teams'];
            $pics = $projectTeams['pics'];
            $picIds = $projectTeams['picUids'];

            $currentData = getCache('detailProject' . $project->id);
            $currentData['venue'] = $project->venue;
            $currentData['city_name'] = $city->name;
            $currentData['country_id'] = $project->country_id;
            $currentData['state_id'] = $project->state_id;
            $currentData['city_id'] = $project->city_id;
            $currentData['event_type'] = $project->event_type_text;
            $currentData['event_type_raw'] = $project->event_type;
            $currentData['collaboration'] = $project->collaboration;
            $currentData['status'] = $project->status_text;
            $currentData['status_raw'] = $project->status;
            $currentData['led_area'] = $project->led_area;
            $currentData['led_detail'] = json_decode($project->led_detail, true);
            $currentData['note'] = $project->note ?? '-';
            $currentData['client_portal'] = $project->client_portal;
            $currentData['pic'] = implode(', ', $pics);
            $currentData['pic_ids'] = $picIds;
            $currentData['teams'] = $teams;

            $currentData = $this->formatTasksPermission($currentData, $project->id);

            storeCache('detailProject' . $project->id, $currentData);

            DB::commit();

            return generalResponse(
                __('global.successUpdateBasicInformation'),
                false,
                $currentData
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update basic project information
     *
     * @param array $data
     * @param string $id
     * @return array
     */
    public function updateBasic(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $data['project_date'] = date('Y-m-d', strtotime($data['date']));

            $projectClass = $this->projectClassRepo->show($data['classification'], 'id,name');

            $data['classification'] = $projectClass->name;

            $data['project_class_id'] = $projectClass->id;

            $this->repo->update(
                collect($data)->except(['date'])->toArray(),
                $projectUid
            );

            /**
             * This function will return
             * 'name' => $project->name,
             * 'project_date',
             * 'event_type' => $eventType,
             * 'event_type_raw' => $project->event_type,
             * 'event_class_raw' => $project->classification,
             * 'event_class' => $eventClass,
             * 'event_class_color' => $eventClassColor,
             */
            $format = $this->formattedBasicData($projectUid);

            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
            $currentData = getCache('detailProject' . $projectId);
            $currentData['name'] = $format['name'];
            $currentData['event_type'] = $format['event_type'];
            $currentData['project_date'] = $format['project_date'];
            $currentData['event_type_raw'] = $format['event_type_raw'];
            $currentData['event_class_raw'] = $format['event_class_raw'];
            $currentData['event_class'] = $projectClass->name;
            $currentData['event_class_color'] = $format['event_class_color'];

            storeCache('detailProject' . $projectId, $currentData);

            DB::commit();

            return generalResponse(
                __('global.successUpdateBasicInformation'),
                false,
                $currentData
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Store references image (Can be multiple uploads)
     *
     * @param array $data
     * @param string $id
     * @return array
     */
    public function storeReferences(array $data, string $id)
    {
        $fileImageType = ['jpg', 'jpeg', 'png'];
        $fileDocumentType = ['doc', 'docx', 'xlsx', 'pdf'];
        $project = $this->repo->show($id);
        try {
            $output = [];

            // handle link upload
            $linkPayload = [];
            foreach ($data['link'] as $link) {
                if (!empty($link['href'])) {
                    $linkPayload[] = [
                        'media_path' => $link['href'],
                        'type' => 'link',
                    ];
                }
            }

            // handle file upload
            foreach ($data['files'] as $file) {
                if ($file['path']) {
                    $type = $file['path']->getClientOriginalExtension();

                    if (gettype(array_search($type, $fileImageType)) != 'boolean') {
                        $fileData = uploadImageandCompress(
                            'projects/references/' . $project->id,
                            10,
                            $file['path']
                        );
                    } else { // handle document upload
                        $fileType = array_search($type, $fileDocumentType);
                        if (gettype($fileType) != 'boolean') {
                            $type = $fileDocumentType[$fileType];
                        }

                        $fileData = uploadFile(
                            'projects/references/' . $project->id,
                            $file['path']
                        );
                    }

                    $output[] = [
                        'media_path' => $fileData,
                        'name' => $fileData,
                        'type' => $type,
                    ];
                }
            }

            $output = collect($output)->merge($linkPayload)->toArray();

            $project->references()->createMany($output);

            // update cache
            $referenceData = $this->formatingReferenceFiles($project->references, $project->id);
            $currentData = getCache('detailProject' . $project->id);
            $currentData['references'] = $referenceData;

            $currentData = $this->formatTasksPermission($currentData, $project->id);

            return generalResponse(
                __("global.successCreateReferences"),
                false,
                [
                    'full_detail' => $currentData,
                    'references' => $referenceData,
                ],
            );
        } catch (\Throwable $th) {
            // delete all files in folder
            // deleteFolder(storage_path('app/public/projects/references/' . $project->id));

            return errorResponse($th);
        }
    }

    /**
     * Save description in selected task
     *
     * @param array $data
     * @param string $taskId
     * @return array
     */
    public function storeDescription(array $data, string $taskId)
    {
        DB::beginTransaction();
        try {
            $this->taskRepo->update($data, $taskId);

            $this->loggingTask(['task_uid' => $taskId], 'addDescription');

            $task = $this->formattedDetailTask($taskId);

            $currentData = getCache('detailProject' . $task->project_id);

            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            $currentData['boards'] = $boards;

            $currentData = $this->formatTasksPermission($currentData, $task->project_id);

            DB::commit();

            return generalResponse(
                __('global.descriptionAdded'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Assign member to selected task
     *
     * $data variable will have
     * 1. array users -> uid
     * 2. array removed -> uid
     *
     * when isForProjectManager is TRUE, then set status to approved, otherwise set to waiting approval
     *
     * Every assigned member need to approved it before do any action. Bcs of this sytem will take a note about 'IDLE TASK'
     * IDLE TASK is the idle time between assigned time and approved time
     *
     * If $isRevise is TRUE, no need to change task status (Already handle in parent function)
     *
     * @param array $data
     * @param string $taskId
     * @param boolean $isForProjectManager
     * @param boolean $isRevise
     * @return array
     */
    public function assignMemberToTask(array $data, string $taskUid, bool $isForProjectManager = false, bool $isRevise = false)
    {
        DB::beginTransaction();
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());

            $notifiedNewTask = [];
            foreach ($data['users'] as $user) {

                $employeeId = getIdFromUid($user, new \Modules\Hrd\Models\Employee());

                $checkPic = $this->taskPicRepo->show(0, 'id', [], 'project_task_id = ' . $taskId . ' AND employee_id = ' . $employeeId);
                if (!$checkPic) {
                    $taskDetail = $this->taskRepo->show($taskUid, 'id,project_id');

                    // add to pic history
                    $this->taskPicHistory->store([
                        'project_id' => $taskDetail->project_id,
                        'project_task_id' => $taskId,
                        'employee_id' => $employeeId,
                    ]);

                    $payload = [
                        'employee_id' => $employeeId,
                        'project_task_id' => $taskId,
                        'status' => !$isForProjectManager ? \App\Enums\Production\TaskPicStatus::WaitingApproval->value : \App\Enums\Production\TaskPicStatus::Approved->value,
                        'assigned_at' => Carbon::now(),
                    ];

                    if ($isRevise) {
                        $payload['status'] = \App\Enums\Production\TaskPicStatus::Revise->value;
                    }

                    $this->taskPicRepo->store($payload);

                    $notifiedNewTask[] = $employeeId;

                    // record task working time history
                    if ($isForProjectManager) { // set to check by pm
                        $this->setTaskWorkingTime($taskId, $employeeId, \App\Enums\Production\WorkType::CheckByPm->value);
                    } else { // check to assigned
                        $this->setTaskWorkingTime($taskId, $employeeId, \App\Enums\Production\WorkType::Assigned->value);
                    }

                    // change task status
                    if (!$isRevise) { // see PHPDOC
                        $this->taskRepo->update([
                            'status' => !$isForProjectManager ? \App\Enums\Production\TaskStatus::WaitingApproval->value : \App\Enums\Production\TaskStatus::CheckByPm->value,
                        ], $taskUid);
                    }

                    $this->loggingTask([
                        'task_id' => $taskId,
                        'employee_uid' => $user
                    ], 'assignMemberTask');
                }
            }

            $this->detachTaskPic($data['removed'], $taskId, true, true);

            // notify removed user
            if (count($data['removed']) > 0) {
                \Modules\Production\Jobs\RemoveUserFromTaskJob::dispatch($data['removed'], $taskId)->afterCommit();
            }

            $task = $this->formattedDetailTask($taskUid);

            $currentData = getCache('detailProject' . $task->project->id);
            if (!$currentData) {
                $this->show($task->project->uid);
                $currentData = getCache('detailProject' . $task->project->id);
            }
            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $task->project_id, $currentData);

            $this->formatTasksPermission($currentData, $task->project_id);

            // TODO: CHECK AGAIN ACTION WHEN ASSIGN TO PROJECT MANAGER
            if ($currentData['status_raw'] != \App\Enums\Production\ProjectStatus::Draft->value) {
                // override notification when task is revise
                if ($isRevise) {
                    \Modules\Production\Jobs\ReviseTaskJob::dispatch($notifiedNewTask, $taskId)->afterCommit();
                } else {
                    \Modules\Production\Jobs\AssignTaskJob::dispatch($notifiedNewTask, $taskId)->afterCommit();
                }
            }

            DB::commit();

            return generalResponse(
                __('global.memberAdded'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    /**
     * Detach current pic to selected task
     *
     * @param array $ids (uid of current task pic)
     * @param integer $taskId
     * @param boolean $isEmployeeUid
     * @return void
     */
    protected function detachTaskPic(array $ids, int $taskId, bool $isEmployeeUid = true, bool $removeFromHistory = false)
    {
        foreach ($ids as $removedUser) {
            if ($isEmployeeUid) {
                $removedEmployeeId = getIdFromUid($removedUser, new \Modules\Hrd\Models\Employee());
            } else {
                $removedEmployeeId = $removedUser;
            }

            $this->taskPicRepo->deleteWithCondition('employee_id = ' . $removedEmployeeId . ' AND project_task_id = ' . $taskId);

            // delete from history
            if ($removeFromHistory) {
                $this->taskPicHistory->deleteWithCondition('employee_id = ' . $removedEmployeeId . ' AND project_task_id = ' . $taskId);
            }

            $this->loggingTask([
                'task_id' => $taskId,
                'employee_uid' => $removedUser
            ], 'removeMemberTask');
        }
    }

    /**
     * Delete selected task
     *
     * @param string $taskUid
     * @return array
     */
    public function deleteTask(string $taskUid)
    {
        try {
            $task = $this->taskRepo->show($taskUid, 'id,project_id', [
                'project:id,uid'
            ]);

            $projectUid = $task->project->uid;
            $projectId = $task->project->id;

            // delete pic history if exists
            $this->taskPicHistory->deleteWithCondition('project_id = ' . $task->project_id . ' and project_task_id = ' . $task->id);

            $this->taskRepo->bulkDelete([$taskUid], 'uid');

            $boards = $this->formattedBoards($projectUid);
            $currentData = getCache('detailProject' . $projectId);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            return generalResponse(
                __('global.successDeleteTask'),
                false,
                $currentData,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all task types
     *
     * @return array
     */
    public function getTaskTypes()
    {
        $types = \App\Enums\Production\TaskType::cases();

        $out = [];
        foreach ($types as $type) {
            $out[] = [
                'value' => $type->value,
                'title' => $type->label(),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }

    /**
     * Store task on selected board
     *
     * $data variable will have
     * string name
     * string end_date
     * array pic [uid]
     * array media [blob]
     *
     * @param array $data
     * @param integer $boardId
     * @return array
     */
    public function storeTask(array $data, int $boardId)
    {
        DB::beginTransaction();
        try {
            $board = $this->boardRepo->show($boardId, 'project_id,name', ['project:id,uid', 'project.personInCharges']);
            $data['project_id'] = $board->project_id;
            $data['project_board_id'] = $boardId;
            $data['start_date'] = date('Y-m-d');
            $data['end_date'] = !empty($data['end_date']) ? date('Y-m-d', strtotime($data['end_date'])) : null;

            // set as waiting employee approval
            if (!empty($data['pic'])) {
                $data['status'] = \App\Enums\Production\TaskStatus::WaitingApproval->value;
            }

            $taskStore = $this->taskRepo->store(collect($data)->except(['pic', 'media'])->toArray());

            $task = $this->formattedDetailTask($taskStore->uid);

            // task log
            $this->loggingTask([
                'board_id' => $boardId,
                'board' => $board,
                'task' => $task,
            ], 'addNewTask');

            // assign pic and record work timing if needed
            if (!empty($data['pic'])) {
                $this->assignMemberToTask(
                    [
                        'users' => $data['pic'],
                        'removed' => [],
                    ],
                    $task->uid
                );

                // send notification if needed

            }

            // add image attachment if needed
            if (!empty($data['media'])) {
                $this->uploadTaskMedia(
                    [
                        'media' => $data['media']
                    ],
                    $task->id,
                    $board->project_id,
                    $board->project->uid,
                    $task->uid
                );
            }

            $boards = $this->formattedBoards($board->project->uid);
            $currentData = getCache('detailProject' . $board->project->id);
            $currentData['boards'] = $boards;

            // $project = $this->repo->show($board->project->uid)
            $teams = $this->getProjectTeams((object)$board->project);
            $currentData['teams'] = $teams['teams'];

            $projectTasks = $this->taskRepo->list('*', 'project_id = ' . $board->project_id, [
                'board:id,name,project_id'
            ]);
            $progress = $this->formattedProjectProgress($projectTasks, $board->project->id);
            $currentData['progress'] = $progress;

            storeCache('detailProject' . $board->project_id, $currentData);

            DB::commit();

            return generalResponse(
                __('global.taskCreated'),
                false,
                $currentData
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function reninitDetailCache($project)
    {
        $this->show($project->uid);

        return getCache('detailProject' . $project->id);
    }

    /**
     * Delete reference image
     *
     * @param array $ids
     * @return array
     */
    public function deleteReference(array $ids, string $projectId)
    {
        try {
            foreach ($ids as $id) {
                $reference = $this->referenceRepo->show($id);
                $path = $reference->media_path;

                deleteImage(storage_path('app/public/projects/references/' . $reference->project_id . '/' . $path));

                $this->referenceRepo->delete($id);
            }


            $project = $this->repo->show($projectId, 'id,name,uid');

            // update cache
            $referenceData = $this->formatingReferenceFiles($project->references, $project->id);
            $currentData = getCache('detailProject' . $project->id);
            $currentData['references'] = $referenceData;

            $currentData = $this->formatTasksPermission($currentData, $project->id);

            return generalResponse(
                __('global.successDeleteReference'),
                false,
                [
                    'full_detail' => $currentData,
                    'references' => $referenceData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get teams in selected project
     *
     * @param string $projectId
     * @return array
     */
    public function getProjectMembers(int $projectId, string $taskId)
    {
        try {
            $project = $this->repo->show('', '*', [
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id',
            ], 'id = ' . $projectId);

            $projectTeams = $this->getProjectTeams($project);
            $teams = $projectTeams['teams'];

            $task = $this->taskRepo->show($taskId, 'id,project_id,project_board_id', [
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,uid,name,email'
            ]);

            $currentTaskPics = $task->pics->toArray();
            $outSelected = collect($currentTaskPics)->map(function ($item) {
                return [
                    'selected' => true,
                    'email' => $item['employee']['email'],
                    'name' => $item['employee']['name'],
                    'uid' => $item['employee']['uid'],
                    'id' => $item['employee']['id'],
                    'intital' => $item['employee']['initial'],
                ];
            })->toArray();

            $selectedKeys = [];
            foreach ($currentTaskPics as $c) {
                $selectedKeys[] = $c['employee']['uid'];
            }

            $memberKeys = array_column($teams, 'uid');

            $diff = array_diff($memberKeys, $selectedKeys);

            $availableKeys = [];
            $available = [];
            foreach ($teams as $team) {
                if (!in_array($team['uid'], $outSelected)) {
                    $available[] = $team;
                }
            }

            $out = [
                'selected' => $outSelected,
                'available' => $available,
                'teams' => $teams,
            ];

            return generalResponse(
                'success',
                false,
                $out,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     *
     * @param array $data
     * @param string $id
     * @param string $where
     *
     * @return array
     */
    public function update(
        array $data,
        string $id,
        string $where = ''
    ): array {
        try {
            $this->repo->update($data, $id);

            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected data
     *
     * @param integer $id
     *
     * @return void
     */
    public function delete(int $id): array
    {
        try {
            return generalResponse(
                'Success',
                false,
                $this->repo->delete($id)->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store new equipment request to INVENTORY
     *
     * @param array $data
     * @param string $projectUid
     * @return array
     */
    public function requestEquipment(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            // format payload to remove item type
            $currentPayload = [];
            foreach ($data['items'] as $outputData) {
                if ((isset($outputData['inventories'])) && (count($outputData['inventories']) > 0)) {
                    foreach ($outputData['inventories'] as $inventory) {
                        $currentPayload[] = [
                            'inventory_id' => $inventory['id'],
                            'qty' => $inventory['qty'],
                        ];
                    }
                } else {
                    $currentPayload[] = [
                        'inventory_id' => $outputData['inventory_id'],
                        'qty' => $outputData['qty'],
                    ];
                }
            }

            // handle duplicate items
            $groupBy = collect($currentPayload)->groupBy('inventory_id')->all();
            $out = [];
            foreach ($groupBy as $key => $group) {
                $qty = collect($group)->pluck('qty')->sum();
                $out[] = [
                    'inventory_id' => $key,
                    'qty' => $qty,
                ];
            }

            $project = $this->repo->show($projectUid, 'id,project_date,uid');
            foreach ($out as $item) {
                $inventoryId = getIdFromUid($item['inventory_id'], new \Modules\Inventory\Models\Inventory());

                $check = $this->projectEquipmentRepo->show('', '*', "project_id = " . $project->id . " AND inventory_id = " . $inventoryId);

                if (!$check) {
                    $this->projectEquipmentRepo->store([
                        'project_id' => $project->id,
                        'inventory_id' => $inventoryId,
                        'qty' => $item['qty'],
                        'status' => \App\Enums\Production\RequestEquipmentStatus::Requested->value,
                        'project_date' => $project->project_date,
                    ]);
                } else {
                    if ($check->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value) {
                        $this->projectEquipmentRepo->update([
                            'status' => \App\Enums\Production\RequestEquipmentStatus::Requested->value,
                            'is_checked_pic' => 0,
                        ], '', 'inventory_id = ' . $inventoryId . ' AND project_id = ' . $project->id);
                    }
                }
            }

            $equipments = $this->formattedEquipments($project->id);
            $currentData = getCache('detailProject' . $project->id);
            if (!$currentData) {
                $this->show($project->uid);

                $currentData = getCache('detailProject' . $project->id);
            }
            $currentData['equipments'] = $equipments;

            storeCache('detailProject' . $project->id, $currentData);

            \Modules\Production\Jobs\RequestEquipmentJob::dispatch($project);

            DB::commit();

            return generalResponse(
                'success',
                false,
                $currentData
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::debug('check continue request', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);

            return errorResponse($th);
        }
    }

    /**
     * Get list of project equipments
     *
     * @param string $projectUid
     * @return array
     */
    public function listEquipment(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $data = $this->projectEquipmentRepo->list('id,uid,project_id,inventory_id,qty,status,is_checked_pic', 'project_id = ' . $projectId, [
            'inventory:id,name,stock',
            'inventory.image',
            'inventory.items:id,inventory_id,inventory_code'
        ]);

        $data = collect((object) $data)->map(function ($item) {
            $canTakeAction = true;
            if (
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ||
                $item->is_checked_pic ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Decline->value ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::Return->value ||
                $item->status == \App\Enums\Production\RequestEquipmentStatus::CompleteAndNotReturn
            ) {
                $canTakeAction = false;
            }
            return [
                'uid' => $item->uid,
                'inventory_name' => $item->inventory->name,
                'inventory_image' => $item->inventory->display_image,
                'inventory_stock' => $item->inventory->stock,
                'items' => collect($item->inventory->items)->pluck('inventory_code')->toArray(),
                'qty' => $item->qty,
                'status' => $item->status_text,
                'status_color' => $item->status_color,
                'is_checked_pic' => $item->is_checked_pic,
                'is_cancel' => $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ? true : false,
                'can_take_action' => $canTakeAction,
            ];
        })->toArray();

        return generalResponse(
            'Success',
            false,
            $data,
        );
    }

    /**
     * Update request equipment list
     *
     * @param array $data
     * @param string $projectUid
     *
     * @return array
     */
    public function updateEquipment(array $data, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $picPermission = auth()->user()->can('accept_request_equipment');

            foreach ($data['items'] as $item) {
                $inventoryCode = $item['selected_code'];
                if (empty($inventoryCode)) {
                    $projectEquipment = $this->projectEquipmentRepo->show($item['id'], 'id,inventory_id');

                    $inventoryItems = $this->inventoryItemRepo->list('id,inventory_code', 'inventory_id = ' . $projectEquipment->inventory_id);
                    $inventoryCode = $inventoryItems[0]->inventory_code;
                }

                $payload = [
                    'status' => $item['status'],
                    'is_checked_pic' => false,
                    'inventory_code' => $inventoryCode,
                ];

                if ($picPermission) {
                    $payload['is_checked_pic'] = true;
                }

                // update stock
                if ($item['status'] == \App\Enums\Production\RequestEquipmentStatus::Ready->value) {

                }

                $this->projectEquipmentRepo->update($payload, '', "is_checked_pic = FALSE and uid = '" . $item['id'] . "'");
            }

            $cache = $this->getDetailProjectCache($projectUid);
            $currentData = $cache['cache'];
            $projectId = $cache['projectId'];

            $equipments = $equipments = $this->formattedEquipments($projectId);
            $currentData['equipments'] = $equipments;

            // set data for update state
            $output = collect($equipments)->map(function ($item) {
                return [
                    'uid' => $item->uid,
                    'inventory_name' => $item->inventory->name,
                    'inventory_image' => $item->inventory->display_image,
                    'inventory_stock' => $item->inventory->stock,
                    'qty' => $item->qty,
                    'status' => $item->status_text,
                    'status_color' => $item->status_color,
                    'is_checked_pic' => $item->is_checked_pic,
                ];
            })->toArray();

            storeCache('detailProject' . $projectId, $currentData);

            $userCanAcceptRequest = auth()->user()->can('request_inventory'); // if TRUE than he is INVENTARIS

            \Modules\Production\Jobs\PostEquipmentUpdateJob::dispatch($projectUid, $data, $userCanAcceptRequest)->afterCommit();

            DB::commit();

            return generalResponse(
                'success',
                false,
                $output,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get Available project status
     *
     * @return array
     */
    public function getProjectStatus()
    {
        $statuses = \App\Enums\Production\ProjectStatus::cases();

        $out = [];

        foreach ($statuses as $status) {
            $out[] = [
                'value' => $status->value,
                'title' => $status->label(),
            ];
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }

    public function cancelRequestEquipment(array $data, string $projectUid)
    {
        try {
            $this->projectEquipmentRepo->update([
                'status' => \App\Enums\Production\RequestEquipmentStatus::Cancel->value,
            ], $data['id']);

            $cache = $this->getDetailProjectCache($projectUid);
            $currentData = $cache['cache'];
            $projectId = $cache['projectId'];

            $equipments = $this->formattedEquipments($projectId);

            $currentData['equipments'] = $equipments;

            storeCache('detailProject' . $projectId, $currentData);

            return generalResponse(
                __("global.equipmentCanceled"),
                false,
                $currentData
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * get current datail project in the cache
     *
     * @param string $projectUid
     * @return array
     */
    protected function getDetailProjectCache(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $currentData = getCache('detailProject' . $projectId);
        if (!$currentData) {
            $this->show($projectUid);

            $currentData = getCache('detailProject' . $projectId);
        }

        return [
            'cache' => $currentData,
            'projectId' => $projectId,
        ];
    }

    public function updateDeadline(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $this->taskRepo->update(
                [
                    'start_date' => empty($data['start_date']) ? null : date('Y-m-d', strtotime($data['start_date'])),
                    'end_date' => empty($data['end_date']) ? null : date('Y-m-d', strtotime($data['end_date'])),
                ],
                $data['task_id']
            );

            $this->loggingTask([
                'task_uid' => $data['task_id']
            ], 'updateDeadline');

            $task = $this->formattedDetailTask($data['task_id']);

            $cache = $this->getDetailProjectCache($projectUid);
            $currentData = $cache['cache'];
            $projectId = $cache['projectId'];

            $boards = $this->formattedBoards($projectUid);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            DB::commit();

            return generalResponse(
                __("global.deadlineAdded"),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Upload attachment in selected task
     *
     * @param array $data
     * @param string $taskUid
     * @param string $projectUid
     * @return array
     */
    public function uploadTaskAttachment(array $data, string $taskUid, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $output = [];
            if ((isset($data['media'])) && (count($data['media']) > 0)) {
                DB::commit();

                return $this->uploadTaskMedia($data, $taskId, $projectId, $projectUid, $taskUid);
            } else if ((isset($data['task_id'])) && (count($data['task_id']) > 0)) {
                DB::commit();

                return $this->uploadTaskLink($data, $taskId, $projectId, $projectUid, $taskUid);
            } else {
                DB::commit();

                return $this->uploadLinkAttachment($data, $taskId, $projectId, $projectUid, $taskUid);
            }
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function uploadTaskLink(array $data, int $taskId, int $projectId, string $projectUid, string $taskUid)
    {
        $type = \App\Enums\Production\ProjectTaskAttachment::TaskLink->value;

        foreach ($data['task_id'] as $task) {
            $targetTask = getIdFromUid($task, new \Modules\Production\Models\ProjectTask());

            $check = $this->projectTaskAttachmentRepo->show('dummy', 'id', [], "media = '{$targetTask}' and project_id = {$projectId} and project_task_id = {$taskId}");

            if (!$check) {
                $this->projectTaskAttachmentRepo->store([
                    'project_task_id' => $taskId,
                    'project_id' => $projectId,
                    'media' => $targetTask,
                    'type' => $type,
                ]);
            }
        }

        $task = $this->formattedDetailTask($taskUid);

        $cache = $this->getDetailProjectCache($projectUid);
        $currentData = $cache['cache'];
        $projectId = $cache['projectId'];

        $boards = $this->formattedBoards($projectUid);
        $currentData['boards'] = $boards;

        storeCache('detailProject' . $projectId, $currentData);

        return generalResponse(
            __('global.successUploadAttachment'),
            false,
            [
                'task' => $task,
                'full_detail' => $currentData,
            ]
        );
    }

    protected function uploadLinkAttachment(array $data, int $taskId, int $projectId, string $projectUid, string $taskUid)
    {
        $type = \App\Enums\Production\ProjectTaskAttachment::ExternalLink->value;

        $this->projectTaskAttachmentRepo->store([
            'project_task_id' => $taskId,
            'project_id' => $projectId,
            'media' => $data['link'],
            'display_name' => $data['display_name'] ?? null,
            'type' => $type,
        ]);

        $task = $this->formattedDetailTask($taskUid);

        $cache = $this->getDetailProjectCache($projectUid);
        $currentData = $cache['cache'];
        $projectId = $cache['projectId'];

        $boards = $this->formattedBoards($projectUid);
        $currentData['boards'] = $boards;

        storeCache('detailProject' . $projectId, $currentData);

        return generalResponse(
            __('global.successUploadAttachment'),
            false,
            [
                'task' => $task,
                'full_detail' => $currentData,
            ]
        );
    }

    /**
     * Store task media attachments
     *
     * $data variable will have
     * 1. media (file type or blob)
     *
     * @param array $data
     * @param integer $taskId
     * @param integer $projectId
     * @param string $projectUid
     * @param string $taskUid
     * @return array
     */
    protected function uploadTaskMedia(array $data, int $taskId, int $projectId, string $projectUid, string $taskUid): array
    {
        $imagesMime = [
            'image/png',
            'image/jpg',
            'image/jpeg',
            'image/webp',
        ];

        $type = \App\Enums\Production\ProjectTaskAttachment::Media->value;

        foreach ($data['media'] as $file) {
            $mime = $file->getClientMimeType();

            if ($mime == 'application/pdf') {
                $name = uploadFile(
                    'projects/' . $projectId . '/task/' . $taskId,
                    $file,
                );
            } else if (in_array($mime, $imagesMime)) {
                $name = uploadImageandCompress(
                    'projects/' . $projectId . '/task/' . $taskId,
                    10,
                    $file
                );
            }

            $this->projectTaskAttachmentRepo->store([
                'project_task_id' => $taskId,
                'project_id' => $projectId,
                'media' => $name,
                'type' => $type,
            ]);

            $this->loggingTask([
                'task_id' => $taskId,
                'media_name' => $name
            ], 'addAttachment');
        }

        $task = $this->formattedDetailTask($taskUid);

        $cache = $this->getDetailProjectCache($projectUid);
        $currentData = $cache['cache'];
        $projectId = $cache['projectId'];

        $boards = $this->formattedBoards($projectUid);
        $currentData['boards'] = $boards;

        storeCache('detailProject' . $projectId, $currentData);

        return generalResponse(
            __('global.successUploadAttachment'),
            false,
            [
                'task' => $task,
                'full_detail' => $currentData,
            ]
        );
    }

    /**
     * Search task by name
     *
     * @param string $search
     * @param string $taskUid
     * @return array
     */
    public function searchTask(string $projectUid, string $taskUid, string $search = '')
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $search = strtolower($search);
            if (!$search) {
                $where = "project_id = '{$projectId}' and uid != '{$taskUid}'";
            } else {
                $where = "LOWER(name) LIKE '%{$search}%' and project_id = '{$projectId}' and uid != '{$taskUid}'";
            }
            $task = $this->taskRepo->list('id,name,uid', $where);

            return generalResponse(
                'success',
                false,
                $task->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function getRelatedTask(string $projectUid, string $taskUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $data = $this->taskRepo->list('id,name,uid', "project_id = {$projectId} and uid != '{$taskUid}'");

        return generalResponse(
            'success',
            false,
            $data->toArray(),
        );
    }

    public function downloadAttachment(string $taskId, int $attachmentId)
    {
        try {
            $data = $this->projectTaskAttachmentRepo->show('dummy', 'media,project_id,project_task_id', [], "id = {$attachmentId}");

            return \Illuminate\Support\Facades\Storage::download('projects/' . $data->project_id . '/task/' . $data->project_task_id . '/' . $data->media);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function deleteAttachment(string $projectUid, string $taskUid, int $attachmentId)
    {
        DB::beginTransaction();
        try {
            $data = $this->projectTaskAttachmentRepo->show('dummy', 'media,project_id,project_task_id,type', [], "id = {$attachmentId}");

            if ($data->type == \App\Enums\Production\ProjectTaskAttachment::Media->value) {
                deleteImage(storage_path("app/public/projects/{$data->project_id}/task/{$data->project_task_id}/{$data->media}"));

                $this->loggingTask([
                    'task_uid' => $taskUid,
                    'media_name' => $data->media
                ], 'deleteAttachment');
            }

            $this->projectTaskAttachmentRepo->delete($attachmentId);


            $task = $this->formattedDetailTask($taskUid);

            $cache = $this->getDetailProjectCache($projectUid);
            $currentData = $cache['cache'];
            $projectId = $cache['projectId'];

            $boards = $this->formattedBoards($projectUid);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            DB::commit();

            return generalResponse(
                __('global.successDeleteAttachment'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ],
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function storeCurrentBoards(int $taskId): void
    {
        //
    }

    /**
     * Function to upload proof of work, and change task to next task and assign PM to check employee work
     *
     * @param array $data
     * @param string $projectUid
     * @param string $taskUid
     * @return array
     */
    public function proofOfWork(array $data, string $projectUid, string $taskUid)
    {
        DB::beginTransaction();
        $image = [];
        $selectedProjectId = null;
        $selectedTaskId = null;
        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());

        // variable for error response
        $failedResponseTask = [];
        $failedResponseDetail = [];
        try {
            if ($data['nas_link'] && isset($data['preview'])) {
                $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());;
                $selectedProjectId = $projectId;
                $selectedTaskId = $taskId;

                foreach ($data['preview'] as $img) {
                    $image[] = uploadImageandCompress(
                        "projects/{$projectId}/task/{$taskId}/proofOfWork",
                        10,
                        $img
                    );
                }

                $this->proofOfWorkRepo->store([
                    'project_task_id' => $taskId,
                    'project_id' => $projectId,
                    'nas_link' => $data['nas_link'],
                    'preview_image' => json_encode($image),
                    'created_by' => auth()->id(),
                    'created_year' => date('Y', strtotime(Carbon::now())),
                    'created_month' => date('m', strtotime(Carbon::now())),
                ]);

                $boardId = $data['board_id'];
                $sourceBoardId = $data['source_board_id'];

                if ($data['manual_approve']) {
                    $taskDetail = $this->taskRepo->show($taskUid, 'id,project_board_id');
                    $sourceBoardId = $taskDetail->project_board_id;

                    // get next board
                    $boardList = $this->boardRepo->list('id,name', 'project_id = ' . $projectId);
                    foreach ($boardList as $keyBoard => $boardData) {
                        if ($boardData->id == $sourceBoardId) {
                            if (isset($boardList[$keyBoard + 1])) {
                                $boardId = $boardList[$keyBoard + 1]->id;
                                break;
                            } else {
                                $boardId = $sourceBoardId;
                                break;
                            }
                        }
                    }
                }

                // set current pic
                $currentPics = $this->taskPicRepo->list('employee_id', 'project_task_id = ' . $taskId);
                $payloadUpdate['current_pics'] = json_encode(collect($currentPics)->pluck('employee_id')->toArray());

                $this->taskRepo->update($payloadUpdate, '', "id = " . $taskId);

//                // move task
//                $setCurrentPic = true;
//                $this->changeTaskBoardProcess(
//                    [
//                        'board_id' => $boardId,
//                        'task_id' => $taskUid,
//                        'board_source_id' => $sourceBoardId,
//                    ],
//                    $projectUid,
//                    \App\Enums\Production\TaskStatus::CheckByPm->value,
//                    $setCurrentPic
//                );

                // set worktime as finish to current task pic
                $currentTaskPic = $this->taskPicRepo->list('id,employee_id', 'project_task_id = ' . $taskId);
                if (count($currentTaskPic) > 0) {
                    foreach ($currentTaskPic as $pic) {
                        $this->setTaskWorkingTime($taskId, $pic->employee_id, \App\Enums\Production\WorkType::Finish->value);
                    }
                }

                // detach all pics and attach pic to PM
                $this->detachPicAndAssignProjectManager(
                    $taskId,
                    $taskUid,
                    $projectId
                );

                $task = $this->formattedDetailTask($taskUid);

                // notified project manager
                \Modules\Production\Jobs\ProofOfWorkJob::dispatch($projectId, $taskId, auth()->id())->afterCommit();

                $cache = $this->getDetailProjectCache($projectUid);
                $currentData = $cache['cache'];

                $boards = $this->formattedBoards($projectUid);
                $currentData['boards'] = $boards;

                storeCache('detailProject' . $projectId, $currentData);
            } else {
                // user cancel to upload proof of work
                $task = $this->formattedDetailTask($taskUid);
                $cache = $this->getDetailProjectCache($projectUid);
                $currentData = $cache['cache'];
                $projectId = $cache['projectId'];
            }

            DB::commit();

            return generalResponse(
                __('global.proofOfWorkUploaded'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            // delete image if any
            if (count($image) > 0) {
                foreach ($image as $img) {
                    deleteImage(storage_path("app/public/projects/{$selectedProjectId}/task/{$selectedTaskId}/proofOfWork/{$img}"));
                }
            }

            return errorResponse($th);
        }
    }

    /**
     * Detach current pic and then assign project manager to selected task (This step PM will check proof of work)
     *
     * $ids array will be have array of pic id (int)
     *
     * @param integer $taskId
     * @param string $taskUid
     * @param integer $projectId
     * @return void
     */
    protected function detachPicAndAssignProjectManager(int $taskId, string $taskUid, int $projectId)
    {
        // get project pics
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId, ['employee:id,uid']);
        $projectPicUids = collect($projectPics)->pluck('employee.uid')->toArray();

        // get task pics
        $taskPics = $this->taskPicRepo->list('id,employee_id', 'project_task_id = ' . $taskId, ['employee:id,uid']);
        $taskPicUids = collect($taskPics)->pluck('employee.uid')->toArray();

        $this->detachTaskPic($taskPicUids, $taskId);

        // attach project manager to selected task
        $this->assignMemberToTask(
            [
                'users' => $projectPicUids,
                'removed' => [],
            ],
            $taskUid,
            true
        );
    }

    protected function changeTaskBoardProcess(array $data, string $projectUid, string $nextTaskStatus = '', bool $setCurrentPic = false)
    {
        $taskId = getIdFromUid($data['task_id'], new \Modules\Production\Models\ProjectTask());

        $boardIds = [$data['board_id'], $data['board_source_id']];
        $boards = $this->boardRepo->list('id,name,based_board_id', "id IN (" . implode(',', $boardIds) . ")");
        $boardData = collect((object) $boards)->filter(function ($filter) use ($data) {
            return $filter->id == $data['board_id'];
        })->values();

        $payloadUpdate = [
            'project_board_id' => $data['board_id'],
            'current_board' => $data['board_source_id'],
        ];

        if (!empty($nextTaskStatus)) {
            $payloadUpdate['status'] = $nextTaskStatus;
        }

        if ($setCurrentPic) {
            $currentPics = $this->taskPicRepo->list('employee_id', 'project_task_id = ' . $taskId);
            $payloadUpdate['current_pics'] = json_encode(collect($currentPics)->pluck('employee_id')->toArray());
        }

        $this->taskRepo->update($payloadUpdate, '', "id = " . $taskId);

        // logging
        $this->loggingTask(
            // collect($data)->merge(['boards' => $boards])->toArray(),
            [
                'task_id' => $taskId,
                'boards' => $boards,
                'board_id' => $data['board_id'],
                'board_source_id' => $data['board_source_id'],
            ],
            'moveTask'
        );
    }

    public function manualMoveBoard(array $data, string $projectUid) {
        $cache = $this->getDetailProjectCache($projectUid);
        $currentData = $cache['cache'];
        $projectId = $cache['projectId'];

        $this->changeTaskBoardProcess($data, $projectUid);

        $boards = $this->formattedBoards($projectUid);
        $currentData['boards'] = $boards;

        storeCache('detailProject' . $projectId, $currentData);

        return generalResponse(
            'success',
            false,
            $currentData,
        );
    }

    /**
     * Change board of task (When user move a task)
     *
     * @param array $data
     * Data will be
     * 1. int board_id
     * 2. int board_source_id
     * 3. string task_id
     * @param string $projectUid
     * @return array
     */
    public function changeTaskBoard(array $data, string $projectUid)
    {
        $cache = $this->getDetailProjectCache($projectUid);
        $currentData = $cache['cache'];
        $projectId = $cache['projectId'];

        DB::beginTransaction();
        try {
            $this->changeTaskBoardProcess($data, $projectUid);

            $taskId = getIdFromUid($data['task_id'], new \Modules\Production\Models\ProjectTask());

            // set worktime as finish to current task pic
            $currentTaskPic = $this->taskPicRepo->list('id,employee_id', 'project_task_id = ' . $taskId);
            if (count($currentTaskPic) > 0) {
                foreach ($currentTaskPic as $pic) {
                    $this->setTaskWorkingTime($taskId, $pic->employee_id, \App\Enums\Production\WorkType::Finish->value);
                }
            }

            // detach all pics and attach pic to PM if action take by employee NOT SUPER USER / DIRECTOR / PROJECT MANAGER
            $user = auth()->user();
            $employeeId = $user->employee_id ?? 0;

            if (isEmployee()) {
                $this->detachPicAndAssignProjectManager(
                    $taskId,
                    $data['task_id'],
                    $projectId
                );
            }

            $boards = $this->formattedBoards($projectUid);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            DB::commit();

            return generalResponse(
                'success',
                false,
                $currentData,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th, $currentData);
        }
    }

    /**
     * Function to start record the start of the working time in the selected task
     * This function only running with 2 scenarios like:
     * 1. When employee approved task
     * 2. When task is moved to next step (Auto assign to PM)
     *
     * $type will refer to \App\Enums\Production\WorkType.php
     *
     * @param collection $task
     * @return void
     */
    protected function setTaskWorkingTime(int $taskId, int $employeeId, string $type)
    {
        $this->taskPicLogRepo->store([
            'project_task_id' => $taskId,
            'employee_id' => $employeeId,
            'work_type' => $type,
            'time_added' => Carbon::now(),
        ]);
    }

    /**
     * Update task name
     *
     * @param array $data
     * @param string $projectUid
     * @param string $taskId
     * @return array
     */
    public function updateTaskName(array $data, string $projectUid, string $taskId)
    {
        DB::beginTransaction();
        try {
            $this->taskRepo->update($data, $taskId);

            $this->loggingTask(['task_uid' => $taskId], 'changeTaskName');

            $task = $this->formattedDetailTask($taskId);

            $cache = $this->getDetailProjectCache($projectUid);
            $currentData = $cache['cache'];
            $projectId = $cache['projectId'];

            $boards = $this->formattedBoards($projectUid);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            DB::commit();

            return generalResponse(
                'success',
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Create task log in every event
     *
     * @param array $payload
     * @param string $type
     * Type will be:
     * 1. moveTask
     * 2. addUser
     * 3. addNewTask
     * 4. addAttachment
     * 5. deleteAttachment
     * 6. changeTaskName
     * 7. addDescription
     * 8. updateDeadline
     * 9. assignMemberTask
     * 10. removeMemberTask
     * 11. deleteAttachment
     * @return void
     */
    public function loggingTask($payload, string $type)
    {
        $type .= "Log";
        return $this->{$type}($payload);
    }

    /**
     * Add log when user add attachment
     *
     * @param array $payload
     * $payload will have
     * [int task_id, string media_name]
     * @return void
     */
    protected function addAttachmentLog($payload)
    {
        $text = __('global.addAttachmentLogText', [
            'name' => auth()->user()->username,
            'media' => $payload['media_name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'addAttachment',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when user delete attachment
     *
     * @param array $payload
     * $payload will have
     * [string task_uid, string media_name]
     * @return void
     */
    protected function deleteAttachmentLog($payload)
    {
        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $text = __('global.deleteAttachmentLogText', [
            'name' => auth()->user()->username,
            'media' => $payload['media_name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'addAttachment',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when remove member from selected task
     *
     * @param array $payload
     * $payload will have
     * [int task_id, string employee_uid]
     * @return void
     */
    protected function removeMemberTaskLog($payload)
    {
        $employee = $this->employeeRepo->show($payload['employee_uid'], 'id,name,nickname');
        $text = __('global.removedMemberLogText', [
            'removedUser' => $employee->nickname
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'assignMemberTask',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when add new member to task
     *
     * @param array $payload
     * $payload will have
     * [int task_id, string employee_uid]
     * @return void
     */
    protected function assignMemberTaskLog($payload)
    {
        $employee = $this->employeeRepo->show($payload['employee_uid'], 'id,name,nickname');
        $text = __('global.assignMemberLogText', [
            'assignedUser' => $employee->nickname
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'assignMemberTask',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when change task deadline
     *
     * @param array $payload
     * $payload will have
     * [string task_uid]
     * @return void
     */
    protected function updateDeadlineLog($payload)
    {
        $text = __('global.updateDeadlineLogText', [
            'name' => auth()->user()->username
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'updateDeadline',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when change task description
     *
     * @param array $payload
     * $payload will have
     * [string task_uid]
     * @return void
     */
    protected function addDescriptionLog($payload)
    {
        $text = __('global.updateDescriptionLogText', [
            'name' => auth()->user()->username
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'addDescription',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when change task name
     *
     * @param array $payload
     * $payload will have
     * [string task_uid]
     * @return void
     */
    protected function changeTaskNameLog($payload)
    {
        $text = __('global.changeTaskNameLogText', [
            'name' => auth()->user()->username
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask());

        $this->projectTaskLogRepository->store([
            'project_task_id' => $taskId,
            'type' => 'changeTaskName',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when create new task
     *
     * @param array $payload
     * $payload will have
     * [array board, int board_id, array task]
     * @return void
     */
    protected function addNewTaskLog($payload)
    {
        $board = $payload['board'];

        $text = __('global.addTaskText', [
            'name' => auth()->user()->username,
            'boardTarget' => $board['name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task']['id'],
            'type' => 'addNewTask',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when moving a task
     *
     * @param array $payload
     * $payload will have
     * [array boards, collection task, int|string board_id, int|string task_id, int|string board_source_id]
     * @return void
     */
    protected function moveTaskLog($payload)
    {
        // get source board
        $sourceBoard = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_source_id'];
        })->values();

        $boardTarget = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_id'];
        })->values();

        $text = __('global.moveTaskLogText', [
            'name' => auth()->user()->username, 'boardSource' => $sourceBoard[0]['name'], 'boardTarget' => $boardTarget[0]['name']
        ]);

        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'moveTask',
            'text' => $text,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Get list of boards to use in 'move to' action
     *
     * @param integer $boardId
     * @param string $projectUid
     * @return array
     */
    public function getMoveToBoards(int $boardId, string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        $data = $this->boardRepo->list('id,name', 'project_id = ' . $projectId . ' and id != ' . $boardId);

        $data = collect($data)->map(function ($board) {
            return [
                'title' => $board->name,
                'value' => $board->id,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data,
        );
    }

    /**
     * Function to manual change task board
     * 1. Check logged user permission or role
     * 2. If not project manager or manager or super user, return a response to render proof of work form
     * 3. If not, change the task board
     *
     * @param array $data
     * @param string $projectId
     * @return array
     */
    public function manualChangeTaskBoard(array $data, string $projectId): array
    {
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $employeeId = $user->employeeId;

        $superUserRole = getSettingByKey('super_user_role');
        $projectManagerRole = getSettingByKey('project_manager_role');

        if ($roleId != $superUserRole || $roleId != $projectManagerRole) {
            return generalResponse(
                'success',
                false,
                [
                    'show_proof_of_work' => true,
                ],
            );
        }

        return $this->changeTaskBoard($data, $projectId);
    }

    /**
     * Get venues
     *
     * @return array
     */
    public function autocompleteVenue(): array
    {
        $search = request('search');
        $where = '';
        if ($search) {
            $search = strtolower($search);
            $where = "lower(venue) LIKE '%{$search}%' or lower(city_name) LIKE '%{$search}%'";
        }

        $data = $this->repo->list(
            'DISTINCT venue',
            $where
        );

        $data = collect($data)->map(function ($item) {

            return [
                'title' => ucfirst($item->venue),
                'value' => strtolower($item->venue),
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $data,
        );
    }

    /**
     * Get all assigned task
     * If admin is logged in, show all task from all employee
     * If employee is logged in, only show assigned task
     *
     * @return array
     */
    public function getAllTasks(): array
    {
        try {
            // check role
            $su = getSettingByKey('super_user_role');
            $userId = auth()->id();
            $roles = auth()->user()->roles;
            $roleId = $roles[0]->id;
            $employeeId = auth()->user()->employee_id;

            $showPic = false;

            $projectManagerRole = getSettingByKey('project_manager_role');

            $where = '';
            $whereHas = [];
            if ($roleId != $su && $roleId != $projectManagerRole) { // only show assigned task if employee is logged (except super user and project manager)
                // $whereHas[] = [
                //     'relation' => 'pics',
                //     'query' => 'employee_id = ' . $employeeId->id,
                // ];

                $whereHas[] = [
                    'relation' => 'times',
                    'query' => 'employee_id = ' . $employeeId,
                ];

                $showPic = true;
            } else {
                if ($projectManagerRole == $roleId) {
                    $projectPicIds = $this->projectPicRepository->list('project_id', 'pic_id = ' . $employeeId);
                    $projectIds = collect($projectPicIds)->pluck('project_id')->toArray();
                    $projectIds = implode("','", $projectIds);
                    $projectIds = "'" . $projectIds;
                    $projectIds .= "'";

                    $where = "project_id in ({$projectIds})";
                }
            }

            if (!empty(request('project_id'))) { // override where clause project id
                $projectIds = collect(request('project_id'))->map(function ($item) {
                    $projectId = getIdFromUid($item, new \Modules\Production\Models\Project());

                    return $projectId;
                })->toArray();

                $projectIds = implode("','", $projectIds);
                $projectIds = "'" . $projectIds;
                $projectIds .= "'";
                $where = "project_id in ({$projectIds})";
            }

            if (!empty(request('task_name'))) {
                $taskName = request('task_name');
                if (empty($where)) {
                    $where = "lower(name) LIKE '%{$taskName}%'";
                } else {
                    $where .= " and lower(name) LIKE '%{$taskName}%'";
                }
            }

            $data = $this->taskRepo->list(
                'id,uid,project_id,project_board_id,name,task_type,end_date,status',
                $where,
                [
                    'project:id,name,project_date',
                    'medias',
                    'taskLink',
                    'board:id,name,based_board_id',
                    'pics:id,project_task_id,employee_id',
                    'pics.employee:id,name,nickname'
                ],
                $whereHas
            );

            $onProgress = getSettingByKey('board_start_calculated');
            $backlog = getSettingByKey('board_as_backlog');
            $checkByPm = getSettingByKey('board_to_check_by_pm');
            $checkByClient = getSettingByKey('board_to_check_by_client');
            $revise = getSettingByKey('board_revise');
            $completed = getSettingByKey('board_completed');

            $output = [];
            $taskStatuses = \App\Enums\Production\TaskStatus::cases();
            foreach ($data as $task) {
                $attachments = $task->medias->count();
                $comments = 0;

                foreach ($taskStatuses as $taskStatus) {
                    if ($taskStatus->value == $task->status) {
                        $statusText = $taskStatus->label();
                    }
                }

                $statusColor = 'success'; // on progress
                if ($task->board->based_board_id == $backlog) {
                    $statusColor = 'grey-lighten-1';
                } else if ($task->board->based_board_id == $checkByPm) {
                    $statusColor = 'primary';
                } else if ($task->board->based_board_id == $checkByClient) {
                    $statusColor = 'light-blue-lighten-3';
                } else if ($task->board->based_board_id == $revise) {
                    $statusColor = 'red-darken-1';
                } else if ($task->board->based_board_id == $completed) {
                    $statusColor = 'blue-darken-2';
                }

                $projectDate = new DateTime($task->project->project_date);
                $diff = date_diff($projectDate, new DateTime('now'));
                $daysToGo = $diff->d . ' ' . __('global.day');

                $pics = collect($task->pics)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->nickname,
                    ];
                })->toArray();

                $output[] = [
                    'uid' => $task->uid,
                    'task_name' => $task->name,
                    'attachments' => $attachments,
                    'comments' => $comments,
                    'project' => $task->project->name,
                    'project_date' => date('d F Y', strtotime($task->project->project_date)),
                    'status_text' => $statusText ?? '-',
                    'status_color' => $statusColor,
                    'days_to_go' => $daysToGo,
                    'pics' => $pics,
                    'due_date' => $task->end_date ? date('d F Y', strtotime($task->end_date)) : '-',
                    'show_pic' => $showPic,
                ];
            }

            return generalResponse(
                'success',
                false,
                $output,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function detailTask(string $uid)
    {
        $task = $this->taskRepo->show($uid, 'project_id', ['project:id,uid']);

        $this->show($task->project->uid);

        $currentData = getCache('detailProject' . $task->project_id);

        $boards = $currentData['boards'];

        $selectedTask = [];
        foreach ($boards as $board) {
            foreach ($board['tasks'] as $task) {
                if ($task['uid'] == $uid) {
                    $selectedTask = $task;
                }
            }
        }

        return generalResponse(
            'success',
            false,
            [
                'task' => $selectedTask,
                'full_detail' => $currentData,
            ],
        );
    }

    /**
     * Function to get marketing list
     * This is used in project form
     * Result should have marketing position + directors
     *
     * @return array
     */
    public function getMarketingListForProject(): array
    {
        $user = auth()->user();

        $positionAsMarketing = getSettingByKey('position_as_marketing');
        $positionAsDirectors = json_decode(getSettingByKey('position_as_directors'), true);

        if ($positionAsDirectors) {
            $combine = array_merge($positionAsDirectors, [$positionAsMarketing]);
        } else {
            $combine = [$positionAsMarketing];
        }

        $combine = implode("','", $combine);
        $condition = "'" . $combine;
        $condition .= "'";

        $positions = $this->positionRepo->list('id', "uid in ({$condition})");

        $positionIds = collect($positions)->pluck('id')->all();
        $combinePositionIds = implode(',', $positionIds);

        $where = "position_id in ({$combinePositionIds}) and status != " . \App\Enums\Employee\Status::Inactive->value;
        $marketings = $this->employeeRepo->list('id,uid,name', $where);

        $marketings = collect((object) $marketings)->map(function ($item) use ($user) {
            $item['selected'] = false;
            if (
                ($user->employee_id) &&
                ($user->employee_id == $item->id)
            ) {
                $item['selected'] = true;
            }

            return $item;
        });

        return generalResponse(
            'success',
            false,
            $marketings->toArray(),
        );
    }

    /**
     * Function to approve task based on authenticate user
     *
     * @param string @projectUid
     * @param string $taskUid
     */
    public function approveTask(string $projectUid, string $taskUid, bool $isFromTelegram = false)
    {
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
            $employeeId = auth()->user()->employee_id;

            $isDirector = isDirector();
            if ($isDirector) { // get the real employee id
                $realPic = $this->taskPicRepo->show(0, 'employee_id', [], 'project_task_id = ' . $taskId);
                $employeeId = $realPic->employee_id;
            }

            $this->taskPicRepo->update([
                'status' => \App\Enums\Production\TaskPicStatus::Approved->value,
                'approved_at' => Carbon::now(),
            ], 'dummy', 'employee_id = ' . $employeeId . ' and project_task_id = ' . $taskId);

            // change task status to on progress
            $this->taskRepo->update([
                'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
            ], 'dummy', 'id = ' . $taskId);

            // update task worktime if meet the requirements
            // $board = $this->boardRepo->show($task->project_board_id);
            $this->setTaskWorkingtime($taskId, $employeeId, \App\Enums\Production\WorkType::OnProgress->value);

            // update cache
            $currentData = getCache('detailProject' . $projectId);

            $task = $this->formattedDetailTask($taskUid);

            $boards = $this->formattedBoards($projectUid);
            $currentData['boards'] = $boards;

            $currentData['boards'] = $boards;

            storeCache('detailProject' . $projectId, $currentData);

            $currentData = $this->formatTasksPermission($currentData, $projectId);

            return generalResponse(
                __('global.taskHasBeenApproved'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Revise given task
     * Change task status to revise (refer \App\Enums\Production\TaskStatus.php)
     * Notify current user
     * Upload revise reason
     * Send task to current board
     *
     * $data will have information like:
     * string reason -> required
     * blob file -> nullable
     *
     * @param array $data
     * @param string $projectUid
     * @param string $taskUid
     * @return array
     */
    public function reviseTask(array $data, string $projectUid, string $taskUid): array
    {
        $tmpFile = [];
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());

        DB::beginTransaction();
        try {
            // upload file
            if (isset($data['file'])) {
                foreach ($data['file'] as $file) {
                    $tmpFile[] = uploadImageandCompress(
                        "projects/{$projectId}/task/{$taskId}/revise",
                        10,
                        $file
                    );
                }
            }

            $this->taskReviseHistoryRepo->store([
                'project_task_id' => $taskId,
                'project_id' => $projectId,
                'reason' => $data['reason'],
                'file' => json_encode($tmpFile),
                'revise_by' => auth()->user()->employee_id,
            ]);

            // this variable is to alert current pics
            $currentTaskData = $this->taskRepo->show($taskUid, 'current_pics,current_board,project_board_id');
            $currentPics = json_decode($currentTaskData->current_pics, true);
            $currentPicUids = [];
            foreach ($currentPics as $currentPic) {
                $employee = $this->employeeRepo->show('dummy', 'id,uid', [], 'id = ' . $currentPic);
                $currentPicUids[] = $employee->uid;
            }

            $currentTaskPics = $this->taskPicRepo->list('employee_id', 'project_task_id = ' . $taskId, ['employee:id,uid']);

            $this->taskRepo->update([
                'status' => \App\Enums\Production\TaskStatus::Revise->value,
//                'project_board_id' => $currentTaskData->current_board,
                'current_board' => null,
            ], $taskUid);

            // update worktime log for project manager
            foreach ($currentTaskPics as $currentPM) {
                $this->setTaskWorkingTime($taskId, $currentPM->employee_id, \App\Enums\Production\WorkType::Finish->value);
            }

            // detach project manager
            $this->detachTaskPic(
                collect($currentTaskPics)->pluck('employee.uid')->toArray(),
                $taskId
            );

            // assign current employee pic to task
            $this->assignMemberToTask(
                [
                    'users' => $currentPicUids,
                    'removed' => [],
                ],
                $taskUid,
                false,
                true,
            );

            // update worktime for employee
            foreach ($currentPics as $currentPic) {
                $this->setTaskWorkingTime($taskId, $currentPic, \App\Enums\Production\WorkType::Assigned->value);
            }

            //update cache and finishing process
            $task = $this->formattedDetailTask($taskUid);

            $currentData = getCache('detailProject' . $projectId);

            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            $currentData = $this->formatTasksPermission($currentData, $projectId);

            DB::commit();

            return generalResponse(
                __('global.reviseIsUpload'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ],
            );
        } catch (\Throwable $th) {
            if ($tmpFile) {
                $path = storage_path("app/public/projects/{$projectId}/task/{$taskId}/revise/{$tmpFile}");
                deleteImage($path);
            }

            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to complete the task
     * Project task status will be complete (refer to \App\Enums\Production\TaskStatus.php)
     * Set working time to finish (refer to \App\Enums\Production\WorkType.php)
     * Detach ALL PIC
     *
     * @param string $projectUid
     * @param string $taskUid
     * @return array
     */
    public function markAsCompleted(string $projectUid, string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            // this variable is to alert current pics which is the worker
            $currentTaskData = $this->taskRepo->show($taskUid, 'current_pics,current_board,project_board_id');
            $currentPics = json_decode($currentTaskData->current_pics, true);
            $currentPicIds = [];
            foreach ($currentPics as $currentPic) {
                $employee = $this->employeeRepo->show('dummy', 'id,uid', [], 'id = ' . $currentPic);
                $currentPicIds[] = $employee->id;
            }

            $currentPic = $this->taskPicRepo->list('employee_id', 'project_task_id = ' . $taskId, ['employee:id,uid']);

            // change worktime status of Project Manager
            foreach ($currentPic as $pic) {
                $this->setTaskWorkingTime($taskId, $pic->employee_id, \App\Enums\Production\WorkType::Finish->value);
            }

            // move task to next board
            $taskDetail = $this->taskRepo->show($taskUid, 'id,project_board_id');
            $sourceBoardId = $taskDetail->project_board_id;

            // get next board
            $boardList = $this->boardRepo->list('id,name', 'project_id = ' . $projectId);
            foreach ($boardList as $keyBoard => $boardData) {
                if ($boardData->id == $sourceBoardId) {
                    if (isset($boardList[$keyBoard + 1])) {
                        $boardId = $boardList[$keyBoard + 1]->id;
                        break;
                    } else {
                        $boardId = $sourceBoardId;
                        break;
                    }
                }
            }

            $setCurrentPic = true;
            $this->changeTaskBoardProcess(
                [
                    'board_id' => $boardId,
                    'task_id' => $taskUid,
                    'board_source_id' => $sourceBoardId,
                ],
                $projectUid,
                \App\Enums\Production\TaskStatus::CheckByPm->value,
                $setCurrentPic
            );

            // detach current pic which is project manager
            $this->detachTaskPic(
                collect($currentPic)->pluck('employee.uid')->toArray(),
                $taskId,
            );

            // update task status
            $this->taskRepo->update([
                'status' => \App\Enums\Production\TaskStatus::Completed->value,
            ], $taskUid);

            $task = $this->formattedDetailTask($taskUid);

            $currentData = getCache('detailProject' . $projectId);

            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            $currentData = $this->formatTasksPermission($currentData, $projectId);

            \Modules\Production\Jobs\TaskIsCompleteJob::dispatch($currentPicIds, $taskId)->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.taskIsCompletedAndContinue'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    public function getProjectCalendars()
    {
        $where = '';
        if (request('search_date')) {
            $searchDate = date('Y-m-d', strtotime(request('search_date')));
        } else {
            $searchDate = date('Y-m-d');
        }

        $year = date('Y', strtotime($searchDate));
        $month = date('m', strtotime($searchDate));
        $start = $year . '-' . $month . '-01';
        $end = $year . '-' . $month . '-30';
        $where = "project_date >= '" . $start . "' and project_date <= '" . $end . "'";

        $data = $this->repo->list('id,uid,name,project_date,venue', $where, [
            'personInCharges:id,project_id,pic_id',
            'personInCharges.employee:id,uid,name',
        ], [], 'project_date ASC');

        $out = [];
        foreach ($data as $projectKey => $project) {
            $pics = collect($project->personInCharges)->pluck('employee.name')->toArray();
            $pic = implode(', ', $pics);
            $project['pic'] = $pic;
            $project['project_date_text'] = date('d F Y', strtotime($project->project_date));

            $out[] = [
                'key' => $project->uid,
                'highlight' => 'indigo',
                'project_date' => $project->project_date,
                'dot' => false,
                'popover' => [
                    'label' => $project->name,
                ],
                'dates' => date('d F Y', strtotime($project->project_date)),
                'order' => $projectKey,
                'customData' => $project,
            ];
        }

        // grouping by date (for custom data)
        $grouping = collect($out)->groupBy('project_date')->all();

        return generalResponse(
            'success',
            false,
            [
                'events' => $out,
                'group' => $grouping,
            ],
        );
    }

    /**
     * Get boards of selected project
     *
     * @param string $projectUid
     * @return array
     */
    public function getProjectBoards(string $projectUid): array
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $data = $this->boardRepo->list('id as value,name as title', 'project_id = ' . $projectId);

        return generalResponse(
            'success',
            false,
            $data->toArray(),
        );
    }

    public function getProjectTeamsForTask(string $projectUid): array
    {
        $project = $this->repo->show($projectUid, 'id,uid,event_type,classification,name,project_date');

        $projectTeams = $this->getProjectTeams($project);
        $teams = $projectTeams['teams'];
        $pics = $projectTeams['pics'];

        return generalResponse(
            'success',
            false,
            $teams,
        );
    }

    public function getProjectStatusses(string $projectUid)
    {
        $project = $this->repo->show($projectUid, 'status');

        $data = \App\Enums\Production\ProjectStatus::cases();

        $out = [];
        foreach ($data as $status) {
            if ($project->status != $status->value) {
                $out[] = [
                    'value' => $status->value,
                    'title' => $status->label(),
                ];
            }
        }

        return generalResponse(
            'success',
            false,
            $out,
        );
    }

    public function changeStatus(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $this->repo->update(['status' => $data['status']], $projectUid);

            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            if ($data['base_status'] == \App\Enums\Production\ProjectStatus::Draft->value && $data['status'] == \App\Enums\Production\ProjectStatus::OnGoing->value) {
                // get task pic with status task is waiting approval
                // then send a notification

                $tasks = $this->taskRepo->list('id,project_id', 'project_id = ' . $projectId, ['pics']);

                foreach ($tasks as $task) {
                    $employeeIds = collect($task->pics)->pluck('employee_id')->toArray();

                    \Modules\Production\Jobs\AssignTaskJob::dispatch($employeeIds, $task->id);
                }
            }

            $project = $this->repo->show($projectUid, 'id,status');

            $currentData = getCache('detailProject' . $projectId);
            if ($currentData) {
                $currentData['status_raw'] = $project->status;
                $currentData['status'] = $project->status_text;
                $currentData['status_color'] = $project->status_color;

                $currentData = $this->formatTasksPermission($currentData, $projectId);
            }

            DB::commit();

            return generalResponse(
                __('global.statusIsChanged'),
                false,
                [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get PIC and task list for request team member component
     */
    public function getTargetPicsAndTaskList(string $projectUid)
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $superUserRole = getSettingByKey('super_user_role');
            $user = auth()->user();
            $roles = $user->roles;
            $roleId = $roles[0]->id;

            $projectManagerPosition = json_decode(getSettingByKey('position_as_project_manager'), true);

            $where = '';

            if (count($projectManagerPosition) > 0) {
                $projectManagerPosition = collect($projectManagerPosition)->map(function ($item) {
                    return getIdFromUid($item, new \Modules\Company\Models\Position());
                })->toArray();

                $positionIds = implode("','", $projectManagerPosition);
                $positionIds = "('" . $positionIds . "')";

                // condition when super admin take this role
                $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = ' . $projectId);
                $picIds = collect($projectPics)->pluck('pic_id')->toArray();
                $adminCondition = implode("','", $picIds);
                $adminCondition = "('" . $adminCondition . "')";

                $where = 'position_id in ' . $positionIds . " and id not in " . $adminCondition;

                if ($roleId != $superUserRole) {
                    $where = 'position_id in ' . $positionIds . ' and id != ' . $user->employee_id;
                }
            }

            // exclude pm entertainment
            $userAsPMEntertainment = \App\Models\User::role('project manager entertainment')
                ->get();
            $PMEntertainmentId = collect($userAsPMEntertainment)->pluck('employee_id')
                ->toArray();
            $PMEntertainmentCondition = implode(',', $PMEntertainmentId);
            $where .= " and id NOT IN ({$PMEntertainmentCondition})";

            $data = $this->employeeRepo->list('id,uid,name,email', $where);
            $data = collect($data)->map(function ($item) {
                return [
                    'value' => $item->uid,
                    'title' => $item->name,
                ];
            })->toArray();

            // get task

            $projects = $this->taskRepo->list('id,uid,name', 'project_id = ' . $projectId);
            $projects = collect($projects)->map(function ($item) {
                return [
                    'value' => $item->uid,
                    'title' => $item->name,
                ];
            })->toArray();

            $output = [
                'tasks' => $projects,
                'pics' => $data,
            ];

            return generalResponse(
                'success',
                false,
                $output,
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to get pic teams for request team member
     *
     * @param string $projectUid
     * @param string $picUid
     * @return array
     */
    public function getPicTeams(string $projectUid, string $picUid): array
    {
        try {
            $bossId = getIdFromUid($picUid, new \Modules\Hrd\Models\Employee());

            // $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $project = $this->repo->show($projectUid, 'id,name,status,project_date');

            $projectDate = $project->project_date;

            $startDate = date('Y-m-d', strtotime('-7 days', strtotime($projectDate)));
            $endDate = date('Y-m-d', strtotime('+7 days', strtotime($projectDate)));

            $taskDateCondition = "project_date >= '" . $startDate . "' and project_date <= '" . $endDate . "'";

            // get boss user data and role
            // make special condition for PM Entertaintment
            $bossData = \App\Models\User::where('employee_id', $bossId)->first();

            if (!$bossData) {
                throw new NotRegisteredAsUser();
            }

            $bossIsPMEntertainment = false;
            if ($bossData->hasRole('project manager entertainment')) {
                $bossIsPMEntertainment = true;
            }

            // get production and operator position
            $productionPosition = json_decode(getSettingByKey('position_as_production'), true);
            $operatorPosition = json_decode(getSettingByKey('position_as_visual_jokey'), true);

            if (!$productionPosition || !$operatorPosition) {
                throw new failedToProcess(__('notification.failedToGetTeams'));
            }

            if ($bossIsPMEntertainment) {
                $operatorPosition = collect($operatorPosition)->map(function ($item) {
                    return getIdFromUid($item, new \Modules\Company\Models\Position());
                })->toArray();
                $positionCondition = "'";
                $positionCondition .= implode("','", $operatorPosition) . "'";
            } else {
                $productionPosition = collect($productionPosition)->map(function ($item) {
                    return getIdFromUid($item, new \Modules\Company\Models\Position());
                })->toArray();
                $positionCondition = "'";
                $positionCondition .= implode("','", $productionPosition) . "'";
            }

            $where = "boss_id = {$bossId} and status != " . Status::Inactive->value . " and position_id IN ({$positionCondition})";
            $userApp = auth()->user();

            if (($userApp) && ($userApp->employee_id) && !$bossIsPMEntertainment) {
                $where .= " and id != " . $userApp->employee_id;
            }

            if ($bossIsPMEntertainment) {
                $where .= " or id = " . $bossId;
            }

            $data = $this->employeeRepo->list('id,uid,name,email', $where);

            $output = collect($data)->map(function ($item) use ($projectDate, $taskDateCondition) {
                $taskOnProjectDate = $this->taskPicRepo->list(
                    'id,project_task_id',
                    'employee_id = ' . $item->id,
                    [
                        'task' => function ($query) use ($taskDateCondition) {
                            $query->selectRaw('id,project_id')
                                ->whereHas('project', function ($q) use ($taskDateCondition) {
                                    $q->whereRaw($taskDateCondition);
                                });
                        }
                    ]
                );

                $taskOnProjectDate = collect($taskOnProjectDate)->filter(function ($filter) {
                    return $filter->task;
                })->toArray();

                return [
                    'value' => $item->uid,
                    'title' => $item->name,
                    'task' => __('global.countTaskOnSelectedDate', ['count' => count($taskOnProjectDate), 'date' => date('d F Y', strtotime($projectDate))]),
                ];
            })->toArray();

            return generalResponse(
                'success',
                false,
                $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function loanTeamMember(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $project = $this->repo->show($projectUid, 'id,name,project_date', ['personInCharges:id,project_id,pic_id']);

            $requestTo = getIdFromUid($data['pic_id'], new \Modules\Hrd\Models\Employee());

            $user = auth()->user();
            $roles = $user->roles;
            $roleId = $roles[0]->id;

            if ($roleId == getSettingByKey('super_user_role')) {
                $requestedBy = $project->personInCharges[0]->pic_id;
            } else {
                $requestedBy = $user->employee_id;
            }

            foreach ($data['teams'] as $team) {
                $teamId = getIdFromUid($team, new \Modules\Hrd\Models\Employee());
                $transferId = $this->transferTeamRepo->store([
                    'project_id' => $projectId,
                    'employee_id' => $teamId,
                    'reason' => $data['reason'],
                    'request_to' => $requestTo,
                    'project_date' => $project->project_date,
                    'status' => \App\Enums\Production\TransferTeamStatus::Requested->value,
                    'requested_by' => $requestedBy,
                    'request_at' => Carbon::now(),
                ]);

                \Modules\Production\Jobs\RequestTeamMemberJob::dispatch($projectId, [
                    'transferId' => $transferId->id,
                    'team' => $teamId,
                    'pic_id' => $data['pic_id'],
                ])->afterCommit();
            }

            DB::commit();

            return generalResponse(
                __('global.teamRequestIsSent'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function uploadShowreels(array $data, string $projectUid)
    {
        $tmpFile = null;
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        try {
            // get current showreel
            $project = $this->repo->show($projectUid, 'id,showreels');
            $currentShowreels = $project->showreels;

            $tmpFile = uploadFile(
                'projects/' . $projectId . '/showreels',
                $data['file']
            );

            $this->repo->update([
                'showreels' => $tmpFile,
            ], $projectUid);

            $currentData = getCache('detailProject' . $projectId);

            $currentData = $this->formatTasksPermission($currentData, $projectId);

            // delete current showreels
            if ($currentShowreels) {
                if (is_file(storage_path('app/public/projects/' . $projectId . '/showreels/' . $currentShowreels))) {
                    unlink(
                        storage_path('app/public/projects/' . $projectId . '/showreels/' . $currentShowreels)
                    );
                }
            }

            return generalResponse(
                __('global.showreelsIsUploaded'),
                false,
                [
                    'full_detail' => $currentData,
                ],
            );
        } catch (\Throwable $th) {
            if ($tmpFile) {
                if (is_file(storage_path("app/public/projects/{$projectId}/showreels/$tmpFile"))) {
                    unlink(storage_path("app/public/projects/{$projectId}/showreels/$tmpFile"));
                }
            }

            return errorResponse($th);
        }
    }

    public function getTaskTeamForReview(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $project = $this->repo->show($projectUid, 'id,uid,event_type,classification,name,project_date');

        $projectTeams = $this->getProjectTeams($project);
        $teams = $projectTeams['teams'];
        $pics = $projectTeams['picUids'];

        $histories = $this->taskPicHistory->list('id,project_id,project_task_id,employee_id', 'project_id = ' . $projectId, ['employee:id,name,employee_id,uid,nickname']);

        $data = collect((object) $histories)->map(function ($item) {
            return [
                'id' => $item->id,
                'employee' => $item->employee->name . ' (' . $item->employee->employee_id . ')',
                'employee_uid' => $item->employee->uid,
                'project_id' => $item->project_id,
                'project_task_id' => $item->project_task_id,
                'employee_id' => $item->employee_id,
            ];
        })
        ->groupBy('employee_id')
        ->toArray();

        $output = [];
        foreach ($data as $employeeId => $employee) {
            $output[$employeeId] = [
                'uid' => $employee[0]['employee_uid'],
                'name' => $employee[0]['employee'],
                'total_task' => count($employee),
                'point' => count($employee),
                'additional_point' => 0,
                'can_decrease_point' => false,
                'can_increase_point' => true
            ];
        }

        $rawData = collect($output)->values()->pluck('uid')->toArray();

        foreach ($teams as $team) {
            if (!in_array($team['uid'], $rawData)) {
                array_push($output, [
                    'uid' => $team['uid'],
                    'name' => $team['name'],
                    'total_task' => 0,
                    'point' => 0,
                    'additional_point' => 0,
                    'can_decrease_point' => false,
                    'can_increase_point' => true
                ]);
            }
        }

        // remove pic in list
        $output = collect($output)->filter(function ($filter) use ($pics) {
            return !in_array($filter['uid'], $pics);
        })->values()->toArray();

        return generalResponse(
            'success',
            false,
            $output,
        );
    }

    /**
     * Function to create a review in project and each teams
     *
     * $data is:
     * string feedback
     * array points
     *
     * @param array $data
     * @param string $projectUid
     * @return array
     */
    public function completeProject(array $data, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            foreach ($data['points'] as $point) {
                $this->employeeTaskPoint->store([
                    'employee_id' => getIdFromUid($point['uid'], new \Modules\Hrd\Models\Employee()),
                    'project_id' => $projectId,
                    'point' => $point['point'] - $point['additional_point'],
                    'additional_point' => $point['additional_point'],
                    'total_point' => $point['point'],
                    'total_task' => $point['total_task'],
                    'created_by' => auth()->user()->employee_id ?? 0,
                ]);
            }

            $this->repo->update([
                'feedback' => $data['feedback'],
                'status' => \App\Enums\Production\ProjectStatus::Completed->value
            ], $projectUid);

            // update project equipment
            $this->projectEquipmentRepo->update([
                'status' => \App\Enums\Production\RequestEquipmentStatus::CompleteAndNotReturn->value
            ], 'dummy', 'project_id = ' . $projectId);

            // update project status cache
            $project = $this->repo->show($projectUid, 'id,status');

            $currentData = getCache('detailProject' . $projectId);
            $currentData['feedback'] = $data['feedback'];
            $currentData['status_raw'] = $project->status;
            $currentData['status'] = $project->status_text;
            $currentData['status_color'] = $project->status_color;

            $currentData = $this->formatTasksPermission($currentData, $projectId);

            DB::commit();

            return generalResponse(
                __('global.projectIsCompleted'),
                false,
                [
                    'full_detail' => $currentData,
                ],
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function assignVJ(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            DB::commit();

            $project = $this->repo->show($projectUid, 'id');

            $project->vjs()->createMany(
                collect($data['employee_id'])->map(function ($item) {
                    return [
                        'employee_id' => getIdFromUid($item, new \Modules\Hrd\Models\Employee()),
                        'created_by' => auth()->user()->employee_id ?? 0,
                    ];
                })->toArray()
            );

            \Modules\Production\Jobs\AssignVjJob::dispatch($project, $data)->afterCommit();

            DB::commit();

            return generalResponse(
                __("global.vjHasBeenAssigned"),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to get all items for final check
     *
     * @param string $projectUid
     * @return array
     */
    public function prepareFinalCheck(string $projectUid): array
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $project = $this->repo->show($projectUid, 'id,name,showreels,showreels_status', [
                'vjs.employee:id,nickname',
                'equipments:id,project_id,inventory_id,qty,status',
                'equipments.inventory:id,name'
            ]);

            // get tasks information
            $tasks = $this->taskRepo->list('id,project_id,status', 'project_id = ' . $projectId . ' and status is not null');
            $completedTask = collect($tasks)->where('status', '=', \App\Enums\Production\TaskStatus::Completed->value)->count();
            $unfinished = $tasks->count() - $completedTask;
            $taskData = [
                'total' => $tasks->count(),
                'completed' => $completedTask,
                'unfinished' => $tasks->count() - $completedTask,
                'text' => __("global.reviewTaskData", ['total' => $tasks->count(), 'unfinished' => $unfinished]),
            ];

            // get showreels status
            $showreels = [
                'text' => __("global.doesNotHaveShowreels"),
            ];
            if ($project->showreels) {
                $showreelsStatus = \App\Enums\Production\ShowreelsStatus::cases();
                foreach ($showreelsStatus as $st) {
                    if ($st->value == $project->showreels_status) {
                        $showreels['text'] = $st->label();
                        break;
                    }
                }
            }

            // Get vj
            $vj = [
                'text' => __('global.doesNotHaveVJ'),
            ];
            if ($project->vjs->count()) {
                $inchargeVj = collect($project->vjs)->pluck('employee.nickname')->toArray();
                $vj['text'] = __('global.inchargeVJAre', ['name' => implode(',', $inchargeVj)]);
            }

            // Get equipment
            $inventories = [];
            foreach ($project->equipments as $equipment) {
                $inventories[] = [
                    'name' => $equipment->inventory->name,
                    'status' => $equipment->status_text,
                    'status_color' => $equipment->status_color,
                    'qty' => $equipment->qty,
                ];
            }

            return generalResponse(
                'success',
                false,
                [
                    'tasks' => $taskData,
                    'showreels' => $showreels,
                    'vj' => $vj,
                    'inventories' => $inventories,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function readyToGo(string $projectUid)
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $equipments = $this->projectEquipmentRepo->list('id,inventory_id,inventory_code', 'project_id = ' . $projectId);

            foreach ($equipments as $equipment) {
                $this->inventoryItemRepo->update([
                    'status' => \App\Enums\Inventory\InventoryStatus::OnSite->value,
                    'current_location' => \App\Enums\Inventory\Location::Outgoing->value,
                ], 'dummy', "inventory_code = '" . $equipment->inventory_code . "'");
            }

            // update equipment status
            $this->projectEquipmentRepo->update([
                'status' => \App\Enums\Production\RequestEquipmentStatus::OnEvent->value
            ], 'dummy', 'project_id = ' . $projectId);

            $this->repo->update([
                'status' => \App\Enums\Production\ProjectStatus::ReadyToGo->value,
            ], $projectUid);


            DB::commit();

            return generalResponse(
                __('global.projectIsGoodToGo'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Return equipment after event is completed
     *
     * @param string $projectUid
     * @param array $payload
     * @return array
     */
    public function returnEquipment(string $projectUid, array $payload): array
    {
        DB::beginTransaction();
        try {
            foreach ($payload['equipment'] as $item) {
                $this->projectEquipmentRepo->update([
                    'status' => \App\Enums\Production\RequestEquipmentStatus::Return->value,
                    'is_good_condition' => $item['return_condition']['is_good_condition'],
                    'detail_condition' => !$item['return_condition']['is_good_condition'] ? $item['return_condition']['detail_condition'] : null,
                    'is_returned' => true,
                ], $item['uid']);
            }

            \Modules\Production\Jobs\ReturnEquipmentJob::dispatch($projectUid)->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.equipmentHasBeenReturned'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function downloadReferences(string $projectUid)
    {
        $project = $this->repo->show($projectUid, 'id,name', ['references']);
        $projectId = $project->id;

        $references = collect($project->references)->filter(function ($item) use ($projectId) {
            return $item->type != 'link';
        })->map(function ($mapping) use ($projectId) {
            return storage_path('app/public/projects/references/' . $projectId . '/' . $mapping->media_path);
        })->values();

        return [
            'files' => $references->toArray(),
            'project' => $project,
        ];
    }

    /**
     * Get All PIC / Project Manager and get each workload
     * Provide all information to user
     *
     */
    public function getPicScheduler(string $projectUid)
    {
        try {
            $output = $this->mainProcessToGetPicScheduler($projectUid);

            return generalResponse(
                'success',
                false,
                $output,
            );
        } catch (\Throwable $e) {
            return errorResponse($e);
        }
    }

    /**
     * Function to get PIC Scheduler, This is composeable function
     *
     * @param string $projectUid
     *
     * @return array
     */
    protected function mainProcessToGetPicScheduler(string $projectUid): array
    {
        $project = $this->repo->show($projectUid, 'id,name,project_date');
        $startDate = date('Y-m-d', strtotime('-7 days', strtotime($project->project_date)));
        $endDate = date('Y-m-d', strtotime('+7 days', strtotime($project->project_date)));

        $userPics = \App\Models\User::role('project manager')->get();
        $assistant = \App\Models\User::role('assistant manager')->get();
        $director = \App\Models\User::role('director')->get();
        $pics = collect($userPics)->merge($director)->merge($assistant)->toArray();

        // get all workload in each pics
        $output = [];
        foreach ($pics as $key => $pic) {
            if ($pic['employee_id']) {
                $employee = $this->employeeRepo->show('dummy', 'id,uid,name,email,employee_id', [], 'id = ' . $pic['employee_id']);

                $output[$key] = [
                    'id' => $employee->uid,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'employee_id' => $employee->employee_id,
                    'projects' => $this->getPicWorkload($employee, $projectUid),
                    'is_recommended' => false,
                ];
            }
        }

        return $output;
    }

    /**
     * Get each PM workload (This data used in assign PIC dialog)
     *
     * @param object $pic
     * @param string $projectUId
     *
     * @return array
     */
    protected function getPicWorkload($pic, $projectUid): array
    {
        $project = $this->repo->show($projectUid, 'id,name,project_date');
        $startDate = date('Y-m-d', strtotime('-7 days', strtotime($project->project_date)));
        $endDate = date('Y-m-d', strtotime('+7 days', strtotime($project->project_date)));

        $surabaya = \Modules\Company\Models\City::selectRaw('id')
            ->whereRaw("lower(name) like 'kota surabaya' or lower(name) like 'surabaya'")
            ->get();

        $projects = $this->repo->list(
            'id,name,project_date,city_id,classification',
            "project_date between '{$startDate}' and '{$endDate}'",
            [],
            [
                [
                    'relation' => 'personInCharges',
                    'query' => 'pic_id = ' . $pic->id
                ]
            ]
        );

        // group by some data like out of town, total project and event class
        $eventClass = 0;
        $totalOfProject = 0;
        $totalOutOfTown = 0;

        if (count($projects) > 0) {
            $totalOfProject = count($projects);

            // get total event class
            $eventClass = collect((object)$projects)->pluck('classification')->filter(function($itemClass) {
                return strtolower($itemClass) == 's (spesial)' || strtolower($itemClass) == 's (special)';
            })->count();

            foreach ($projects as $project) {
                if (!in_array($project->city_id, collect($surabaya)->pluck('id')->toArray())) {
                    $totalOutOfTown++;
                }
            }
        }

        return [
            'traveled' => __('global.timesTraveledInWeek', ['count' => $totalOutOfTown]),
            'projects' => __('global.totalProjectInWeek', ['count' => $totalOfProject]),
            'event_class' => __('global.projectClassInWeek', ['count' => $eventClass]),
        ];
    }

    /**
     * Store assign pic to selected project
     *
     * @param string $projectUid
     * @param array<array, string> $data
     *
     */
    public function assignPic(string $projectUid, array $data)
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $this->handleAssignPicLogic($data, $projectUid, $projectId);

            // update cache
            if ($currentData = getCache('detailProject' . $projectId)) {
                // new pics
                $newPics = $this->projectPicRepository->list('pic_id', "project_id = {$projectId}", ['employee:id,uid,name']);

                $currentData['pic'] = implode(',', collect($newPics)->pluck('employee.name')->toArray());
                $currentData['pic_ids'] = collect($newPics)->pluck('employee.uid')->toArray();

                $currentData = $this->formatTasksPermission($currentData, $projectId);
            }

            DB::commit();

            return generalResponse(
                __('global.successAssignPIC'),
                false,
                [
                    'full_detail' => $currentData,
                ],
            );
        } catch (\Throwable $error) {
            DB::rollBack();
            return errorResponse($error);
        }
    }

    /**
     * Main function to handle assignation PIC to selected project
     * @param array<string, array<string>> $data
     * @param string $projectUid
     * @param int $projectId
     *
     * @return void
     *
     */
    protected function handleAssignPicLogic(array $data, string $projectUid, int $projectId): void
    {
        foreach ($data['pics'] as $pic) {
            $employeeId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee());
            $this->projectPicRepository->store(['pic_id' => $employeeId, 'project_id' => $projectId]);
        }

        \Modules\Production\Jobs\NewProjectJob::dispatch($projectUid)->afterCommit();
    }

    /**
     * Remove all selected pic form selected project
     *
     * @param araray<string> $picList
     * @param string $projectUid
     *
     * @return void
     */
    protected function removePicProject(array $picList, string $projectUid, int $projectId): void
    {
        $ids = [];
        foreach ($picList as $list) {
            $employeeId = getIdFromUid($list, new \Modules\Hrd\Models\Employee());
            $ids[] = $employeeId;
            $this->projectPicRepository->delete(0, "project_id = {$projectId} and pic_id = {$employeeId}");
        }

        // notified removed user
        \Modules\Production\Jobs\RemovePMFromProjectJob::dispatch($ids, $projectUid)->afterCommit();
    }

    /**
     * Assign new pic or remove current pic of project
     *
     * @param string $projectUid
     * @param array<string, array<string>> $data
     *
     */
    public function subtitutePic(string $projectUid, array $data): array
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            // handle new pic
            if (count($data['pics']) > 0) {
                $this->handleAssignPicLogic($data, $projectUid ,$projectId);
            }

            // handle removed pic
            if (count($data['removed']) > 0) {
                $this->removePicProject($data['removed'], $projectUid, $projectId);
            }

            // update cache
            if ($currentData = getCache('detailProject' . $projectId)) {
                // new pics
                $newPics = $this->projectPicRepository->list('pic_id', "project_id = {$projectId}", ['employee:id,uid,name']);

                $currentData['pic'] = implode(',', collect($newPics)->pluck('employee.name')->toArray());
                $currentData['pic_ids'] = collect($newPics)->pluck('employee.uid')->toArray();

                $currentData = $this->formatTasksPermission($currentData, $projectId);
            }

            DB::commit();

            return generalResponse(
                __('notification.projectPicHasBeenChanged'),
                false,
                [
                    'full_detail' => $currentData ?? [],
                ],
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return errorResponse($e);
        }
    }

    /**
     * Get all available PIC and current PIC to show in dialog Subtitute PIC
     *
     * @param string $projectUId
     * @return array
     */
    public function getPicForSubtitute(string $projectUid): array
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $pics = $this->mainProcessToGetPicScheduler($projectUid);
            $selectedPic = $this->projectPicRepository->list(
                'id,project_id,pic_id',
                "project_id = {$projectId}",
                ['employee:id,uid,name,email,employee_id'],
            );

            $pics = collect($pics)->filter(function ($filter) use ($selectedPic) {
                return !in_array(
                    $filter['id'],
                    collect($selectedPic)->pluck('employee.uid')->toArray()
                );
            })->values();

            // make selected pic format same as available pic
            $selectedPic = collect($selectedPic)->map(function ($item) use ($projectUid) {
                return [
                    'id' => $item->employee->uid,
                    'current_id' => $item->id, // additional key for frontend use
                    'name' => $item->employee->name,
                    'email' => $item->employee->email,
                    'employee_id' => $item->employee->employee_id,
                    'projects' => $this->getPicWorkload($item->employee, $projectUid),
                    'is_recommended' => false,
                ];
            })->toArray();

            return generalResponse(
                'success',
                false,
                [
                    'current_pic' => $selectedPic,
                    'available_pic' => $pics
                ],
            );
        } catch (\Throwable $e) {
            return errorResponse($e);
        }
    }

    /**
     * Download all media in proof of work
     *
     * @param int $proofOfWorkId
     */
    public function downloadProofOfWork(string $projectUid, int $proofOfWorkId)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $works = $this->proofOfWorkRepo->show(
            $proofOfWorkId,
            'preview_image,id,project_task_id',
            ['task:id,name']
        );

        $images = json_decode($works->preview_image, true);

        $files = [];
        foreach ($images as $image) {
            $files[] = storage_path("app/public/projects/{$projectId}/task/{$works->project_task_id}/proofOfWork/{$image}");
        }

        return [
            'files' => $files,
            'task' => $works->task,
        ];
    }

    /**
     * Download all media in proof of work
     *
     * @param int $proofOfWorkId
     */
    public function downloadReviseMedia(string $projectUid, int $reviseId)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $works = $this->taskReviseHistoryRepo->show(
            $reviseId,
            'file,id,project_task_id',
            ['task:id,name']
        );

        $images = json_decode($works->file, true);

        $files = [];
        foreach ($images as $image) {
            $files[] = storage_path("app/public/projects/{$projectId}/task/{$works->project_task_id}/revise/{$image}");
        }

        return [
            'files' => $files,
            'task' => $works->task,
        ];
    }

    /**
     * Get all projects for file manager
     */
    public function getProjectsFolder()
    {
        $itemsPerPage = request('itemsPerPage') ?? 25;
        $page = request('page') ?? 1;
        $page = $page == 1 ? 0 : $page;
        $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
        $isMyFile = request('is_my_file');

        $year = request('year') ?? date('Y');
        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        $where = "project_date between '{$startDate}' and '{$endDate}'";

        if (request('name')) {
            $where .= " and lower(name) like '%". strtolower(request('name')) ."%'";
        }

        if ($isMyFile) {
            // identity user
            $user = auth()->user();
            if ($user->email != config('app.root_email')) {
                if ($user->is_employee) {
                    $userProjectIds = $this->taskPicHistory->list('project_id', "employee_id = " . $user->employee_id);
                    $userProjectIds = collect($userProjectIds)->pluck('project_id')->toArray();
                    $userProjectIds = implode(',', $userProjectIds);
                } else if ($user->is_project_manager) {
                    $userProjectIds = $this->projectPicRepository->list('project_id', 'pic_id = ' . $user->employee_id);
                    $userProjectIds = collect($userProjectIds)->pluck('project_id')->toArray();
                    $userProjectIds = implode(',', $userProjectIds);
                }

                $where .= " and id IN ({$userProjectIds})";
            }
        }

        $data = $this->repo->pagination(
            'id,uid,name,client_portal',
            $where,
            ['tasks:id,project_id'],
            $itemsPerPage,
            $page,
        );

        $totalData = $this->repo->list('id', $where)->count();
        $total = round($totalData / $itemsPerPage);

        $output = collect($data)->map(function ($item) {
            return [
                'name' => $item->name,
                'id' => $item->uid,
                'task' => count($item->tasks),
                'client_portal' => $item->client_portal,
            ];
        })->toArray();

        return generalResponse(
            'success',
            false,
            [
                'folders' => $output,
                'pagination' => [
                    'total' => $total,
                    'page' => (int)request('page'),
                ],
                'is_my_files' => $isMyFile,
            ],
        );
    }

    /**
     * Get all available years in company
     *
     * @return array
     */
    public function getProjectYears(): array
    {
        $year = date('Y');
        $startYear = date('Y', strtotime('-4 years'));
        $range = collect(range($startYear, $year))->sortDesc()->values()->map(function ($item) {
            return ['year' => $item, 'active' => false];
        })->toArray();

        return generalResponse(
            'success',
            false,
            $range,
        );
    }

    protected function getAllProjectImages(object $project, string $year)
    {
        $where = "project_id = '{$project->id}' and created_year = '{$year}'";
        $relation = ['user:id,employee_id', 'user.employee:id,name'];

        if (request('task')) {
            $where .= " and project_task_id = " . request('task');
            $relation = ['user:id,employee_id', 'user.employee:id,name', 'task:id,name'];
        }

        $user = null;
        if (request('user')) {
            $where .= " and created_by = " . request('user');

            // search user
            $userData = \App\Models\User::select("employee_id")
                ->with(['employee:id,name'])
                ->find(request('user'));
            $user = $userData->employee->name;
        }

        $proofOfWorkAssets = $this->proofOfWorkRepo->list('id,project_task_id,project_id,nas_link,preview_image,created_by', $where, $relation);

        $proofOfWorkImages = [];
        $nasLink = [];
        foreach ($proofOfWorkAssets as $proof) {
            $nasLink[] = [
                'image' => $proof->nas_link,
                'type' => 'nas_link',
                'owner' => $proof->user->employee->name,
            ];

            $proofImages = json_decode($proof->preview_image, true);
            foreach ($proofImages as $image) {
                $proofOfWorkImages[] = [
                    'image' => asset("storage/projects/{$proof->project_id}/task/{$proof->project_task_id}/proofOfWork/{$image}"),
                    'image_name' => $image,
                    'type' => 'image',
                    'owner' => $proof->user->employee->name,
                ];
            }
        }

        return [
            'image' => $proofOfWorkImages,
            'nas_link' => $nasLink,
            'task' => request('task') ? $proofOfWorkAssets[0]->task->name : null,
            'user' => $user,
        ];
    }

    /**
     * Function to get all task on selected project and show as folder
     *
     * @param object $project
     *
     * @return array
     */
    protected function getProjectTasks(object $project): array
    {
        $where = "project_id = {$project->id}";

        if (request('name')) {
            $where .= " and lower(name) like '%". strtolower(request('name')) ."%'";
        }

        $data = $this->taskRepo->list('id,name,project_id', $where, ['proofOfWorks:id,project_task_id,preview_image']);

        $output = [];
        foreach ($data as $task) {
            $works = 0;
            $allImages = [];
            foreach ($task->proofOfWorks as $workData) {
                $images = json_decode($workData->preview_image, true);

                foreach ($images as $image) {
                    $allImages[] = $image;
                }
            }

            $output[] = [
                'name' => $task->name,
                'id' => $task->id,
                'image' => count($allImages),
            ];
        }

        return $output;
    }

    protected function getProjectEmployeeAssets(object $project, string $year)
    {
        $query = \App\Models\User::query();
        $query->selectRaw('id,employee_id')
            ->role('production');

        if (request('name')) {
            $query->with(['employee' => function ($q) {
                $q->selectRaw('id,name');
                $q->whereRaw("lower(name) like '%" . strtolower(request('name')) . "%' or lower(nickname) like '%" . strtolower(request('name')) . "%' or lower(email) like '%" . strtolower(request('name')) . "%'");
            }]);
        } else {
            $query->with(['employee:id,name']);
        }

        $productionUsers = collect((object)$query->get())->filter(function ($filter) {
            return $filter->employee;
        });

        $output = [];
        foreach ($productionUsers as $user) {
            $works = $this->proofOfWorkRepo->list('id,preview_image', "created_by = {$user->id} and created_year = '{$year}' and project_id = {$project->id}");
            $images = [];
            foreach ($works as $work) {
                $imageData = json_decode($work->preview_image, true);
                foreach ($imageData as $img) {
                    $images[] = $img;
                }
            }

            $output[] = [
                'id' => $user->id,
                'image' => count($images),
                'name' => $user->employee->name,
            ];
        }

        return $output;
    }

    public function getProjectFolderDetail()
    {
        $year = request('year');
        $type = request('type');
        $clientPortal = request('project');

        $project = $this->repo->show(0, 'id,uid,name,project_date', [], "client_portal = '{$clientPortal}'");

        if ($type == 'images') {
            $output = $this->getAllProjectImages($project, $year);
        } else if ($type == 'user') {
            $output = $this->getProjectEmployeeAssets($project, $year);
        } else {
            $output = $this->getProjectTasks($project);
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    public function cancelProject(array $data, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $this->deleteProjectPic($projectId);

            $this->repo->update(['status' => $data['status']], 'dummy', "uid = '{$projectUid}'");

            \Modules\Production\Jobs\CancelProjectWithPicJob::dispatch($data['pic_list'], $projectUid)->afterCommit();

            // update cache
            if ($currentData = getCache('detailProject' . $projectId)) {
                // new pics
                $newPics = $this->projectPicRepository->list('pic_id', "project_id = {$projectId}", ['employee:id,uid,name']);

                $currentData['pic'] = implode(',', collect($newPics)->pluck('employee.name')->toArray());
                $currentData['pic_ids'] = collect($newPics)->pluck('employee.uid')->toArray();

                $currentData = $this->formatTasksPermission($currentData, $projectId);
            }

            DB::commit();

            return generalResponse(
                'success',
                false,
                [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return errorResponse($e);
        }
    }

    public function initEntertainmentTeam()
    {
        $users = \App\Models\User::select('id', 'employee_id')->role(['entertainment', 'project manager entertainment'])->get();

        $employeeIds = collect((object) $users)->pluck('employee_id')->toArray();
        $employeeIds = implode(',', $employeeIds);

        $employees = [];
        if ($users->count() > 0) {
            $employees = $this->employeeRepo->list('id,uid,name', "id IN ($employeeIds) and status = 1")->toArray();
        }

        return generalResponse(
            'success',
            false,
            $employees,
        );
    }

    public function requestEntertainment(array $payload, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $project = $this->repo->show($projectUid, 'id,name,project_date');

            $entertainmentPic = \App\Models\User::role('project manager entertainment')->first();

            $user = auth()->user();

            $employeeIds = [];

            if ($payload['default_select']) {
                $this->transferTeamRepo->store([
                    'project_id' => $projectId,
                    'employee_id' => NULL,
                    'reason' => 'Untuk event ' . $project->name,
                    'project_date' => $project->project_date,
                    'status' => \App\Enums\Production\TransferTeamStatus::Requested->value,
                    'request_to' => $entertainmentPic->employee_id,
                    'requested_by' => $user->employee_id,
                    'is_entertainment' => 1,
                ]);
            } else {
                foreach ($payload['team'] as $team) {
                    $employeeId = getIdFromUid($team, new \Modules\Hrd\Models\Employee());

                    $employeeIds[] = $employeeId;

                    $this->transferTeamRepo->store([
                        'project_id' => $projectId,
                        'employee_id' => $employeeId,
                        'reason' => 'Untuk event ' . $project->name,
                        'project_date' => $project->project_date,
                        'status' => \App\Enums\Production\TransferTeamStatus::Requested->value,
                        'request_to' => $entertainmentPic->employee_id,
                        'requested_by' => $user->employee_id,
                        'is_entertainment' => 1
                    ]);
                }
            }

            \Modules\Production\Jobs\RequestEntertainmentTeamJob::dispatch($payload, $project, $entertainmentPic, $user, $employeeIds)->afterCommit();

            DB::commit();

            return generalResponse(
                __('notification.requestEntertainmentSuccess'),
                false,
            );
        } catch (\Throwable $th) {
            DB::rollBack();

           return errorResponse($th);
        }
    }
}
