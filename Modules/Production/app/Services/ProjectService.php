<?php

namespace Modules\Production\Services;

use App\Actions\CreateInteractiveProject;
use App\Actions\CreateQuotation;
use App\Actions\DefineTaskAction;
use App\Actions\GenerateQuotationNumber;
use App\Actions\Hrd\PointRecord;
use App\Actions\Project\DetailCache;
use App\Actions\Project\DetailProject;
use App\Actions\Project\Entertainment\DistributeSong;
use App\Actions\Project\Entertainment\ReportAsDone;
use App\Actions\Project\Entertainment\StoreLogAction;
use App\Actions\Project\Entertainment\SwitchSongWorker;
use App\Actions\Project\FormatBoards;
use App\Actions\Project\FormatTaskPermission;
use App\Actions\Project\SaveTaskState;
use App\Enums\Cache\CacheKey;
use App\Enums\Employee\Status;
use App\Enums\Production\Entertainment\TaskSongLogType;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskSongStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Exceptions\failedToProcess;
use App\Exceptions\NotRegisteredAsUser;
use App\Exceptions\SongHaveNoTask;
use App\Exceptions\TaskAlreadyBeingChecked;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use App\Services\UserRoleManagement;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Repository\PositionRepository;
use Modules\Company\Repository\ProjectClassRepository;
use Modules\Finance\Jobs\ProjectHasBeenFinal;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Hrd\Repository\EmployeeTaskPointRepository;
use Modules\Hrd\Repository\EmployeeTaskStateRepository;
use Modules\Inventory\Repository\CustomInventoryRepository;
use Modules\Inventory\Repository\InventoryItemRepository;
use Modules\Production\Exceptions\FailedModifyWaitingApprovalSong;
use Modules\Production\Exceptions\ProjectNotFound;
use Modules\Production\Exceptions\SongNotFound;
use Modules\Production\Jobs\ConfirmDeleteSongJob;
use Modules\Production\Jobs\DeleteSongJob;
use Modules\Production\Jobs\Project\RejectRequestEditSongJob;
use Modules\Production\Jobs\RemovePicFromSong;
use Modules\Production\Jobs\RequestDeleteSongJob;
use Modules\Production\Jobs\RequestEditSongJob;
use Modules\Production\Jobs\RequestSongJob;
use Modules\Production\Jobs\SongApprovedToBeEditedJob;
use Modules\Production\Jobs\SongReportAsDone;
use Modules\Production\Jobs\SongReviseJob;
use Modules\Production\Jobs\TaskSongApprovedJob;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectSongList;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\EntertainmentTaskSongRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultImageRepository;
use Modules\Production\Repository\EntertainmentTaskSongResultRepository;
use Modules\Production\Repository\EntertainmentTaskSongReviseRepository;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectEquipmentRepository;
use Modules\Production\Repository\ProjectPersonInChargeRepository;
use Modules\Production\Repository\ProjectReferenceRepository;
use Modules\Production\Repository\ProjectRepository;
use Modules\Production\Repository\ProjectSongListRepository;
use Modules\Production\Repository\ProjectTaskAttachmentRepository;
use Modules\Production\Repository\ProjectTaskHoldRepository;
use Modules\Production\Repository\ProjectTaskLogRepository;
use Modules\Production\Repository\ProjectTaskPicHistoryRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;
use Modules\Production\Repository\ProjectTaskPicRepository;
use Modules\Production\Repository\ProjectTaskProofOfWorkRepository;
use Modules\Production\Repository\ProjectTaskRepository;
use Modules\Production\Repository\ProjectTaskReviseHistoryRepository;
use Modules\Production\Repository\ProjectTaskWorktimeRepository;
use Modules\Production\Repository\ProjectVjRepository;
use Modules\Production\Repository\TransferTeamMemberRepository;

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

    private $taskPicLogRepo;

    private $taskReviseHistoryRepo;

    private $transferTeamRepo;

    private $employeeTaskPoint;

    private $taskPicHistory;

    private $customItemRepo;

    private $projectClassRepo;

    private $projectVjRepo;

    private $inventoryItemRepo;

    private $projectTaskHoldRepo;

    private $geocoding;

    private $telegramEmployee;

    private $userManagement;

    private $positionRepo;

    private $projectSongListRepo;

    private $generalService;

    private $entertainmentTaskSongRepo;

    private $entertainmentTaskSongLogService;

    private $userRepo;

    private $detailProjectAction;

    private $detailCacheAction;

    private $entertainmentTaskSongResultRepo;

    private $entertainmentTaskSongResultImageRepo;

    private $entertainmentTaskSongRevise;

    private $employeeTaskStateRepo;

    private $settingRepo;

    private $projectQuotationRepo;

    private $projectDealRepo;

    private $projectDealMarketingRepo;

    /**
     * Construction Data
     */
    public function __construct(
        UserRoleManagement $userRoleManagement,
        ProjectBoardRepository $projectBoardRepo,
        \App\Services\Geocoding $geoCoding,
        ProjectTaskHoldRepository $projectTaskHoldRepo,
        ProjectVjRepository $projectVjRepo,
        InventoryItemRepository $inventoryItemRepo,
        ProjectClassRepository $projectClassRepo,
        ProjectRepository $projectRepo,
        ProjectReferenceRepository $projectRefRepo,
        EmployeeRepository $employeeRepo,
        ProjectTaskRepository $projectTaskRepo,
        ProjectTaskPicRepository $projectTaskPicRepo,
        ProjectEquipmentRepository $projectEquipmentRepo,
        ProjectTaskAttachmentRepository $projectTaskAttachmentRepo,
        ProjectPersonInChargeRepository $projectPicRepo,
        ProjectTaskLogRepository $projectTaskLogRepo,
        ProjectTaskProofOfWorkRepository $projectProofOfWorkRepo,
        ProjectTaskWorktimeRepository $projectTaskWorktimeRepo,
        PositionRepository $positionRepo,
        ProjectTaskPicLogRepository $taskPicLogRepo,
        ProjectTaskReviseHistoryRepository $taskReviseHistoryRepo,
        TransferTeamMemberRepository $transferTeamRepo,
        EmployeeTaskPointRepository $employeeTaskPoint,
        ProjectTaskPicHistoryRepository $taskPicHistory,
        CustomInventoryRepository $customItemRepo,
        ProjectSongListRepository $projectSongListRepo,
        GeneralService $generalService,
        EntertainmentTaskSongRepository $entertainmentTaskSongRepo,
        EntertainmentTaskSongLogService $entertainmentTaskSongLogService,
        UserRepository $userRepo,
        DetailProject $detailProjectAction,
        DetailCache $detailCacheAction,
        EntertainmentTaskSongResultRepository $entertainmentTaskSongResultRepo,
        EntertainmentTaskSongResultImageRepository $entertainmentTaskSongResultImageRepo,
        EntertainmentTaskSongReviseRepository $entertainmentTaskSongRevise,
        EmployeeTaskStateRepository $employeeTaskStateRepo,
        \Modules\Company\Repository\SettingRepository $settingRepo,
        \Modules\Production\Repository\ProjectQuotationRepository $projectQuotationRepo,
        \Modules\Production\Repository\ProjectDealRepository $projectDealRepo,
        \Modules\Production\Repository\ProjectDealMarketingRepository $projectDealMarketingRepo
    ) {
        $this->entertainmentTaskSongRevise = $entertainmentTaskSongRevise;

        $this->entertainmentTaskSongResultImageRepo = $entertainmentTaskSongResultImageRepo;

        $this->entertainmentTaskSongResultRepo = $entertainmentTaskSongResultRepo;

        $this->detailCacheAction = $detailCacheAction;

        $this->detailProjectAction = $detailProjectAction;

        $this->userRepo = $userRepo;

        $this->entertainmentTaskSongLogService = $entertainmentTaskSongLogService;

        $this->entertainmentTaskSongRepo = $entertainmentTaskSongRepo;

        $this->generalService = $generalService;

        $this->userManagement = $userRoleManagement;

        $this->geocoding = $geoCoding;

        $this->projectTaskHoldRepo = $projectTaskHoldRepo;

        $this->projectVjRepo = $projectVjRepo;

        $this->inventoryItemRepo = $inventoryItemRepo;

        $this->projectClassRepo = $projectClassRepo;

        $this->repo = $projectRepo;

        $this->referenceRepo = $projectRefRepo;

        $this->employeeRepo = $employeeRepo;

        $this->taskRepo = $projectTaskRepo;

        $this->boardRepo = $projectBoardRepo;

        $this->taskPicRepo = $projectTaskPicRepo;

        $this->projectEquipmentRepo = $projectEquipmentRepo;

        $this->projectTaskAttachmentRepo = $projectTaskAttachmentRepo;

        $this->projectPicRepository = $projectPicRepo;

        $this->projectTaskLogRepository = $projectTaskLogRepo;

        $this->proofOfWorkRepo = $projectProofOfWorkRepo;

        $this->taskWorktimeRepo = $projectTaskWorktimeRepo;

        $this->positionRepo = $positionRepo;

        $this->taskPicLogRepo = $taskPicLogRepo;

        $this->taskReviseHistoryRepo = $taskReviseHistoryRepo;

        $this->transferTeamRepo = $transferTeamRepo;

        $this->employeeTaskPoint = $employeeTaskPoint;

        $this->taskPicHistory = $taskPicHistory;

        $this->customItemRepo = $customItemRepo;

        $this->projectSongListRepo = $projectSongListRepo;

        $this->employeeTaskStateRepo = $employeeTaskStateRepo;

        $this->settingRepo = $settingRepo;

        $this->projectQuotationRepo = $projectQuotationRepo;

        $this->projectDealRepo = $projectDealRepo;

        $this->projectDealMarketingRepo = $projectDealMarketingRepo;
    }

    /**
     * Delete bulk data
     *
     * @param  array<string>  $ids
     */
    public function bulkDelete(array $ids): array
    {
        DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $projectId = getIdFromUid($id, new \Modules\Production\Models\Project);

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
     * @param  array  $ids
     */
    public function removeAllVJ(string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $this->projectVjRepo->delete(0, 'project_id = '.getIdFromUid($projectUid, new \Modules\Production\Models\Project));

            $project = $this->repo->show(
                uid: $projectUid,
                select: 'id',
                relation: [
                    'vjs:id,project_id,employee_id',
                    'vjs.employee:id,nickname',
                ]
            );

            $currentData = $this->detailCacheAction->handle(
                projectUid: $projectUid,
                necessaryUpdate: [
                    // update vj
                    'vjs' => $project->vjs,
                ]
            );

            DB::commit();

            return generalResponse(
                __('global.allVjisRemoved'),
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

    protected function deleteProjectBoard(int $projectId)
    {
        $this->boardRepo->delete(0, 'project_id = '.$projectId);
    }

    protected function deleteProjectPic(int $projectId)
    {
        $this->projectPicRepository->delete(0, 'project_id = '.$projectId);
    }

    protected function deleteProjectEquipmentRequest(int $projectId)
    {
        $data = $this->projectEquipmentRepo->list('id,project_id', 'project_id = '.$projectId);

        if (count($data) > 0) {
            // send notification
        }

        $this->projectEquipmentRepo->delete(0, 'project_id = '.$projectId);
    }

    protected function deleteProjectTasks(int $projectId)
    {
        $data = $this->taskRepo->list('id,project_id', 'project_id = '.$projectId);

        foreach ($data as $task) {
            // delete task attachments
            $taskAttachments = $this->projectTaskAttachmentRepo->list('id,media', 'project_task_id = '.$task->id);

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
        $data = $this->referenceRepo->list('id,project_id,media_path', 'project_id = '.$projectId);

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
            if (! empty($search['event_type'])) {
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
     */
    protected function getProjectTaskRelationQuery(object $employee): array
    {
        if (auth()->user()->hasRole('entertainment')) { // just get event for entertainment. Look at transfer_team_members table
            $newWhereHas = [
                [
                    'relation' => 'teamTransfer',
                    'query' => 'employee_id = '.auth()->user()->employee_id,
                ],
            ];
        } else { // get based on task
            $taskIds = $this->taskPicLogRepo->list('id,project_task_id', 'employee_id = '.$employee->id);
            $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

            if (count($taskIds) > 0) {
                $queryNewHas = 'id IN ('.implode(',', $taskIds).')';
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

    public function listForEntertainment() {}

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
            $whereHas = [];

            $roles = auth()->user()->roles;

            $isProductionRole = $this->userManagement->isProductionRole();
            $isEntertainmentRole = $this->userManagement->isEntertainmentRole();

            $projectManagerRole = getSettingByKey('project_manager_role');
            $isPMRole = $roles[0]->id == $projectManagerRole;

            // $filterResult = $this->buildFilterResult();

            if (request('filter_month') == 'true') {
                $startMonth = date('Y-m').'-01';
                $endDateOfMonth = Carbon::createFromDate(
                    (int) date('Y'),
                    (int) date('m'),
                    1
                )
                    ->endOfMonth()
                    ->format('d');
                $endMonth = date('Y-m').'-'.$endDateOfMonth;
                if (empty($where)) {
                    $where = "project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                } else {
                    $where .= " and project_date BETWEEN '{$startMonth}' AND '{$endMonth}'";
                }
            }

            if (request('filter_year') == 'true') {
                $startMonth = date('Y').'-01-01';
                $endMonth = date('Y').'-12-31';

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
                if (! empty($search['name']) && empty($where)) {
                    $name = strtolower($search['name']);
                    $where = "lower(name) LIKE '%{$name}%'";
                } elseif (! empty($search['name']) && ! empty($where)) {
                    $name = $search['name'];
                    $where .= " AND lower(name) LIKE '%{$name}%'";
                }

                if (! empty($search['event_type']) && empty($where)) {
                    $eventType = strtolower($search['event_type']);
                    $where = "event_type = '{$eventType}'";
                } elseif (! empty($search['event_type']) && ! empty($where)) {
                    $eventType = $search['event_type'];
                    $where .= " AND event_type = '{$eventType}'";
                }

                if (! empty($search['classification']) && empty($where)) {
                    $classification = strtolower($search['classification']);
                    $where = "classification = '{$classification}'";
                } elseif (! empty($search['classification']) && ! empty($where)) {
                    $classification = $search['classification'];
                    $where .= " AND classification = '{$classification}'";
                }

                if (! empty($search['start_date']) && empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where = "project_date >= '{$start}'";
                } elseif (! empty($search['start_date']) && ! empty($where)) {
                    $start = date('Y-m-d', strtotime($search['start_date']));
                    $where .= " AND project_date >= '{$start}'";
                }

                if (! empty($search['end_date']) && empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where = "project_date <= '{$end}'";
                } elseif (! empty($search['end_date']) && ! empty($where)) {
                    $end = date('Y-m-d', strtotime($search['end_date']));
                    $where .= " AND project_date <= '{$end}'";
                }

                if (! empty($search['pic']) && empty($whereHas)) {
                    $pics = $search['pic'];
                    $pics = collect($pics)->map(function ($pic) {
                        $picId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee);

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

            $employeeId = $this->employeeRepo->show('dummy', 'id,boss_id', [], 'id = '.auth()->user()->employee_id);

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
                        $inWhere = '(';
                        $inWhere .= auth()->user()->employee_id;
                        $inWhere .= ',';
                        $inWhere .= $bossId;
                        $inWhere .= ')';

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
                        'query' => 'pic_id = '.auth()->user()->employee_id,
                    ];
                }
            }

            $sorts = '';
            if (! empty(request('sortBy'))) {
                foreach (request('sortBy') as $sort) {
                    if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                        $sorts .= $sort['key'].' '.$sort['order'].',';
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

            $paginated = collect((object) $paginated)->map(function ($item) use ($eventTypes, $classes, $statusses, $roles) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name.'('.$pic->employee->employee_id.')',
                    ];
                })->pluck('name')->values()->toArray();

                $picEid = collect($item->personInCharges)->pluck('employee.employee_id')->toArray();

                $marketing = $item->marketing ? $item->marketing->name : '-';

                $marketingData = collect($item->marketings)->pluck('marketing.name')->toArray();
                $marketing = $item->marketings[0]->marketing->name;
                if ($item->marketings->count() > 1) {
                    $marketing .= ', and +'.$item->marketings->count() - 1 .' more';
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

                $item['roles'] = $roles;

                return [
                    'uid' => $item->uid,
                    'id' => $item->id,
                    'marketing' => $marketing,
                    'pic' => count($pics) > 0 ? implode(', ', $pics) : __('global.undetermined'),
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
                    'roles' => $item['roles'],
                ];
            });

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                    'itemPerPage' => (int) $itemsPerPage,
                    'isAllItems' => $isAllItems,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all board based related logged user project
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

        //        if (request('filter_month')) {
        //            $startDate = request('filter_year') . '-' . request('filter_month') . '-01';
        //        }

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

        //        if (request('filter_month')) {
        //            $endCarbon = \Carbon\Carbon::parse(request('filter_year') . '-' . request('filter_month') . '-01');
        //            $endDate = request('filter_year') . '-' . request('filter_month') . '-' . $endCarbon->endOfMonth()->format('d');
        //        }

        if (empty($where)) {
            $where = "project_date <= '{$endDate}'";
        } else {
            $where .= " and project_date <= '{$endDate}'";
        }

        $filterData['date']['end_date'] = date('Y, F d', strtotime($endDate));
        $filterData['date']['enable'] = true;

        // by venue
        $select = 'id,uid,name,project_date,venue,event_type,collaboration,status,led_area,led_detail,project_class_id,classification,city_name';
        $coordinate = [];
        $orderBy = 'project_date ASC';
        if (request('filter_venue') == 1) {
            $coordinate = [$project->latitude, $project->longitude, $project->latitude];
            $orderBy = 'distance ASC, project_date ASC';
            $select = "id,uid,name,project_date,venue,event_type,collaboration,status,led_area,led_detail,project_class_id,classification,city_name,(
                       6371 * acos(
                           cos(radians({$project->latitude})) * cos(radians(latitude)) *
                           cos(radians(longitude) - radians({$project->longitude})) +
                           sin(radians({$project->latitude})) * sin(radians(latitude))
                       )
                   ) AS distance";
        }

        $data = $this->repo->list(
            $select,
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
                'led_area' => $item->led_area.'m <sup>2</sup>',
                'collaboration' => $item->collaboration,
                'venue' => $item->venue.', '.$item->city_name,
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
        if (! $isSuperUserRole) {
            $whereHas[] = [
                'relation' => 'personInCharges',
                'query' => 'pic_id = '.$employeeId,
            ];
        }

        $data = $this->repo->list(
            'id,uid as value,name as title',
            "project_date > '{$now}'",
            [],
            $whereHas
        );

        logging('WHERE HAS', $whereHas);

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
                    'name' => $reference->name,
                ];
            } elseif (in_array($reference->type, $fileDocumentType)) {
                $group['pdf'][] = [
                    'id' => $reference->id,
                    'name' => 'document',
                    'media_path' => asset('storage/projects/references/'.$projectId).'/'.$reference->media_path,
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
     * @param  \Illuminate\Database\Eloquent\Collection  $project
     */
    public function getProjectTeams(object $project, bool $forceGetSpecialTeam = false): array
    {
        $where = '';
        $pics = [];
        $teams = [];
        $picIds = [];
        $picUids = [];

        if ($productionPositions = json_decode(getSettingByKey('position_as_production'), true)) {
            $productionPositions = collect($productionPositions)->map(function ($item) {
                return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
            })->toArray();
        }

        foreach ($project->personInCharges as $key => $pic) {
            $pics[] = $pic->employee->name.'('.$pic->employee->employee_id.')';
            $picIds[] = $pic->pic_id;
            $picUids[] = $pic->employee->uid;

            // check persion in charge role
            // if Assistant, then get teams based his team and his boss team
            $userPerson = \App\Models\User::selectRaw('id,email')->where('employee_id', $pic->employee->id)
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
        $specialPosition = $this->generalService->getSettingByKey('special_production_position');
        $leadModeller = $this->generalService->getSettingByKey('lead_3d_modeller');

        $specialEmployee = [];
        $specialIds = [];
        if ($specialPosition) {
            $specialPosition = $this->generalService->getIdFromUid($specialPosition, new PositionBackup);
            $whereSpecial = "position_id = {$specialPosition}";

            $isLeadModeller = false;
            if ($leadModeller != null && $leadModeller != '' && $leadModeller != 'null' && ! $forceGetSpecialTeam) {
                $leadModeller = $this->generalService->getIdFromUid($leadModeller, new Employee);
                $whereSpecial = "id = {$leadModeller}";
                $isLeadModeller = true;
            }

            $specialEmployee = $this->employeeRepo->list('id,uid,name,nickname,email,position_id', $whereSpecial, ['position:id,name'])->toArray();

            $specialEmployee = collect($specialEmployee)->map(function ($employee) use ($isLeadModeller) {
                $employee['loan'] = false;
                $employee['image'] = asset('images/user.png');
                $employee['is_lead_modeller'] = $isLeadModeller;

                return $employee;
            })->toArray();

            $specialIds = collect($specialEmployee)->pluck('id')->toArray();
        }

        logging('SPECIAL EMPLOYEE', $specialEmployee);
        logging('WHERE SPECIAL', [$whereSpecial]);
        logging('LEAD MODELER', [$leadModeller]);

        // get another teams from approved transfer team
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $superUserRole = getSettingByKey('super_user_role');
        $transferCondition = 'status = '.\App\Enums\Production\TransferTeamStatus::Approved->value.' and project_id = '.$project->id.' and is_entertainment = 0';
        if ($roleId != $superUserRole) {
            $transferCondition .= ' and requested_by = '.$user->employee_id;
        }

        if (count($picIds) > 0) {
            $picId = implode(',', $picIds);
            $employeeCondition = "boss_id IN ($picId)";
        } else {
            $employeeCondition = 'boss_id IN (0)';
        }

        $employeeCondition .= ' and status != '.\App\Enums\Employee\Status::Inactive->value;

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

            // remove special position id from production position
            if ($specialPosition) {
                $searchSpecialPosition = array_search($specialPosition, $productionPositions);
                if (isset($productionPositions[$searchSpecialPosition])) {
                    unset($productionPositions[$searchSpecialPosition]);
                }
            }

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
                    'query' => "LOWER(name) like '%production%'",
                ],
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

            // THIS CAUSE PM WHO DO NOT HAVE ANY TEAM MEMBER CANNOT SEE TRANSFER AND SPECIAL EMPLOYEE
            // SHO THIS SHOULD BE RUNNING OUTSIDE OF THIS 'IF' CONDITION
            // $teams = collect($teams)->merge($transfers)->toArray();

            // $teams = collect($teams)->merge($specialEmployee)->toArray();
        }

        $teams = collect($teams)->merge($transfers)->toArray();

        $teams = collect($teams)->merge($specialEmployee)->toArray();

        // get task on selected project
        $outputTeam = [];
        foreach ($teams as $key => $team) {
            $task = $this->taskPicHistory->list('id', 'project_id = '.$project->id.' and employee_id = '.$team['id'])->count();

            $outputTeam[$key] = $team;
            $outputTeam[$key]['total_task'] = $task;
        }

        // get entertainment teams
        $entertain = $this->transferTeamRepo->list(
            'id,employee_id,requested_by,alternative_employee_id',
            'project_id = '.$project->id.' and is_entertainment = 1 and employee_id is not null',
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
            'pics.employee:id,name,email,uid,avatar_color',
            'medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
            'taskLink:id,project_id,project_task_id,media,display_name,related_task_id,type',
            'proofOfWorks',
            'logs',
            'board',
            'times:id,project_task_id,employee_id,work_type,time_added',
            'times.employee:id,uid,name',
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
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
        $employeeId = auth()->user()->employee_id ?? 0;
        $superUserRole = isSuperUserRole();

        $data = $this->boardRepo->list('id,project_id,name,sort,based_board_id', 'project_id = '.$projectId, [
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
            'tasks.times.employee:id,uid,name',
        ]);

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = '.$projectId);
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

                $outputTask[$keyTask]['is_mine'] = (bool) in_array(auth()->user()->employee_id, $picIds);

                if ($superUserRole || $isProjectPic || $isDirector || isAssistantPMRole()) {
                    $isActive = true;
                }

                // check the ownership of task

                $haveTaskAccess = true;
                if (! $superUserRole && ! $isProjectPic && ! $isDirector && ! isAssistantPMRole()) {
                    if (! in_array($employeeId, $picIds)) {
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

        $projectBoards = $this->boardRepo->list('id,project_id,name,based_board_id', 'project_id = '.$projectId);

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
                        $completed = collect($value)->where('status', '=', \App\Enums\Production\TaskStatus::Completed->value)->count();

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
        $equipments = $this->projectEquipmentRepo->list('*', 'project_id = '.$projectId, [
            'inventory:id,name',
            'inventory.image',
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

    protected function getTaskTimeTracker(int $taskId) {}

    public function updateDetailProjectFromOtherService(string $projectUid)
    {
        // $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        // $currentData = getCache('detailProject' . $projectId);

        // if ($currentData) {
        //     $boards = $this->formattedBoards($projectUid);
        //     $currentData['boards'] = $boards;

        //     $currentData['boards'] = $boards;

        //     storeCache('detailProject' . $projectId, $currentData);

        //     $currentData = $this->formatTasksPermission($currentData, $projectId);
        // }
        $this->detailCacheAction->handle($projectUid, [
            'boards' => FormatBoards::run($projectUid),
        ]);
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $output = $this->detailProjectAction->handle($uid, $this->repo, $this->entertainmentTaskSongRepo);

            $serviceEncrypt = new \App\Services\EncryptionService;
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
            return errorResponse(
                message: $th,
                code: $th->getCode()
            );
        }
    }

    protected function formatSingleTaskPermission($task)
    {
        $employeeId = $this->telegramEmployee ? $this->telegramEmployee->id : auth()->user()->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

        // if logged user is pic or super user role, set as is_project_pic
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = '.$task['project_id']);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $task['is_project_pic'] = $isProjectPic;

        $task['action_list'] = DefineTaskAction::run($task);

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
        if (! $superUserRole && ! $isProjectPic || ! $isDirector) {
            if (! in_array($employeeId, $picIds)) {
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

        // define user can add, edit or delete the task description
        $task['can_add_description'] = false;
        $task['can_edit_description'] = false;
        $task['can_delete_description'] = false;
        $user = auth()->user();
        /**
         * Who can modify the description?
         * 1. Project Manager
         * 2. Root
         * 3. Project Manager Admin
         * 4. Lead Modeller
         * 5. Who own the modify description role
         * 6. Who are the PIC's of this event
         */
        if (
            ($user->hasPermissionTo('edit_task_description')) &&
            (hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task))
        ) {
            $task['can_edit_description'] = true;
        }

        if (
            ($user->hasPermissionTo('add_task_description')) &&
            (hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task))
        ) {
            $task['can_add_description'] = true;
        }

        if (
            ($user->hasPermissionTo('delete_task_description')) &&
            (hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task))
        ) {
            $task['can_delete_description'] = true;
        }

        /**
         * Define who can modify task attachment result
         */
        $task['can_delete_attachment'] = false;
        if (
            hasSuperPower(projectId: $task['project_id']) ||
            hasLittlePower(task: $task)
        ) {
            $task['can_delete_attachment'] = true;
        }

        return $task;
    }

    // TODO: Need to develop
    protected function getEmployeeWorkingTimeReport() {}

    public function getProjectStatistic($project)
    {
        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project);
        $teams = $project['teams'];

        $output = [];
        $resp = [];

        $checkPoint = $this->employeeTaskPoint->list('*', 'project_id = '.$projectId);

        if ($checkPoint->count() > 0) {
            foreach ($teams as $key => $team) {
                $output[$key] = $team;

                // get points
                $point = $this->employeeTaskPoint->show('dummy', '*', [], 'employee_id = '.$team['id'].' and project_id = '.$projectId);

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

                $moreLine = count($output) > 2 ? $output : [];

                $resp = [
                    'first_line' => $firstLine,
                    'more_line' => $moreLine,
                ];
            }
        }

        return $resp;
    }

    public function updateSongList(int $projectId): array
    {
        $songs = $this->projectSongListRepo->list(
            select: 'uid,id,name,created_by,is_request_edit,is_request_delete',
            where: 'project_id = '.$projectId,
            relation: [
                'task:id,project_song_list_id,employee_id',
                'task.employee:id,nickname',
            ]
        );

        $songs = collect((object) $songs)->map(function ($item) {
            $item = $this->formatSingleSongStatus($item);

            $disabled = false;
            if ($item->is_request_edit || $item->is_request_delete) {
                $disabled = true;
            }
            $item['disabled'] = $disabled;

            return $item;
        })->toArray();

        return $songs;
    }

    public function formatSingleSongStatus(object $item)
    {
        $statusFormat = $item->task ? __('global.distributed') : __('global.waitingToDistribute');
        $statusColor = $item->task ? 'success' : 'info';

        if (! $item->task) {
            $item['status_text'] = $statusFormat;
            $item['status_color'] = $statusColor;
        } else {
            $item['status_text'] = $item->task->status_text;
            $item['status_color'] = $item->task->status_color;
        }

        $statusRequest = null;
        if ($item->is_request_edit) {
            $statusRequest = __('global.songEditRequest');
        }

        if ($item->is_request_delete) {
            $statusRequest = __('global.songDeleteRequest');
        }

        $item['status_request'] = $statusRequest;

        // override all action for root
        $admin = auth()->user()->hasRole(BaseRole::Root->value);
        $director = auth()->user()->hasRole(BaseRole::Director->value);
        $entertainmentPm = auth()->user()->hasRole(BaseRole::ProjectManagerEntertainment->value);

        $item['status_of_work'] = ! $item->task ? null : TaskSongStatus::getLabel($item->task->status);
        $item['status_of_work_color'] = ! $item->task ? null : TaskSongStatus::getColor($item->task->status);

        $item['my_own'] = $admin || $director || $entertainmentPm ?
            true :
            (
                ! $item->task ?
                false :
                (
                    $item->task->employee->user_id == auth()->user()->id ?
                    true :
                    false
                )
            ); // override permission for root, director and project manager
        $item['need_to_be_done'] = ! $item->task ? false : ($item->task->status == TaskSongStatus::OnProgress->value ? true : false);
        $item['need_worker_approval'] = ! $item->task ?
            false :
            (
                $item->task->status == TaskSongStatus::Active->value && ($item->task->employee->user_id == auth()->user()->id || $admin || $director || $entertainmentPm) ?
                true :
                false
            );

        return $item;
    }

    public function formatTasksPermission($project, int $projectId)
    {
        $output = [];

        $project['report'] = $this->getProjectStatistic($project);

        $project['songs'] = $this->updateSongList(projectId: $projectId);

        $project['feedback_given'] = count($project['report']) > 0 ? true : false;

        $user = auth()->user();
        $employeeId = $user->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

        // get teams
        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project);
        $personInCharges = $this->projectPicRepository->list('*', 'project_id = '.$projectId, ['employee:id,uid,name,email,nickname,boss_id,position_id']);
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
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = '.$projectId);
        $isProjectPic = in_array($employeeId, collect($projectPics)->pluck('pic_id')->toArray()) || $superUserRole ? true : false;
        $project['is_project_pic'] = $isProjectPic;

        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project);
        $projectTasks = $this->taskRepo->list('*', 'project_id = '.$projectId, ['board']);

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

                // disable when task is on hold
                if ($task['status'] === \App\Enums\Production\TaskStatus::OnHold->value) {
                    $outputTask[$keyTask]['is_active'] = false;
                }

                $outputTask[$keyTask]['show_hold_button'] = $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value || $task['status'] == \App\Enums\Production\TaskStatus::Revise->value;
                $outputTask[$keyTask]['is_hold'] = $task['status'] == \App\Enums\Production\TaskStatus::OnHold->value ? true : false;

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
                if (! $superUserRole && ! $isProjectPic && ! $isDirector && ! isAssistantPMRole()) {
                    if (! in_array($employeeId, $picIds)) { // where logged user is not a in task pic except the project manager
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
                if ($project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value || ! $project['status_raw']) {
                    $outputTask[$keyTask]['is_active'] = false;
                }
            }

            $output[$keyBoard]['tasks'] = $outputTask;
        }

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

        storeCache('detailProject'.$projectId, $project);

        return $project;
    }

    /**
     * Store data
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $data['project_date'] = date('Y-m-d', strtotime($data['project_date']));

            $ledDetail = [];
            if ((isset($data['led_detail'])) && (! empty($data['led_detail']))) {
                $ledDetail = $data['led_detail'];
            }
            $data['led_detail'] = json_encode($ledDetail);

            $city = \Modules\Company\Models\City::select('name')->find($data['city_id']);
            $state = \Modules\Company\Models\State::select('name')->find($data['state_id']);

            $coordinate = $this->geocoding->getCoordinate($city->name.', '.$state->name);
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
                    'marketing_id' => getIdFromUid($marketing, new \Modules\Hrd\Models\Employee),
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
     * @return array
     */
    public function updateMoreDetail(array $data, string $id)
    {
        DB::beginTransaction();
        try {
            $city = \Modules\Company\Models\City::select('name')->find($data['city_id']);
            $state = \Modules\Company\Models\State::select('name')->find($data['state_id']);

            $coordinate = $this->geocoding->getCoordinate($city->name.', '.$state->name);
            if (count($coordinate) > 0) {
                $data['longitude'] = $coordinate['longitude'];
                $data['latitude'] = $coordinate['latitude'];
            }

            $data['city_name'] = $city->name;

            $ledDetail = [];
            if ((isset($data['led_detail'])) && (! empty($data['led_detail']))) {
                $ledDetail = $data['led_detail'];
            }
            $data['led_detail'] = json_encode($ledDetail);

            $projectId = getIdFromUid($id, new \Modules\Production\Models\Project);

            $this->repo->update(collect($data)->except(['pic'])->toArray(), $id);

            $project = $this->repo->show($id, 'id,client_portal,collaboration,event_type,note,status,venue,country_id,state_id,city_id,led_detail,led_area', [
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id,uid,boss_id',
            ]);

            $projectTeams = $this->getProjectTeams($project);
            $teams = $projectTeams['teams'];
            $pics = $projectTeams['pics'];
            $picIds = $projectTeams['picUids'];

            // $currentData = getCache('detailProject' . $project->id);
            // $currentData['venue'] = $project->venue;
            // $currentData['city_name'] = $city->name;
            // $currentData['country_id'] = $project->country_id;
            // $currentData['state_id'] = $project->state_id;
            // $currentData['city_id'] = $project->city_id;
            // $currentData['event_type'] = $project->event_type_text;
            // $currentData['event_type_raw'] = $project->event_type;
            // $currentData['collaboration'] = $project->collaboration;
            // $currentData['status'] = $project->status_text;
            // $currentData['status_raw'] = $project->status;
            // $currentData['led_area'] = $project->led_area;
            // $currentData['led_detail'] = json_decode($project->led_detail, true);
            // $currentData['note'] = $project->note ?? '-';
            // $currentData['client_portal'] = $project->client_portal;
            // $currentData['pic'] = implode(', ', $pics);
            // $currentData['pic_ids'] = $picIds;
            // $currentData['teams'] = $teams;

            // $currentData = $this->formatTasksPermission($currentData, $project->id);

            // storeCache('detailProject' . $project->id, $currentData);
            $currentData = $this->detailCacheAction->handle($id, ['status' => $project->status_text, 'status_raw' => $project->status, 'status_color' => $project->status_color]);

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
     * @param  string  $id
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

            $update = $this->repo->update(
                collect($data)->except(['date'])->toArray(),
                $projectUid
            );

            // manually fire the event
            Event::dispatch('eloquent.updated: '.get_class(new \Modules\Production\Models\Project), $update);

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

            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
            $currentData = getCache('detailProject'.$projectId);
            $currentData['name'] = $format['name'];
            $currentData['event_type'] = $format['event_type'];
            $currentData['project_date'] = $format['project_date'];
            $currentData['event_type_raw'] = $format['event_type_raw'];
            $currentData['event_class_raw'] = $format['event_class_raw'];
            $currentData['event_class'] = $projectClass->name;
            $currentData['event_class_color'] = $format['event_class_color'];

            storeCache('detailProject'.$projectId, $currentData);

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
     * @return array
     */
    public function storeReferences(array $data, string $id)
    {
        // 2nd layer validation
        if (empty($data['link']) && empty($data['files'])) {
            return generalResponse(
                message: 'Invalid data',
                error: true,
                data: [
                    'link.0.name' => [__('notification.linkOrFileRequired')],
                    'link.0.href' => [__('notification.linkOrFileRequired')],
                    'files.0.path' => [__('notification.linkOrFileRequired')],
                ],
            );
        }

        $fileImageType = ['jpg', 'jpeg', 'png'];
        $fileDocumentType = ['doc', 'docx', 'xlsx', 'pdf'];
        $project = $this->repo->show($id);

        if (! $project) {
            throw new ProjectNotFound;
        }

        try {
            $output = [];

            // handle link upload
            $linkPayload = [];
            if (isset($data['link'])) {
                foreach ($data['link'] as $keyLink => $link) {
                    // 3rd layer of validation
                    if (! isset($link['name']) && isset($link['href'])) {
                        return generalResponse('Invalid data', true, [
                            "link.{$keyLink}.name" => [__('notification.linkNameRequired')],
                        ], 422);
                    }

                    if (
                        (
                            isset($link['name']) &&
                            ! isset($link['href'])
                        ) ||
                        (
                            empty($link['href']) &&
                            ! empty($link['name'])
                        )
                    ) {
                        return generalResponse('Invalid data', true, [
                            "link.{$keyLink}.href" => [__('notification.linkRequired')],
                        ], 422);
                    }

                    if (! empty($link['href'])) {
                        $linkPayload[] = [
                            'media_path' => $link['href'],
                            'type' => 'link',
                            'name' => $link['name'],
                        ];
                    }
                }
            }

            // handle file upload
            if (isset($data['files'])) {
                foreach ($data['files'] as $file) {
                    if ($file['path']) {
                        $type = $file['path']->getClientOriginalExtension();

                        if (gettype(array_search($type, $fileImageType)) != 'boolean') {
                            $fileData = uploadImageandCompress(
                                'projects/references/'.$project->id,
                                10,
                                $file['path']
                            );
                        } else { // handle document upload
                            $fileType = array_search($type, $fileDocumentType);
                            if (gettype($fileType) != 'boolean') {
                                $type = $fileDocumentType[$fileType];
                            }

                            $fileData = uploadFile(
                                'projects/references/'.$project->id,
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
            }

            $output = collect($output)->merge($linkPayload)->toArray();

            $project->references()->createMany($output);

            // update cache
            $referenceData = $this->formatingReferenceFiles($project->references, $project->id);

            $currentData = $this->detailCacheAction->handle(
                projectUid: $project->uid,
                necessaryUpdate: [
                    'references' => $referenceData,
                ]
            );

            return generalResponse(
                __('global.successCreateReferences'),
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
     * @return array
     */
    public function storeDescription(array $data, string $taskId)
    {
        DB::beginTransaction();
        try {
            $this->taskRepo->update($data, $taskId);

            $this->loggingTask(['task_uid' => $taskId], 'addDescription');

            $task = $this->formattedDetailTask($taskId);

            $currentData = $this->detailCacheAction->handle($task->project->uid, [
                'boards' => FormatBoards::run($task->project->uid),
            ]);

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
     * when isForProjectManager is TRUE, then set status to approved, otherwise set to waiting approval
     *
     * Every assigned member need to approved it before do any action. Bcs of this sytem will take a note about 'IDLE TASK'
     * IDLE TASK is the idle time between assigned time and approved time
     *
     * If $isRevise is TRUE, no need to change task status (Already handle in parent function)
     *
     * @param  array  $data  With these following structure
     *                       - array <string> $users
     *                       - array <string> $remmoved
     * @return array
     */
    public function assignMemberToTask(
        array $data,
        string $taskUid,
        bool $isForProjectManager = false,
        bool $isRevise = false,
        bool $needChangeTaskStatus = true
    ) {
        DB::beginTransaction();
        try {
            // validate pic
            /**
             * Cannot combine lead modeler with other employee
             */
            $leadModeller = $this->generalService->getSettingByKey('lead_3d_modeller');
            $isForLeadModeller = (isset($data['users'])) && (! empty($data['users'])) && (count($data['users']) == 1) && ($data['users'][0] == $leadModeller) ? true : false;

            $isValid = $this->validatePicTask($data['users']);

            if (! $isValid) {
                return errorResponse(message: __('notification.cannotCombineModeller'));
            }

            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);

            $notifiedNewTask = [];
            foreach ($data['users'] as $user) {

                $employeeId = $this->generalService->getIdFromUid($user, new \Modules\Hrd\Models\Employee);
                $userData = $this->userRepo->detail(select: 'id', where: "employee_id = {$employeeId}");

                $checkPic = $this->taskPicRepo->show(0, 'id', [], 'project_task_id = '.$taskId.' AND employee_id = '.$employeeId);
                if (! $checkPic) {
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
                        'status' => ! $isForProjectManager ? \App\Enums\Production\TaskPicStatus::WaitingApproval->value : \App\Enums\Production\TaskPicStatus::Approved->value,
                        'assigned_at' => Carbon::now(),
                    ];

                    if ($isRevise) {
                        $payload['status'] = \App\Enums\Production\TaskPicStatus::Revise->value;
                    }

                    if ($isForLeadModeller) {
                        $payload['status'] = TaskPicStatus::WaitingToDistribute->value;
                    }

                    $this->taskPicRepo->store($payload);

                    $notifiedNewTask[] = $employeeId;

                    // record task working time history
                    if ($isForProjectManager) { // set to check by pm
                        $this->setTaskWorkingTime($taskId, $employeeId, \App\Enums\Production\WorkType::CheckByPm->value);
                    } else { // check to assigned
                        // if task assign to 'LEAD MODELLER', do not write working time
                        if (! $userData->hasPermissionTo('assign_modeller')) {
                            $this->setTaskWorkingTime($taskId, $employeeId, \App\Enums\Production\WorkType::Assigned->value);
                        }
                    }

                    $this->loggingTask([
                        'task_id' => $taskId,
                        'employee_uid' => $user,
                    ], 'assignMemberTask');
                }
            }

            $this->detachTaskPic(
                ids: $data['removed'],
                taskId: $taskId,
                isEmployeeUid: true,
                removeFromHistory: true
            );

            // change task status
            if (! $isRevise && $needChangeTaskStatus) { // see PHPDOC
                $payloadStatus = [
                    'status' => ! $isForProjectManager ? \App\Enums\Production\TaskStatus::WaitingApproval->value : \App\Enums\Production\TaskStatus::CheckByPm->value,
                ];
                if ($isForLeadModeller) { // change to distribute is it for lead modeller
                    $payloadStatus['status'] = TaskStatus::WaitingDistribute->value;
                }
                // check current task pic
                $lastPic = $this->taskPicRepo->list(
                    select: 'id',
                    where: "project_task_id = '{$taskId}'"
                );
                if ($lastPic->count() == 0) {
                    $payloadStatus['status'] = null;
                }
                $this->taskRepo->update($payloadStatus, $taskUid);
            }

            // notify removed user
            if (count($data['removed']) > 0) {
                \Modules\Production\Jobs\RemoveUserFromTaskJob::dispatch($data['removed'], $taskId)->afterCommit();
            }

            $task = $this->formattedDetailTask($taskUid);

            $currentData = $this->detailCacheAction->handle($task->project->uid, [
                'boards' => FormatBoards::run($task->project->uid),
            ]);

            // TODO: CHECK AGAIN ACTION WHEN ASSIGN TO PROJECT MANAGER
            if ($currentData['status_raw'] != \App\Enums\Production\ProjectStatus::Draft->value) {
                // override notification when task is revise
                if ($isRevise) {
                    \Modules\Production\Jobs\ReviseTaskJob::dispatch($notifiedNewTask, $taskId)->afterCommit();
                } else {
                    if ($isForProjectManager) {
                        //                        \Modules\Production\Jobs\AssignCheckByPMJob::dispatch($notifiedNewTask, $taskId)->afterCommit();
                    } else {
                        if (isset($userData)) {
                            \Modules\Production\Jobs\AssignTaskJob::dispatch($notifiedNewTask, $taskId, $userData)->afterCommit();
                        }
                    }
                }
            }

            /**
             * We have is_modeler_task column in project task do define task is for modeler team or not
             * Because of that we need update the column every pic is removed or new pic is assigned
             */
            DB::commit();

            $message = __('notification.memberAdded');
            if (empty($data['users']) && ! empty($data['removed'])) {
                $message = __('notification.memberHasBeedRemoved');
            }

            return generalResponse(
                $message,
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
     * Detach current pic to selected task
     *
     * @param  array  $ids  (uid of current task pic)
     * @return void
     */
    public function detachTaskPic(
        array $ids,
        int $taskId,
        bool $isEmployeeUid = true,
        bool $removeFromHistory = false,
        string $message = '',
        bool $doLogging = true // sometime we don't need to log the action
    ) {
        foreach ($ids as $removedUser) {
            if ($isEmployeeUid) {
                $removedEmployeeId = getIdFromUid($removedUser, new \Modules\Hrd\Models\Employee);
            } else {
                $removedEmployeeId = $removedUser;
            }

            // delete from table task_pics
            $this->taskPicRepo->deleteWithCondition('employee_id = '.$removedEmployeeId.' AND project_task_id = '.$taskId);

            // delete from history
            if ($removeFromHistory) {
                // delete from table task_pic_histories
                $this->taskPicHistory->deleteWithCondition('employee_id = '.$removedEmployeeId.' AND project_task_id = '.$taskId);
            }

            $employee = $this->employeeRepo->show('id', 'id,name,nickname', [], 'id = '.$removedEmployeeId);

            $logMessage = __('global.removedMemberLogText', [
                'removedUser' => $employee->nickname,
            ]);
            if (! empty($message)) {
                $logMessage = $message;
            }

            if ($doLogging) {
                $this->loggingTask([
                    'task_id' => $taskId,
                    'employee_uid' => $removedUser,
                    'message' => $logMessage,
                ], 'removeMemberTask');
            }
        }
    }

    /**
     * Delete selected task
     *
     * @return array
     */
    public function deleteTask(string $taskUid)
    {
        try {
            $task = $this->taskRepo->show($taskUid, 'id,project_id', [
                'project:id,uid',
            ]);

            $projectUid = $task->project->uid;
            $projectId = $task->project->id;

            // delete pic history if exists
            $this->taskPicHistory->deleteWithCondition('project_id = '.$task->project_id.' and project_task_id = '.$task->id);

            $this->taskRepo->bulkDelete([$taskUid], 'uid');

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

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

    protected function validatePicTask(array $payloadUser): bool
    {
        // validate pic
        $leadModeller = $this->generalService->getSettingByKey('lead_3d_modeller');
        $specialPosition = $this->generalService->getSettingByKey('special_production_position');
        $specialPosition = $this->generalService->getIdFromUid($specialPosition, new PositionBackup);

        if ((isset($payloadUser)) && (! empty($payloadUser)) && ($leadModeller && count($payloadUser) > 1)) {
            $selectedModeller = collect($payloadUser)->filter(function ($filter) use ($leadModeller) {
                return $filter != $leadModeller;
            })->values()->toArray();
        }
        $isValid = ! isset($selectedModeller) ? true : (count($payloadUser) == count($selectedModeller) ? true : false);

        if (count($payloadUser) > 1) {
            $employees = $this->employeeRepo->list(
                select: 'id,position_id',
                where: "uid IN ('".implode("','", $payloadUser)."')"
            );
            $positionIds = collect($employees)->pluck('position_id')->toArray();

            $filterSpecialPosition = collect($positionIds)->filter(function ($filter) use ($specialPosition) {
                return $filter == $specialPosition;
            })->values()->count();
            $filterRegularPosition = collect($positionIds)->filter(function ($filter) use ($specialPosition) {
                return $filter != $specialPosition;
            })->values()->count();

            if ($filterSpecialPosition > 0 && $filterRegularPosition > 0) {
                $isValid = false;
            }

            // skip if all users are 3d modeller
            if ($filterSpecialPosition == count($payloadUser)) {
                $isValid = true;
            }
        }

        return $isValid;
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
     * @return array
     */
    public function storeTask(array $data, int $boardId)
    {
        DB::beginTransaction();
        try {
            // validate pic
            $leadModeller = $this->generalService->getSettingByKey('lead_3d_modeller');
            $isForLeadModeller = (isset($data['pic'])) && ($data['pic'][0] == $leadModeller) ? true : false;

            $isValid = isset($data['pic']) ? $this->validatePicTask($data['pic']) : true;

            if (! $isValid) {
                return errorResponse(message: __('notification.cannotCombineModeller'));
            }

            $board = $this->boardRepo->show($boardId, 'project_id,name', ['project:id,uid', 'project.personInCharges']);
            $data['project_id'] = $board->project_id;
            $data['project_board_id'] = $boardId;
            $data['start_date'] = date('Y-m-d');
            $data['end_date'] = ! empty($data['end_date']) ? date('Y-m-d', strtotime($data['end_date'])) : null;

            // set as waiting employee approval
            if (! empty($data['pic'])) {
                $data['status'] = $isForLeadModeller ? TaskStatus::WaitingDistribute->value : TaskStatus::WaitingApproval->value;
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
            if (! empty($data['pic'])) {
                $this->assignMemberToTask(
                    data: [
                        'users' => $data['pic'],
                        'removed' => [],
                    ],
                    taskUid: $task->uid,
                    needChangeTaskStatus: $isForLeadModeller ? false : true
                );

                // send notification if needed

            }

            // add image attachment if needed
            if (! empty($data['media'])) {
                $this->uploadTaskMedia(
                    [
                        'media' => $data['media'],
                    ],
                    $task->id,
                    $board->project_id,
                    $board->project->uid,
                    $task->uid
                );
            }

            $currentData = $this->detailCacheAction->handle(
                projectUid: $board->project->uid,
                forceUpdateAll: true
            );

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

    /**
     * Distribute task to modeler teams
     */
    public function distributeModellerTask(array $payload, string $projectUid, string $taskUid): array
    {
        try {
            $leadModeller = $this->generalService->getSettingByKey('lead_3d_modeller');
            $taskId = $this->generalService->getIdFromUid($taskUid, new ProjectTask);

            // check special position
            $specialPosition = $this->generalService->getSettingByKey('special_production_position');
            $specialPosition = $this->generalService->getIdFromUid($specialPosition, new PositionBackup);
            $specialEmployees = $this->employeeRepo->list(
                select: 'id',
                where: "position_id = {$specialPosition}"
            )->count();

            $data = [
                'users' => $payload['teams'],
                'removed' => $specialEmployees == count($payload['teams']) ? [] : [$leadModeller],
            ];

            if (count($payload['teams']) == 1 && $leadModeller == $payload['teams'][0]) {
                $payload['assign_to_me'] = 1;
            }

            if ($payload['assign_to_me'] == 1) { // auto change status to on progress
                $this->taskRepo->update([
                    'status' => TaskStatus::OnProgress->value,
                ], $taskUid);
            } else {
                $distribute = $this->assignMemberToTask(
                    data: $data,
                    taskUid: $taskUid
                );

                if ($distribute['error']) {
                    return $distribute;
                }
            }

            /**
             * Mark the task as modeler task
             */
            $this->taskRepo->update([
                'is_modeler_task' => true,
            ], $taskUid);

            $task = $this->formattedDetailTask($taskUid);
            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

            return generalResponse(
                message: __('notification.taskHasBeenDistribute'),
                data: [
                    'task' => $task,
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function reinitDetailCache($project)
    {
        $this->show($project->uid);

        return getCache('detailProject'.$project->id);
    }

    /**
     * Delete reference image
     *
     * @return array
     */
    public function deleteReference(array $ids, string $projectId)
    {
        try {
            foreach ($ids as $id) {
                $reference = $this->referenceRepo->show($id);
                $path = $reference->media_path;

                deleteImage(storage_path('app/public/projects/references/'.$reference->project_id.'/'.$path));

                $this->referenceRepo->delete($id);
            }

            $project = $this->repo->show($projectId, 'id,name,uid');

            // update cache
            $referenceData = $this->formatingReferenceFiles($project->references, $project->id);

            $currentData = $this->detailCacheAction->handle(
                projectUid: $project->uid,
                necessaryUpdate: [
                    'references' => $referenceData,
                ]
            );

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
     * This function is only used for LEAD MODELER account
     * If he open the 'members' button in the detail task, it will call this function
     */
    protected function getProject3DMember(string $taskUid): array
    {
        $specialEmployee = $this->generalService->getSettingByKey('special_production_position');
        $positionId = $this->generalService->getIdFromUid($specialEmployee, new PositionBackup);

        $employees = $this->employeeRepo->list(
            select: 'uid,id,name,email',
            where: "position_id = {$positionId}"
        );

        // define who are still idle who are not
        $task = $this->taskRepo->show(
            uid: $taskUid,
            select: 'id,name',
            relation: [
                'pics:id,project_task_id,employee_id',
            ]
        );
        $picIds = collect($task->pics)->pluck('employee_id')->toArray();

        // return [
        //     'selected' => true,
        //     'email' => $item->employee->email,
        //     'name' => $item->employee->name,
        //     'uid' => $item->employee->uid,
        //     'id' => $item->employee->id,
        //     'intital' => $item->employee->initial,
        //     'image' => asset('images/user.png'),
        //     'is_lead_modeller' => $isLeadModeller,
        //     'is_modeler' => (bool) $task->is_modeler_task
        // ];

        $selectedEmployees = collect((object) $employees)->filter(function ($filter) use ($picIds) {
            return in_array($filter->id, $picIds);
        })->map(function ($mapping) {
            $mapping['image'] = asset('images/user.png');
            $mapping['selected'] = true;

            return $mapping;
        })->values();

        $availableEmployees = collect((object) $employees)->filter(function ($filter) use ($picIds) {
            return ! in_array($filter->id, $picIds);
        })->map(function ($mapping) {
            $mapping['image'] = asset('images/user.png');
            $mapping['selected'] = false;

            return $mapping;
        })->values();

        $out = [
            'selected' => $selectedEmployees,
            'available' => $availableEmployees,
        ];

        return generalResponse(
            message: 'Success',
            data: $out
        );
    }

    /**
     * Get teams in selected project
     * If authenticated user is lead modeler, then only show the 3d modeler
     * Otherwise show other employee
     *
     * @param  string  $projectId
     * @return array
     */
    public function getProjectMembers(int $projectId, string $taskId)
    {
        try {
            $leadModeller = $this->generalService->getSettingByKey('lead_3d_modeller');
            $leadModeller = $this->generalService->getIdFromUid($leadModeller, new Employee);
            if (auth()->user()->employee_id == $leadModeller) {
                return $this->getProject3DMember($taskId);
            }

            $project = $this->repo->show('', '*', [
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id,boss_id',
            ], 'id = '.$projectId);

            $projectTeams = $this->getProjectTeams($project);
            $teams = $projectTeams['teams'];

            $task = $this->taskRepo->show($taskId, 'id,project_id,project_board_id,is_modeler_task', [
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,uid,name,email',
                'pics.user:id,employee_id',
            ]);

            $currentTaskPics = $task->pics->toArray();
            $outSelected = collect($task->pics)->map(function ($item) use ($task) {
                $isLeadModeller = $item->user->hasPermissionTo('assign_modeller') && $this->generalService->getSettingByKey('lead_3d_modeller') ? true : false;

                return [
                    'selected' => true,
                    'email' => $item->employee->email,
                    'name' => $item->employee->name,
                    'uid' => $item->employee->uid,
                    'id' => $item->employee->id,
                    'intital' => $item->employee->initial,
                    'image' => asset('images/user.png'),
                    'is_lead_modeller' => $isLeadModeller,
                    'is_modeler' => (bool) $task->is_modeler_task,
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
            foreach ($teams as $keyTeam => $team) {
                if (! in_array($team['uid'], collect($outSelected)->pluck('uid')->toArray())) {
                    if ($team) {
                        $team['is_modeler'] = false;
                    }
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
                $inventoryId = getIdFromUid($item['inventory_id'], new \Modules\Inventory\Models\Inventory);

                $check = $this->projectEquipmentRepo->show('', '*', 'project_id = '.$project->id.' AND inventory_id = '.$inventoryId);

                if (! $check) {
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
                        ], '', 'inventory_id = '.$inventoryId.' AND project_id = '.$project->id);
                    }
                }
            }

            $equipments = $this->formattedEquipments($project->id);
            $currentData = getCache('detailProject'.$project->id);
            if (! $currentData) {
                $this->show($project->uid);

                $currentData = getCache('detailProject'.$project->id);
            }
            $currentData['equipments'] = $equipments;

            storeCache('detailProject'.$project->id, $currentData);

            \Modules\Production\Jobs\RequestEquipmentJob::dispatch($project);

            DB::commit();

            return generalResponse(
                'success',
                false,
                $currentData
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Get list of project equipments
     *
     * @return array
     */
    public function listEquipment(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

        $data = $this->projectEquipmentRepo->list('id,uid,project_id,inventory_id,qty,status,is_checked_pic', 'project_id = '.$projectId, [
            'inventory:id,name,stock',
            'inventory.image',
            'inventory.items:id,inventory_id,inventory_code',
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

                    $inventoryItems = $this->inventoryItemRepo->list('id,inventory_code', 'inventory_id = '.$projectEquipment->inventory_id);
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

                $this->projectEquipmentRepo->update($payload, '', "is_checked_pic = FALSE and uid = '".$item['id']."'");
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

            storeCache('detailProject'.$projectId, $currentData);

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

            storeCache('detailProject'.$projectId, $currentData);

            return generalResponse(
                __('global.equipmentCanceled'),
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
     * @return array
     */
    protected function getDetailProjectCache(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

        $currentData = getCache('detailProject'.$projectId);
        if (! $currentData) {
            $this->show($projectUid);

            $currentData = getCache('detailProject'.$projectId);
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
                'task_uid' => $data['task_id'],
            ], 'updateDeadline');

            $task = $this->formattedDetailTask($data['task_id']);

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

            DB::commit();

            return generalResponse(
                __('global.deadlineAdded'),
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
     * @return array
     */
    public function uploadTaskAttachment(array $data, string $taskUid, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $output = [];
            if ((isset($data['media'])) && (count($data['media']) > 0)) {
                DB::commit();

                return $this->uploadTaskMedia($data, $taskId, $projectId, $projectUid, $taskUid);
            } elseif ((isset($data['task_id'])) && (count($data['task_id']) > 0)) {
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
            $targetTask = getIdFromUid($task, new \Modules\Production\Models\ProjectTask);

            $check = $this->projectTaskAttachmentRepo->show('dummy', 'id', [], "media = '{$targetTask}' and project_id = {$projectId} and project_task_id = {$taskId}");

            if (! $check) {
                $this->projectTaskAttachmentRepo->store([
                    'project_task_id' => $taskId,
                    'project_id' => $projectId,
                    'media' => $targetTask,
                    'type' => $type,
                ]);
            }
        }

        $task = $this->formattedDetailTask($taskUid);

        $currentData = $this->detailCacheAction->handle($projectUid, [
            'boards' => FormatBoards::run($projectUid),
        ]);

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

        $currentData = $this->detailCacheAction->handle($projectUid, [
            'boards' => FormatBoards::run($projectUid),
        ]);

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
                    'projects/'.$projectId.'/task/'.$taskId,
                    $file,
                );
            } elseif (in_array($mime, $imagesMime)) {
                $name = uploadImageandCompress(
                    'projects/'.$projectId.'/task/'.$taskId,
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
                'media_name' => $name,
            ], 'addAttachment');
        }

        $task = $this->formattedDetailTask($taskUid);

        $currentData = $this->detailCacheAction->handle($projectUid, [
            'boards' => FormatBoards::run($projectUid),
        ]);

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
     * @return array
     */
    public function searchTask(string $projectUid, string $taskUid, string $search = '')
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $search = strtolower($search);
            if (! $search) {
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
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

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

            return \Illuminate\Support\Facades\Storage::download('projects/'.$data->project_id.'/task/'.$data->project_task_id.'/'.$data->media);
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
                    'media_name' => $data->media,
                ], 'deleteAttachment');
            }

            $this->projectTaskAttachmentRepo->delete($attachmentId);

            $task = $this->formattedDetailTask($taskUid);

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

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

    protected function mainProofOfWork(array $data, string $projectUid, string $taskUid, bool $useDefaultImage = false)
    {
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);

            if ($data['nas_link'] && (isset($data['preview']) || $useDefaultImage)) {
                $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
                $selectedProjectId = $projectId;
                $selectedTaskId = $taskId;

                if ($useDefaultImage) {
                    $image[] = $this->taskRepo->modelClass()::DEFAULTIMAGEPROOF;
                } else {
                    foreach ($data['preview'] as $img) {
                        $imageData = uploadImageandCompress(
                            "projects/{$projectId}/task/{$taskId}/proofOfWork",
                            10,
                            $img
                        );
                        $image[] = $imageData;
                    }
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

                // $boardId = $data['board_id'];
                // $sourceBoardId = $data['source_board_id'];

                // if ($data['manual_approve']) {
                //     $taskDetail = $this->taskRepo->show($taskUid, 'id,project_board_id');
                //     $sourceBoardId = $taskDetail->project_board_id;

                //     // get next board
                //     $boardList = $this->boardRepo->list('id,name', 'project_id = ' . $projectId);
                //     foreach ($boardList as $keyBoard => $boardData) {
                //         if ($boardData->id == $sourceBoardId) {
                //             if (isset($boardList[$keyBoard + 1])) {
                //                 $boardId = $boardList[$keyBoard + 1]->id;
                //                 break;
                //             } else {
                //                 $boardId = $sourceBoardId;
                //                 break;
                //             }
                //         }
                //     }
                // }

                // set current pic
                $currentPics = $this->taskPicRepo->list(
                    select: 'employee_id',
                    where: 'project_task_id = '.$taskId
                );
                $payloadUpdate['current_pics'] = json_encode(collect($currentPics)->pluck('employee_id')->toArray());
                $payloadUpdate['is_modeler_task'] = false;

                $this->taskRepo->update(
                    data: $payloadUpdate,
                    where: 'id = '.$taskId
                );

                // set worktime as finish to current task pic
                $currentTaskPic = $this->taskPicRepo->list('id,employee_id', 'project_task_id = '.$taskId);
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
            }
        } catch (\Throwable $th) {
            throw new Exception(errorMessage($th));
        }
    }

    /**
     * Function to upload proof of work, and change task to next task and assign PM to check employee work
     *
     * @param  bool  $useDefaultImage  -> this parameter will be TRUE IF PM / ROOT / DIRECTOR force complete the task before completing the project
     * @return array
     */
    public function proofOfWork(array $data, string $projectUid, string $taskUid, bool $useDefaultImage = false)
    {
        DB::beginTransaction();
        $image = [];
        $selectedProjectId = null;
        $selectedTaskId = null;
        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);

        // variable for error response
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $this->mainProofOfWork($data, $projectUid, $taskUid, $useDefaultImage);

            $task = $this->formattedDetailTask($taskUid);

            // notified project manager
            if (! $useDefaultImage) {
                \Modules\Production\Jobs\ProofOfWorkJob::dispatch($projectId, $taskId, auth()->id())->afterCommit();
            }

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

            DB::commit();

            return generalResponse(
                __('global.proofOfWorkUploaded'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
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
     * @return void
     */
    protected function detachPicAndAssignProjectManager(int $taskId, string $taskUid, int $projectId)
    {
        // get project pics
        $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = '.$projectId, ['employee:id,uid']);
        $projectPicUids = collect($projectPics)->pluck('employee.uid')->toArray();

        // get task pics
        $taskPics = $this->taskPicRepo->list('id,employee_id', 'project_task_id = '.$taskId, ['employee:id,uid']);
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
        $taskId = getIdFromUid($data['task_id'], new \Modules\Production\Models\ProjectTask);

        $boardIds = [$data['board_id'], $data['board_source_id']];
        $boards = $this->boardRepo->list('id,name,based_board_id', 'id IN ('.implode(',', $boardIds).')');
        $boardData = collect((object) $boards)->filter(function ($filter) use ($data) {
            return $filter->id == $data['board_id'];
        })->values();

        $payloadUpdate = [
            'project_board_id' => $data['board_id'],
            'current_board' => $data['board_source_id'],
        ];

        if (! empty($nextTaskStatus)) {
            $payloadUpdate['status'] = $nextTaskStatus;
        }

        if ($setCurrentPic) {
            $currentPics = $this->taskPicRepo->list('employee_id', 'project_task_id = '.$taskId);
            $payloadUpdate['current_pics'] = json_encode(collect($currentPics)->pluck('employee_id')->toArray());
        }

        $this->taskRepo->update($payloadUpdate, '', 'id = '.$taskId);

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

    public function manualMoveBoard(array $data, string $projectUid)
    {
        $cache = $this->getDetailProjectCache($projectUid);
        $currentData = $cache['cache'];
        $projectId = $cache['projectId'];

        $this->changeTaskBoardProcess($data, $projectUid);

        $currentData = $this->detailCacheAction->handle($projectUid, [
            'boards' => FormatBoards::run($projectUid),
        ]);

        return generalResponse(
            'success',
            false,
            $currentData,
        );
    }

    /**
     * Change board of task (When user move a task)
     *
     * @param  array  $data
     *                       Data will be
     *                       1. int board_id
     *                       2. int board_source_id
     *                       3. string task_id
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

            $taskId = getIdFromUid($data['task_id'], new \Modules\Production\Models\ProjectTask);

            // set worktime as finish to current task pic
            $currentTaskPic = $this->taskPicRepo->list('id,employee_id', 'project_task_id = '.$taskId);
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

            storeCache('detailProject'.$projectId, $currentData);

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
     * @param  collection  $task
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

            storeCache('detailProject'.$projectId, $currentData);

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
     * @param  array  $payload
     * @param  string  $type
     *                        Type will be:
     *                        1. moveTask
     *                        2. addUser
     *                        3. addNewTask
     *                        4. addAttachment
     *                        5. deleteAttachment
     *                        6. changeTaskName
     *                        7. addDescription
     *                        8. updateDeadline
     *                        9. assignMemberTask
     *                        10. removeMemberTask
     *                        11. deleteAttachment
     * @return void
     */
    public function loggingTask($payload, string $type)
    {
        $type .= 'Log';

        return $this->{$type}($payload);
    }

    protected function startTaskLog($payload)
    {
        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'holdTask', // TODO: Change to startTask
            'text' => __('global.actorStartTheTask', ['actor' => $payload['actor']]),
            'user_id' => auth()->id(),
        ]);
    }

    protected function holdTaskLog($payload)
    {
        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'holdTask',
            'text' => __('global.actorHoldTheTask', ['actor' => $payload['actor']]),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Add log when user add attachment
     *
     * @param  array  $payload
     *                          $payload will have
     *                          [int task_id, string media_name]
     * @return void
     */
    protected function addAttachmentLog($payload)
    {
        $text = __('global.addAttachmentLogText', [
            'name' => auth()->user()->username,
            'media' => $payload['media_name'],
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
     * @param  array  $payload
     *                          $payload will have
     *                          [string task_uid, string media_name]
     * @return void
     */
    protected function deleteAttachmentLog($payload)
    {
        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask);

        $text = __('global.deleteAttachmentLogText', [
            'name' => auth()->user()->username,
            'media' => $payload['media_name'],
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
     * @param  array  $payload
     *                          $payload will have
     *                          [int task_id, string employee_uid]
     * @return void
     */
    protected function removeMemberTaskLog($payload)
    {
        $this->projectTaskLogRepository->store([
            'project_task_id' => $payload['task_id'],
            'type' => 'assignMemberTask',
            'text' => $payload['message'],
            'user_id' => auth()->id() ?? 0,
        ]);
    }

    /**
     * Add log when add new member to task
     *
     * @param  array  $payload
     *                          $payload will have
     *                          [int task_id, string employee_uid]
     * @return void
     */
    protected function assignMemberTaskLog($payload)
    {
        $employee = $this->employeeRepo->show($payload['employee_uid'], 'id,name,nickname');
        $text = __('global.assignMemberLogText', [
            'assignedUser' => $employee->nickname,
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
     * @param  array  $payload
     *                          $payload will have
     *                          [string task_uid]
     * @return void
     */
    protected function updateDeadlineLog($payload)
    {
        $text = __('global.updateDeadlineLogText', [
            'name' => auth()->user()->username,
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask);

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
     * @param  array  $payload
     *                          $payload will have
     *                          [string task_uid]
     * @return void
     */
    protected function addDescriptionLog($payload)
    {
        $text = __('global.updateDescriptionLogText', [
            'name' => auth()->user()->username,
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask);

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
     * @param  array  $payload
     *                          $payload will have
     *                          [string task_uid]
     * @return void
     */
    protected function changeTaskNameLog($payload)
    {
        $text = __('global.changeTaskNameLogText', [
            'name' => auth()->user()->username,
        ]);

        $taskId = getIdFromUid($payload['task_uid'], new \Modules\Production\Models\ProjectTask);

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
     * @param  array  $payload
     *                          $payload will have
     *                          [array board, int board_id, array task]
     * @return void
     */
    protected function addNewTaskLog($payload)
    {
        $board = $payload['board'];

        $text = __('global.addTaskText', [
            'name' => auth()->user()->username,
            'boardTarget' => $board['name'],
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
     * @param  array  $payload
     *                          $payload will have
     *                          [array boards, collection task, int|string board_id, int|string task_id, int|string board_source_id]
     * @return void
     */
    protected function moveTaskLog($payload)
    {
        $nickname = $this->telegramEmployee ? $this->telegramEmployee->nickname : auth()->user()->username;
        // get source board
        $sourceBoard = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_source_id'];
        })->values();

        $boardTarget = collect($payload['boards'])->filter(function ($filter) use ($payload) {
            return $filter['id'] == $payload['board_id'];
        })->values();

        $text = __('global.moveTaskLogText', [
            'name' => $nickname, 'boardSource' => $sourceBoard[0]['name'], 'boardTarget' => $boardTarget[0]['name'],
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
     * @return array
     */
    public function getMoveToBoards(int $boardId, string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
        $data = $this->boardRepo->list('id,name', 'project_id = '.$projectId.' and id != '.$boardId);

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
     */
    public function getAllTasks(): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? 10;

            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $sorts = '';
            if (! empty(request('sortBy'))) {
                foreach (request('sortBy') as $sort) {
                    if ($sort['key'] == 'task_name') {
                        $sort['key'] = 'name';
                    }
                    if ($sort['key'] != 'pic' && $sort['key'] != 'uid') {
                        $sorts .= $sort['key'].' '.$sort['order'].',';
                    }
                }

                $sorts = rtrim($sorts, ',');
            } else {
                $sorts .= 'created_at desc';
            }

            // check role
            $user = auth()->user();
            $su = getSettingByKey('super_user_role');
            $userId = $user->id;
            $roles = $user->roles;
            $roleId = $roles[0]->id;
            $employeeId = $user->employee_id;

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
                    'query' => 'employee_id = '.$employeeId,
                ];

                $showPic = true;
            } else {
                if ($projectManagerRole == $roleId) {
                    $projectPicIds = $this->projectPicRepository->list('project_id', 'pic_id = '.$employeeId);
                    $projectIds = collect($projectPicIds)->pluck('project_id')->toArray();
                    $projectIds = implode("','", $projectIds);
                    $projectIds = "'".$projectIds;
                    $projectIds .= "'";

                    $where = "project_id in ({$projectIds})";
                }
            }

            if (! empty(request('project_id'))) { // override where clause project id
                $projectIds = collect(request('project_id'))->map(function ($item) {
                    $projectId = getIdFromUid($item, new \Modules\Production\Models\Project);

                    return $projectId;
                })->toArray();

                $projectIds = implode("','", $projectIds);
                $projectIds = "'".$projectIds;
                $projectIds .= "'";
                $where = "project_id in ({$projectIds})";
            }

            if (! empty(request('task_name'))) {
                $taskName = request('task_name');
                if (empty($where)) {
                    $where = "lower(name) LIKE '%{$taskName}%'";
                } else {
                    $where .= " and lower(name) LIKE '%{$taskName}%'";
                }
            }

            if (! empty(request('status'))) {
                $status = implode(',', request('status'));
                if (empty($where)) {
                    $where = "status IN ({$status})";
                } else {
                    $where .= " AND status IN ({$status})";
                }
            }

            $data = $this->taskRepo->pagination(
                select: 'id,uid,project_id,project_board_id,name,task_type,end_date,status',
                where: $where,
                relation: [
                    'project:id,name,project_date',
                    'medias',
                    'taskLink',
                    'board:id,name,based_board_id',
                    'pics:id,project_task_id,employee_id',
                    'pics.employee:id,name,nickname',
                ],
                page: $page,
                itemsPerPage: $itemsPerPage,
                whereHas: $whereHas,
                sortBy: $sorts
            );

            $totalData = $this->taskRepo->list('id', $where, [], $whereHas)->count();

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
                } elseif ($task->board->based_board_id == $checkByPm) {
                    $statusColor = 'primary';
                } elseif ($task->board->based_board_id == $checkByClient) {
                    $statusColor = 'light-blue-lighten-3';
                } elseif ($task->board->based_board_id == $revise) {
                    $statusColor = 'red-darken-1';
                } elseif ($task->board->based_board_id == $completed) {
                    $statusColor = 'blue-darken-2';
                }

                // $projectDate = new DateTime($task->project->project_date);
                $projectDate = Carbon::parse($task->project->project_date);
                $nowTime = Carbon::now();
                // $diff = date_diff($projectDate, new DateTime('now'));
                $diff = $nowTime->diffInDays($projectDate);
                $daysToGo = floor($diff).' '.__('global.day');
                if (floor($diff) < 0) {
                    $daysToGo = __('global.passed');
                }

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
                [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function detailTask(string $uid)
    {
        $task = $this->taskRepo->show($uid, 'project_id', ['project:id,uid']);

        $this->show($task->project->uid);

        $currentData = getCache('detailProject'.$task->project_id);

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
     */
    public function getMarketingListForProject(): array
    {
        $user = auth()->user();

        $marketings = \Illuminate\Support\Facades\Cache::get(CacheKey::MarketingList->value);

        if (! $marketings) {
            $marketings = \Illuminate\Support\Facades\Cache::rememberForever(CacheKey::MarketingList->value, function () use ($user) {
                $positionAsMarketing = getSettingByKey('position_as_marketing');
                $positionAsDirectors = json_decode(getSettingByKey('position_as_directors'), true);

                if ($positionAsDirectors) {
                    $combine = array_merge($positionAsDirectors, [$positionAsMarketing]);
                } else {
                    $combine = [$positionAsMarketing];
                }

                $combine = implode("','", $combine);
                $condition = "'".$combine;
                $condition .= "'";

                $positions = $this->positionRepo->list('id', "uid in ({$condition})");

                $positionIds = collect($positions)->pluck('id')->all();
                $combinePositionIds = implode(',', $positionIds);

                $where = "position_id in ({$combinePositionIds}) and status != ".\App\Enums\Employee\Status::Inactive->value;
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

                return $marketings;
            });
        }

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
     */
    public function approveTask(string $projectUid, string $taskUid, bool $isFromTelegram = false)
    {
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
            $employeeId = auth()->user()->employee_id;

            $isDirector = isDirector();
            if ($isDirector) { // get the real employee id
                $realPic = $this->taskPicRepo->show(0, 'employee_id', [], 'project_task_id = '.$taskId);
                $employeeId = $realPic->employee_id;
            }

            $this->taskPicRepo->update([
                'status' => \App\Enums\Production\TaskPicStatus::Approved->value,
                'approved_at' => Carbon::now(),
            ], 'dummy', 'employee_id = '.$employeeId.' and project_task_id = '.$taskId);

            // change task status to on progress
            $this->taskRepo->update([
                'status' => \App\Enums\Production\TaskStatus::OnProgress->value,
            ], 'dummy', 'id = '.$taskId);

            // update task worktime if meet the requirements
            // $board = $this->boardRepo->show($task->project_board_id);
            $this->setTaskWorkingtime($taskId, $employeeId, \App\Enums\Production\WorkType::OnProgress->value);

            // update cache
            $currentData = getCache('detailProject'.$projectId);

            $task = $this->formattedDetailTask($taskUid);

            // update cache
            $currentData = $this->detailCacheAction->handle(
                projectUid: $projectUid,
                forceUpdateAll: true
            );

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
     */
    public function reviseTask(array $data, string $projectUid, string $taskUid): array
    {
        $tmpFile = [];
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);

        // define special employee
        $specialPosition = $this->generalService->getSettingByKey('special_production_position');
        $specialPosition = $this->generalService->getIdFromUid($specialPosition, new PositionBackup);
        $specialEmployees = $this->employeeRepo->list(
            select: 'id,uid',
            where: "position_id = {$specialPosition}"
        );

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
                $employee = $this->employeeRepo->show('dummy', 'id,uid', [], 'id = '.$currentPic);
                $currentPicUids[] = $employee->uid;
            }

            $currentTaskPics = $this->taskPicRepo->list('employee_id', 'project_task_id = '.$taskId, ['employee:id,uid']);

            $this->taskRepo->update([
                'status' => \App\Enums\Production\TaskStatus::Revise->value,
                //                'project_board_id' => $currentTaskData->current_board,
                'current_board' => null,
                'is_modeler_task' => in_array($currentPicUids[0], collect($specialEmployees)->pluck('uid')->toArray()) ? true : false, // set to TRUE if current pic contain 3D modeler employee
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

            // update cache and finishing process
            $task = $this->formattedDetailTask($taskUid);

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

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
                foreach ($tmpFile as $tmp) {
                    $path = storage_path("app/public/projects/{$projectId}/task/{$taskId}/revise/{$tmp}");
                    deleteImage($path);
                }
            }

            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function startTask(string $projectUid, string $taskUid)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $taskId = getIdFromUid($taskUid, new ProjectTask);
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
            $employee = $this->employeeRepo->show('id', 'id,nickname', [], 'id = '.$user->employee_id);
            $this->setTaskWorkingTime($taskId, $user->employee_id, \App\Enums\Production\WorkType::OnProgress->value);

            $this->taskRepo->update([
                'status' => TaskStatus::OnProgress->value,
            ], $taskUid);

            // update end_at in the project_task_holds table
            $this->projectTaskHoldRepo->update([
                'end_at' => Carbon::now(),
            ], 'dummy', 'project_task_id = '.$taskId.' and end_at is null');

            $this->loggingTask([
                'task_id' => $taskId,
                'actor' => $employee->nickname,
            ], 'startTask');

            // update cache and finishing process
            $task = $this->formattedDetailTask($taskUid);

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

            DB::commit();

            return generalResponse(
                __('notification.taskIsNowActive'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function holdTask(string $projectUid, string $taskUid, array $payload = []): array
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $taskId = getIdFromUid($taskUid, new ProjectTask);
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
            $this->setTaskWorkingTime($taskId, $user->employee_id, \App\Enums\Production\WorkType::OnHold->value);
            $employee = $this->employeeRepo->show('id', 'id,nickname', [], 'id = '.$user->employee_id);

            // get current task pic
            $currentTaskPics = $this->taskPicRepo->list('employee_id', 'project_task_id = '.$taskId, ['employee:id,uid']);

            $this->taskRepo->update([
                'status' => TaskStatus::OnHold->value,
            ], $taskUid);

            foreach ($currentTaskPics as $currentPic) {
                $this->projectTaskHoldRepo->store([
                    'project_task_id' => $taskId,
                    'reason' => $payload['reason'],
                    'hold_at' => Carbon::now(),
                    'hold_by' => auth()->user()->employee_id ?? auth()->id(),
                    'employee_id' => $currentPic->employee_id,
                ]);
            }

            $this->loggingTask([
                'task_id' => $taskId,
                'actor' => $employee->nickname,
            ], 'holdTask');

            // update cache and finishing process
            $task = $this->formattedDetailTask($taskUid);

            $currentData = $this->detailCacheAction->handle($projectUid, [
                'boards' => FormatBoards::run($projectUid),
            ]);

            DB::commit();

            return generalResponse(
                __('notification.taskIsOnHold'),
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

    protected function mainMarkAsCompleted(string $projectUid, string $taskUid, ?object $employee = null, bool $sendNotification = true)
    {
        if ($employee) {
            // THIS IS REMARK REQUEST CAME FROM TELEGRAM
            $this->telegramEmployee = $employee;
        }

        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask);
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

        // this variable is to alert current pics which is the worker
        $currentTaskData = $this->taskRepo->show($taskUid, 'current_pics,current_board,project_board_id');
        $currentPics = json_decode($currentTaskData->current_pics, true);
        $currentPicIds = [];
        foreach ($currentPics as $currentPic) {
            $employee = $this->employeeRepo->show('dummy', 'id,uid', [], 'id = '.$currentPic);
            $currentPicIds[] = $employee->id;
        }

        $currentPic = $this->taskPicRepo->list('employee_id', 'project_task_id = '.$taskId, ['employee:id,uid']);

        // change worktime status of Project Manager
        foreach ($currentPic as $pic) {
            $this->setTaskWorkingTime($taskId, $pic->employee_id, \App\Enums\Production\WorkType::Finish->value);
        }

        // move task to next board
        $taskDetail = $this->taskRepo->show($taskUid, 'id,project_board_id');
        $sourceBoardId = $taskDetail->project_board_id;

        // get next board
        $boardList = $this->boardRepo->list('id,name', 'project_id = '.$projectId);
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

        $payloadOutput = [];
        if ($this->telegramEmployee) {
            // delete cache
            Artisan::call('cache:clear');
        } else {
            $task = $this->formattedDetailTask($taskUid);

            // $currentData = getCache('detailProject' . $projectId);

            // $boards = $this->formattedBoards($task->project->uid);
            // $currentData['boards'] = $boards;

            // $currentData = $this->formatTasksPermission($currentData, $projectId);

            $currentData = $this->detailCacheAction->handle(
                projectUid: $projectUid,
                forceUpdateAll: true,
            );

            $payloadOutput = [
                'task' => $task,
                'full_detail' => $currentData,
            ];
        }

        // save task state
        SaveTaskState::run($currentPicIds, $taskUid);

        if ($sendNotification) {
            \Modules\Production\Jobs\TaskIsCompleteJob::dispatch($currentPicIds, $taskId)->afterCommit();
        }

        return $payloadOutput;
    }

    /**
     * Function to complete the task
     * Project task status will be complete (refer to \App\Enums\Production\TaskStatus.php)
     * Set working time to finish (refer to \App\Enums\Production\WorkType.php)
     * Detach ALL PIC
     */
    public function markAsCompleted(string $projectUid, string $taskUid, ?object $employee = null): array
    {
        DB::beginTransaction();
        try {
            $payloadOutput = $this->mainMarkAsCompleted($projectUid, $taskUid, $employee);

            DB::commit();

            return generalResponse(
                __('global.taskIsCompletedAndContinue'),
                false,
                $payloadOutput
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Here we get list of projects that will be shown in the authorized user calendar
     *
     * Based on role, director, root and marketing should be see all events.
     * Other role like Project manager, production and else will be see only project that already assign to them
     */
    public function getProjectCalendars(): array
    {
        $user = Auth::user();

        $where = '';
        if (request('search_date')) {
            $searchDate = date('Y-m-d', strtotime(request('search_date')));
        } else {
            $searchDate = date('Y-m-d');
        }

        $year = date('Y', strtotime($searchDate));
        $month = date('m', strtotime($searchDate));
        $start = $year.'-'.$month.'-01';
        $end = $year.'-'.$month.'-30';
        $where = "project_date >= '".$start."' and project_date <= '".$end."'";

        $grouping = [];

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
                'role' => $user->hasRole([BaseRole::Marketing->value, BaseRole::Root->value, BaseRole::Director->value]),
            ],
        );
    }

    /**
     * Get boards of selected project
     */
    public function getProjectBoards(string $projectUid): array
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

        $data = $this->boardRepo->list('id as value,name as title', 'project_id = '.$projectId);

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

    public function getTaskStatus()
    {
        $data = \App\Enums\Production\TaskStatus::cases();

        $out = [];
        foreach ($data as $status) {
            $out[] = [
                'value' => $status->value,
                'title' => $status->label(),
            ];
        }

        return generalResponse(
            message: 'Success',
            data: $out
        );
    }

    public function getProjectStatusses(string $projectUid): array
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

            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            if ($data['base_status'] == \App\Enums\Production\ProjectStatus::Draft->value && $data['status'] == \App\Enums\Production\ProjectStatus::OnGoing->value) {
                // get task pic with status task is waiting approval
                // then send a notification

                $tasks = $this->taskRepo->list('id,project_id', 'project_id = '.$projectId, ['pics']);

                foreach ($tasks as $task) {
                    $employeeIds = collect($task->pics)->pluck('employee_id')->toArray();

                    foreach ($employeeIds as $employeeId) {
                        $userData = $this->userRepo->detail(select: 'id', where: "employee_id = {$employeeId}");

                        \Modules\Production\Jobs\AssignTaskJob::dispatch($employeeIds, $task->id, $userData);
                    }
                }
            }

            $project = $this->repo->show($projectUid, 'id,status,uid');

            $currentData = $this->detailCacheAction->handle(
                projectUid: $projectUid,
                necessaryUpdate: [
                    'status_raw' => $project->status,
                    'status' => $project->status_text,
                    'status_color' => $project->status_color,
                ]
            );

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
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $superUserRole = getSettingByKey('super_user_role');
            $user = auth()->user();
            $roles = $user->roles;
            $roleId = $roles[0]->id;

            $projectManagerPosition = json_decode(getSettingByKey('position_as_project_manager'), true);

            $where = '';

            if (count($projectManagerPosition) > 0) {
                $projectManagerPosition = collect($projectManagerPosition)->map(function ($item) {
                    return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
                })->toArray();

                $positionIds = implode("','", $projectManagerPosition);
                $positionIds = "('".$positionIds."')";

                // condition when super admin take this role
                $projectPics = $this->projectPicRepository->list('id,pic_id', 'project_id = '.$projectId);
                $picIds = collect($projectPics)->pluck('pic_id')->toArray();
                $adminCondition = implode("','", $picIds);
                $adminCondition = "('".$adminCondition."')";

                $where = 'position_id in '.$positionIds.' and id not in '.$adminCondition;

                if ($roleId != $superUserRole) {
                    $where = 'position_id in '.$positionIds.' and id != '.$user->employee_id;
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

            $projects = $this->taskRepo->list('id,uid,name', 'project_id = '.$projectId);
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
     */
    public function getPicTeams(string $projectUid, string $picUid): array
    {
        try {
            $bossId = getIdFromUid($picUid, new \Modules\Hrd\Models\Employee);

            // $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

            $project = $this->repo->show($projectUid, 'id,name,status,project_date');

            $projectDate = $project->project_date;

            $startDate = date('Y-m-d', strtotime('-7 days', strtotime($projectDate)));
            $endDate = date('Y-m-d', strtotime('+7 days', strtotime($projectDate)));

            $taskDateCondition = "project_date >= '".$startDate."' and project_date <= '".$endDate."'";

            // get boss user data and role
            // make special condition for PM Entertaintment
            $bossData = \App\Models\User::where('employee_id', $bossId)->first();

            if (! $bossData) {
                throw new NotRegisteredAsUser;
            }

            $bossIsPMEntertainment = false;
            if ($bossData->hasRole('project manager entertainment')) {
                $bossIsPMEntertainment = true;
            }

            // get production and operator position
            $productionPosition = json_decode(getSettingByKey('position_as_production'), true);
            $operatorPosition = json_decode(getSettingByKey('position_as_visual_jokey'), true);

            if (! $productionPosition || ! $operatorPosition) {
                throw new failedToProcess(__('notification.failedToGetTeams'));
            }

            if ($bossIsPMEntertainment) {
                $operatorPosition = collect($operatorPosition)->map(function ($item) {
                    return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
                })->toArray();
                $positionCondition = "'";
                $positionCondition .= implode("','", $operatorPosition)."'";
            } else {
                $productionPosition = collect($productionPosition)->map(function ($item) {
                    return getIdFromUid($item, new \Modules\Company\Models\PositionBackup);
                })->toArray();
                $positionCondition = "'";
                $positionCondition .= implode("','", $productionPosition)."'";
            }

            $where = "boss_id = {$bossId} and status != ".Status::Inactive->value." and position_id IN ({$positionCondition})";
            $userApp = auth()->user();

            if (($userApp) && ($userApp->employee_id) && ! $bossIsPMEntertainment) {
                $where .= ' and id != '.$userApp->employee_id;
            }

            if ($bossIsPMEntertainment) {
                $where .= ' or id = '.$bossId;
            }

            $data = $this->employeeRepo->list('id,uid,name,email', $where);

            $output = collect($data)->map(function ($item) use ($projectDate, $taskDateCondition) {
                $taskOnProjectDate = $this->taskPicRepo->list(
                    'id,project_task_id',
                    'employee_id = '.$item->id,
                    [
                        'task' => function ($query) use ($taskDateCondition) {
                            $query->selectRaw('id,project_id')
                                ->whereHas('project', function ($q) use ($taskDateCondition) {
                                    $q->whereRaw($taskDateCondition);
                                });
                        },
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
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $project = $this->repo->show($projectUid, 'id,name,project_date', ['personInCharges:id,project_id,pic_id']);

            $requestTo = getIdFromUid($data['pic_id'], new \Modules\Hrd\Models\Employee);

            $user = auth()->user();
            $roles = $user->roles;
            $roleId = $roles[0]->id;

            if ($roleId == getSettingByKey('super_user_role')) {
                $requestedBy = $project->personInCharges[0]->pic_id;
            } else {
                $requestedBy = $user->employee_id;
            }

            foreach ($data['teams'] as $team) {
                $teamId = getIdFromUid($team, new \Modules\Hrd\Models\Employee);
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
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
        try {
            // get current showreel
            $project = $this->repo->show($projectUid, 'id,showreels');
            $currentShowreels = $project->showreels;

            $tmpFile = uploadFile(
                'projects/'.$projectId.'/showreels',
                $data['file']
            );

            $this->repo->update([
                'showreels' => $tmpFile,
            ], $projectUid);

            $currentData = getCache('detailProject'.$projectId);

            $currentData = $this->formatTasksPermission($currentData, $projectId);

            // delete current showreels
            if ($currentShowreels) {
                if (is_file(storage_path('app/public/projects/'.$projectId.'/showreels/'.$currentShowreels))) {
                    unlink(
                        storage_path('app/public/projects/'.$projectId.'/showreels/'.$currentShowreels)
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

    public function getTaskTeamForReview(string $projectUid): array
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

        $project = $this->repo->show($projectUid, 'id,uid,event_type,classification,name,project_date');

        $projectTeams = $this->getProjectTeams(
            project: $project,
            forceGetSpecialTeam: true
        );
        $teams = $projectTeams['teams'];
        $pics = $projectTeams['picUids'];

        // format with task details
        $output = [];
        foreach ($teams as $team) {
            $tasks = $this->employeeTaskStateRepo->list(
                select: 'id,project_id,project_task_id,project_board_id,employee_id',
                where: "employee_id = {$team['id']} AND project_id = {$projectId}",
                relation: [
                    'task:id,name',
                ]
            );

            $output[] = [
                'uid' => $team['uid'],
                'name' => $team['name'],
                'total_task' => $tasks->count(),
                'point' => $tasks->count(),
                'additional_point' => 0,
                'can_decrease_point' => false,
                'can_increase_point' => true,
                'tasks' => collect($tasks)->pluck('project_task_id')->values()->toArray(),
            ];
        }

        return generalResponse(
            'success',
            false,
            $output
        );

        $histories = $this->taskPicHistory->list('DISTINCT project_id,project_task_id,employee_id', 'project_id = '.$projectId, ['employee:id,name,employee_id,uid,nickname']);

        $data = collect((object) $histories)->map(function ($item) {
            return [
                'id' => $item->id,
                'employee' => $item->employee->name.' ('.$item->employee->employee_id.')',
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
                'can_increase_point' => true,
                'tasks' => collect($employee)->pluck('project_task_id')->toArray(),
            ];
        }

        $rawData = collect($output)->values()->pluck('uid')->toArray();

        foreach ($teams as $team) {
            if (! in_array($team['uid'], $rawData)) {
                array_push($output, [
                    'uid' => $team['uid'],
                    'name' => $team['name'],
                    'total_task' => 0,
                    'point' => 0,
                    'additional_point' => 0,
                    'can_decrease_point' => false,
                    'can_increase_point' => true,
                    'tasks' => [],
                ]);
            }
        }

        // remove pic in list
        $output = collect($output)->filter(function ($filter) use ($pics) {
            return ! in_array($filter['uid'], $pics);
        })->values()->toArray();

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    /**
     * Function to create a review in project and each teams
     *
     * $data is:
     * string feedback
     * array points
     */
    public function completeProject(array $data, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            if (! empty($data['points'])) {
                PointRecord::run($data, $projectUid, 'production');
            }

            $this->repo->update([
                'feedback' => $data['feedback'],
                'status' => \App\Enums\Production\ProjectStatus::Completed->value,
            ], $projectUid);

            // update project equipment
            $this->projectEquipmentRepo->update([
                'status' => \App\Enums\Production\RequestEquipmentStatus::CompleteAndNotReturn->value,
            ], 'dummy', 'project_id = '.$projectId);

            // update project status cache
            $project = $this->repo->show($projectUid, 'id,status');

            $currentData = $this->detailCacheAction->handle(
                projectUid: $projectUid,
                forceUpdateAll: true
            );

            // modify cache if exists
            $needCompleteCache = $this->generalService->getCache(CacheKey::ProjectNeedToBeComplete->value.auth()->id());
            if ($needCompleteCache) {
                $needCompleteCache = collect($needCompleteCache)->filter(function ($filter) use ($projectUid) {
                    return $filter['uid'] != $projectUid;
                })->values()->toArray();

                $this->generalService->storeCache(CacheKey::ProjectNeedToBeComplete->value.auth()->id(), $needCompleteCache);
            }

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
                        'employee_id' => getIdFromUid($item, new \Modules\Hrd\Models\Employee),
                        'created_by' => auth()->user()->employee_id ?? 0,
                    ];
                })->toArray()
            );

            \Modules\Production\Jobs\AssignVjJob::dispatch($project, $data)->afterCommit();

            $project = $this->repo->show(
                uid: $projectUid,
                select: 'id',
                relation: [
                    'vjs:id,project_id,employee_id',
                    'vjs.employee:id,nickname',
                ]
            );

            $currentData = $this->detailCacheAction->handle(
                projectUid: $projectUid,
                necessaryUpdate: [
                    // update vj
                    'vjs' => $project->vjs,
                ]
            );

            DB::commit();

            return generalResponse(
                __('global.vjHasBeenAssigned'),
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
     * Function to get all items for final check
     */
    public function prepareFinalCheck(string $projectUid): array
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $project = $this->repo->show($projectUid, 'id,name,showreels,showreels_status', [
                'vjs.employee:id,nickname',
                'equipments:id,project_id,inventory_id,qty,status',
                'equipments.inventory:id,name',
            ]);

            // get tasks information
            $tasks = $this->taskRepo->list('id,project_id,status', 'project_id = '.$projectId.' and status is not null');
            $completedTask = collect($tasks)->where('status', '=', \App\Enums\Production\TaskStatus::Completed->value)->count();
            $unfinished = $tasks->count() - $completedTask;
            $taskData = [
                'total' => $tasks->count(),
                'completed' => $completedTask,
                'unfinished' => $tasks->count() - $completedTask,
                'text' => __('global.reviewTaskData', ['total' => $tasks->count(), 'unfinished' => $unfinished]),
            ];

            // get showreels status
            $showreels = [
                'text' => __('global.doesNotHaveShowreels'),
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
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $equipments = $this->projectEquipmentRepo->list('id,inventory_id,inventory_code', 'project_id = '.$projectId);

            foreach ($equipments as $equipment) {
                $this->inventoryItemRepo->update([
                    'status' => \App\Enums\Inventory\InventoryStatus::OnSite->value,
                    'current_location' => \App\Enums\Inventory\Location::Outgoing->value,
                ], 'dummy', "inventory_code = '".$equipment->inventory_code."'");
            }

            // update equipment status
            $this->projectEquipmentRepo->update([
                'status' => \App\Enums\Production\RequestEquipmentStatus::OnEvent->value,
            ], 'dummy', 'project_id = '.$projectId);

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
     */
    public function returnEquipment(string $projectUid, array $payload): array
    {
        DB::beginTransaction();
        try {
            foreach ($payload['equipment'] as $item) {
                $this->projectEquipmentRepo->update([
                    'status' => \App\Enums\Production\RequestEquipmentStatus::Return->value,
                    'is_good_condition' => $item['return_condition']['is_good_condition'],
                    'detail_condition' => ! $item['return_condition']['is_good_condition'] ? $item['return_condition']['detail_condition'] : null,
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

        $references = collect($project->references)->filter(function ($item) {
            return $item->type != 'link';
        })->map(function ($mapping) use ($projectId) {
            return storage_path('app/public/projects/references/'.$projectId.'/'.$mapping->media_path);
        })->values();

        return [
            'files' => $references->toArray(),
            'project' => $project,
        ];
    }

    /**
     * Get All PIC / Project Manager and get each workload
     * Provide all information to user
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
     */
    protected function mainProcessToGetPicScheduler(string $projectUid): array
    {
        $project = $this->repo->show($projectUid, 'id,name,project_date');
        $startDate = date('Y-m-d', strtotime('-7 days', strtotime($project->project_date)));
        $endDate = date('Y-m-d', strtotime('+7 days', strtotime($project->project_date)));

        $userPics = \App\Models\User::role('project manager')->get();
        $userPicsAdmin = \App\Models\User::role('project manager admin')->get();
        $assistant = \App\Models\User::role('assistant manager')->get();
        $director = \App\Models\User::role('director')->get();
        $pics = collect($userPics)->merge($director)->merge($assistant)->merge($userPicsAdmin)->toArray();

        // get all workload in each pics
        $output = [];
        foreach ($pics as $key => $pic) {
            if ($pic['employee_id']) {
                $employee = $this->employeeRepo->show('dummy', 'id,uid,name,email,employee_id', [], 'id = '.$pic['employee_id']);

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
     * @param  object  $pic
     * @param  string  $projectUId
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
                    'query' => 'pic_id = '.$pic->id,
                ],
            ]
        );

        // group by some data like out of town, total project and event class
        $eventClass = 0;
        $totalOfProject = 0;
        $totalOutOfTown = 0;

        if (count($projects) > 0) {
            $totalOfProject = count($projects);

            // get total event class
            $eventClass = collect((object) $projects)->pluck('classification')->filter(function ($itemClass) {
                return strtolower($itemClass) == 's (spesial)' || strtolower($itemClass) == 's (special)';
            })->count();

            foreach ($projects as $project) {
                if (! in_array($project->city_id, collect($surabaya)->pluck('id')->toArray())) {
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
     * @param  array<array, string>  $data
     */
    public function assignPic(string $projectUid, array $data)
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $this->handleAssignPicLogic($data, $projectUid, $projectId);

            // update cache
            $currentData = $this->detailCacheAction->handle($projectUid);

            if (! $currentData) {
                $currentData = $this->reinitDetailCache((object) ['id' => $projectId, 'uid' => $projectUid]);
            }

            // new pics
            $newPics = $this->projectPicRepository->list('pic_id', "project_id = {$projectId}", ['employee:id,uid,name,employee_id']);

            $currentData['pic'] = implode(',', collect($newPics)->pluck('employee.name')->toArray());
            $currentData['pic_ids'] = collect($newPics)->pluck('employee.uid')->toArray();

            $listUpdated = [
                'pic' => $currentData['pic'],
                'no_pic' => false,
                'pic_eid' => collect((object) $newPics)->pluck('employee.employee_id')->toArray(),
            ];

            $currentData = FormatTaskPermission::run($currentData, $projectId);

            DB::commit();

            return generalResponse(
                __('global.successAssignPIC'),
                false,
                [
                    'full_detail' => $currentData,
                    'list_updated' => $listUpdated,
                ],
            );
        } catch (\Throwable $error) {
            DB::rollBack();

            return errorResponse($error);
        }
    }

    /**
     * Main function to handle assignation PIC to selected project
     *
     * @param  array<string, array<string>>  $data
     */
    protected function handleAssignPicLogic(array $data, string $projectUid, int $projectId): void
    {
        foreach ($data['pics'] as $pic) {
            $employeeId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee);
            $this->projectPicRepository->store(['pic_id' => $employeeId, 'project_id' => $projectId]);
        }

        \Modules\Production\Jobs\NewProjectJob::dispatch($projectUid)->afterCommit();
    }

    /**
     * Remove all selected pic form selected project
     *
     * @param  araray<string>  $picList
     */
    protected function removePicProject(array $picList, string $projectUid, int $projectId): void
    {
        $ids = [];
        foreach ($picList as $list) {
            $employeeId = getIdFromUid($list, new \Modules\Hrd\Models\Employee);
            $ids[] = $employeeId;
            $this->projectPicRepository->delete(0, "project_id = {$projectId} and pic_id = {$employeeId}");
        }

        // notified removed user
        \Modules\Production\Jobs\RemovePMFromProjectJob::dispatch($ids, $projectUid)->afterCommit();
    }

    /**
     * Assign new pic or remove current pic of project
     *
     * @param  array<string, array<string>>  $data
     */
    public function subtitutePic(string $projectUid, array $data): array
    {
        DB::beginTransaction();
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            // handle new pic
            if (count($data['pics']) > 0) {
                $this->handleAssignPicLogic($data, $projectUid, $projectId);
            }

            // handle removed pic
            if (count($data['removed']) > 0) {
                $this->removePicProject($data['removed'], $projectUid, $projectId);
            }

            // update cache
            // $currentData = getCache('detailProject'.$projectId);
            // if (! $currentData) {
            //     $currentData = $this->reinitDetailCache((object) ['id' => $projectId, 'uid' => $projectUid]);
            // }

            // new pics
            $newPics = $this->projectPicRepository->list('pic_id', "project_id = {$projectId}", ['employee:id,uid,name,employee_id']);

            // $currentData['pic'] = implode(',', collect($newPics)->pluck('employee.name')->toArray());
            // $currentData['pic_ids'] = collect($newPics)->pluck('employee.uid')->toArray();

            // $currentData = $this->formatTasksPermission($currentData, $projectId);

            $currentData = $this->detailCacheAction->run($projectUid, [], true);

            // update list project
            $noPic = $newPics->count() > 0 ? false : true;
            $listUpdated = [
                'pic' => ! $noPic ? $currentData['pic'] : __('global.undetermined'),
                'no_pic' => $noPic,
                'pic_eid' => collect((object) $newPics)->pluck('employee.employee_id')->toArray(),
            ];

            DB::commit();

            return generalResponse(
                __('notification.projectPicHasBeenChanged'),
                false,
                [
                    'full_detail' => $currentData ?? [],
                    'list_updated' => $listUpdated,
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
     * @param  string  $projectUId
     */
    public function getPicForSubtitute(string $projectUid): array
    {
        try {
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $pics = $this->mainProcessToGetPicScheduler($projectUid);
            $selectedPic = $this->projectPicRepository->list(
                'id,project_id,pic_id',
                "project_id = {$projectId}",
                ['employee:id,uid,name,email,employee_id'],
            );

            $pics = collect($pics)->filter(function ($filter) use ($selectedPic) {
                return ! in_array(
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
                    'available_pic' => $pics,
                ],
            );
        } catch (\Throwable $e) {
            return errorResponse($e);
        }
    }

    /**
     * Download all media in proof of work
     */
    public function downloadProofOfWork(string $projectUid, int $proofOfWorkId)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

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
     * @param  int  $proofOfWorkId
     */
    public function downloadReviseMedia(string $projectUid, int $reviseId)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

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
        $startDate = $year.'-01-01';
        $endDate = $year.'-12-31';

        $where = "project_date between '{$startDate}' and '{$endDate}'";

        if (request('name')) {
            $where .= " and lower(name) like '%".strtolower(request('name'))."%'";
        }

        if ($isMyFile) {
            // identity user
            $user = auth()->user();
            if ($user->email != config('app.root_email')) {
                if ($user->is_employee) {
                    $userProjectIds = $this->taskPicHistory->list('project_id', 'employee_id = '.$user->employee_id);
                    $userProjectIds = collect($userProjectIds)->pluck('project_id')->toArray();
                    $userProjectIds = implode(',', $userProjectIds);
                } elseif ($user->is_project_manager) {
                    $userProjectIds = $this->projectPicRepository->list('project_id', 'pic_id = '.$user->employee_id);
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
                    'page' => (int) request('page'),
                ],
                'is_my_files' => $isMyFile,
            ],
        );
    }

    /**
     * Get all available years in company
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
            $where .= ' and project_task_id = '.request('task');
            $relation = ['user:id,employee_id', 'user.employee:id,name', 'task:id,name'];
        }

        $user = null;
        if (request('user')) {
            $where .= ' and created_by = '.request('user');

            // search user
            $userData = \App\Models\User::select('employee_id')
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
     */
    protected function getProjectTasks(object $project): array
    {
        $where = "project_id = {$project->id}";

        if (request('name')) {
            $where .= " and lower(name) like '%".strtolower(request('name'))."%'";
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
                $q->whereRaw("lower(name) like '%".strtolower(request('name'))."%' or lower(nickname) like '%".strtolower(request('name'))."%' or lower(email) like '%".strtolower(request('name'))."%'");
            }]);
        } else {
            $query->with(['employee:id,name']);
        }

        $productionUsers = collect((object) $query->get())->filter(function ($filter) {
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
        } elseif ($type == 'user') {
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
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $this->deleteProjectPic($projectId);

            $this->repo->update(['status' => $data['status']], 'dummy', "uid = '{$projectUid}'");

            \Modules\Production\Jobs\CancelProjectWithPicJob::dispatch($data['pic_list'], $projectUid)->afterCommit();

            // update cache
            if ($currentData = getCache('detailProject'.$projectId)) {
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
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);

            $project = $this->repo->show($projectUid, 'id,name,project_date');

            $entertainmentPic = \App\Models\User::role('project manager entertainment')->first();

            $user = auth()->user();

            $employeeIds = [];

            if ($payload['default_select']) {
                $this->transferTeamRepo->store([
                    'project_id' => $projectId,
                    'employee_id' => null,
                    'reason' => 'Untuk event '.$project->name,
                    'project_date' => $project->project_date,
                    'status' => \App\Enums\Production\TransferTeamStatus::Requested->value,
                    'request_to' => $entertainmentPic->employee_id,
                    'requested_by' => $user->employee_id,
                    'is_entertainment' => 1,
                ]);
            } else {
                foreach ($payload['team'] as $team) {
                    $employeeId = getIdFromUid($team, new \Modules\Hrd\Models\Employee);

                    $employeeIds[] = $employeeId;

                    $this->transferTeamRepo->store([
                        'project_id' => $projectId,
                        'employee_id' => $employeeId,
                        'reason' => 'Untuk event '.$project->name,
                        'project_date' => $project->project_date,
                        'status' => \App\Enums\Production\TransferTeamStatus::Requested->value,
                        'request_to' => $entertainmentPic->employee_id,
                        'requested_by' => $user->employee_id,
                        'is_entertainment' => 1,
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

    public function getEmployeeTaskList(string $projectUid, int $employeeId)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project);
        $tasks = $this->taskPicHistory->list('distinct(project_task_id),project_id,employee_id', "employee_id = {$employeeId} and project_id = {$projectId}", ['task:id,name,status,created_at', 'task.proofOfWorks:project_task_id,project_id,nas_link,preview_image']);

        $output = [];
        foreach ($tasks as $task) {
            $output[] = [
                'name' => $task->task->name,
                'status' => $task->task->task_status,
                'created_at' => date('d F Y', strtotime($task->task->created_at)),
                'proof_of_works' => collect((object) $task->task->proofOfWorks)->map(function ($item) {
                    return [
                        'images' => $item->images,
                        'nas_link' => $item->nas_link,
                    ];
                })->toArray(),
                'task_status_color' => $task->task->task_status_color,
            ];
        }

        return generalResponse(
            'success',
            false,
            $output
        );
    }

    /**
     * Store song lists
     */
    public function storeSongs(array $payload, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->show($projectUid, 'id,name,project_date', ['songs']);

            $createdBy = auth()->id();

            $songs = [];
            foreach ($payload['songs'] as $song) {
                $songs[] = new ProjectSongList([
                    'name' => $song,
                    'created_by' => $createdBy,
                ]);
            }
            $project->songs()->saveMany($songs);

            // send notification
            RequestSongJob::dispatch($project, $payload['songs'], $createdBy)->afterCommit();

            // get current data
            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.songHasBeenAdded'),
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all entertainment team member with workload on selected project
     */
    public function entertainmentMemberWorkload(string $projectUid): array
    {
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
            $users = \App\Models\User::role([BaseRole::Entertainment->value, BaseRole::ProjectManagerEntertainment->value])
                ->with([
                    'employee' => function ($query) use ($projectId) {
                        $query->selectRaw('id,name,uid,employee_id')
                            ->with([
                                'songTasks' => function ($taskQuery) use ($projectId) {
                                    $taskQuery->selectRaw('id,project_song_list_id,employee_id,project_id,status')
                                        ->where('project_id', $projectId)
                                        ->with('song:id,name,uid');
                                },
                            ]);
                    },
                ])
                ->get();

            $output = collect($users)->map(function ($user) {
                return [
                    'uid' => $user->employee->uid,
                    'name' => $user->employee->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee->employee_id,
                    'tasks' => collect($user->employee->songTasks)->map(function ($task) {
                        return [
                            'uid' => $task->song->uid,
                            'name' => $task->song->name,
                        ];
                    }),
                ];
            })->toArray();

            return generalResponse(
                message: 'success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to check update song
     * Do validation before edit the song
     */
    public function updateSong(array $payload, string $projectUid, string $songUid): array
    {
        try {
            // check validation
            $song = $this->projectSongListRepo->show(
                $songUid,
                'id,project_id,name',
                [
                    'task:id,project_song_list_id,employee_id',
                    'task.employee:id,name,nickname',
                ]
            );
            $currentName = $song->name;

            if (! $song) {
                throw new SongNotFound;
            }

            if ($song->task) {
                // request changes to entertainment first
                $this->projectSongListRepo->update([
                    'is_request_edit' => true,
                    'is_request_delete' => false,
                    'target_name' => $payload['song'],
                ], $songUid);

                // send notification to PM entertainment
                $requesterId = auth()->id();
                RequestEditSongJob::dispatch($payload, $projectUid, $songUid, $requesterId)->afterCommit();

                // log this request
                StoreLogAction::run(
                    type: TaskSongLogType::RequestToEditSong->value,
                    payload: [
                        'project_song_list_id' => $song->id,
                        'project_id' => $song->project_id,
                        'employee_id' => null,
                    ],
                    params: [
                        'author' => auth()->user()->load('employee')->employee->nickname,
                    ]
                );

                goto result;
            }

            // do edit when available
            $this->doEditSong(payload: ['name' => $payload['song']], songUid: $songUid);

            // log the changes
            StoreLogAction::run(
                type: TaskSongLogType::EditSong->value,
                payload: [
                    'project_song_list_id' => $song->id,
                    'project_id' => $song->project_id,
                    'employee_id' => null,
                ],
                params: [
                    'author' => auth()->user()->load('employee')->employee->nickname,
                    'currentName' => $currentName,
                    'newName' => $payload['song'],
                ]
            );

            result:
            // get current data
            $currentData = $this->detailCacheAction->run($projectUid);

            return generalResponse(
                $song->task ? __('notification.successUpdateDistributedSong') : __('notification.successUpdateSong'),
                false,
                [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Function to approve edit request
     * This function will notify the changes to PM project and current worker
     */
    public function confirmEditSong(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $detail = $this->projectSongListRepo->show(
                uid: $songUid,
                select: 'id,name,target_name',
                relation: [
                    'task:id,project_song_list_id,employee_id',
                ],
                where: "uid = '{$songUid}' and is_request_edit = 1 and is_request_delete = 0"
            );

            if (! $detail) {
                throw new SongNotFound;
            }

            $currentWorkerId = $detail->task->employee_id;
            $currentName = $detail->name;
            $newName = $detail->target_name;

            $this->doEditSong(
                payload: [
                    'name' => $detail->target_name,
                    'target_name' => null,
                    'is_request_edit' => false,
                    'is_request_delete' => false,
                ],
                songUid: $songUid
            );

            // logging task
            $user = $this->employeeRepo->show(
                uid: 'id',
                select: 'id,nickname',
                where: 'user_id = '.auth()->id()
            );

            $event = $this->repo->show(
                uid: $projectUid,
                select: 'id,name'
            );

            $this->entertainmentTaskSongLogService->storeLog(
                type: TaskSongLogType::ApprovedRequestEdit->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                    'employee_id' => null,
                ],
                params: [
                    'pm' => $user->nickname ?? 'Unknown',
                    'event' => $event->name,
                    'currentName' => $currentName,
                    'newName' => $newName,
                ]
            );

            $currentData = $this->detailCacheAction->handle($projectUid);

            SongApprovedToBeEditedJob::dispatch($currentName, $newName, $currentWorkerId, $projectId)->afterCommit();

            DB::commit();

            return generalResponse(
                message: 'success',
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to delete song
     */
    public function confirmDeleteSong(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);

            $detail = $this->projectSongListRepo->show(
                uid: $songUid,
                select: 'id,name,target_name',
                relation: [
                    'task:id,project_song_list_id,employee_id',
                ],
                where: "uid = '{$songUid}' and is_request_edit = 0 and is_request_delete = 1"
            );

            if (! $detail) {
                throw new SongNotFound;
            }

            $currentSongName = $detail->name;
            $currentWorker = $detail->task->employee_id;

            // detach people
            $this->entertainmentTaskSongRepo->delete(0, 'employee_id = '.$currentWorker." and project_song_list_id = {$songId}");

            // delete data
            $this->projectSongListRepo->delete($songId);

            ConfirmDeleteSongJob::dispatch($currentSongName, $currentWorker, $projectUid)->afterCommit();

            // reformat cache
            $detailData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.successDeleteSong'),
                error: false,
                data: [
                    'full_detail' => $detailData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update song
     */
    public function doEditSong(array $payload, string $songUid): bool
    {
        $this->projectSongListRepo->update($payload, $songUid);

        return true;
    }

    /**
     * Reject edit song
     */
    public function rejectEditSong(array $payload, string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $this->projectSongListRepo->update([
                'reason' => $payload['reason'],
                'is_request_delete' => 0,
                'is_request_edit' => 0,
                'target_name' => null,
            ], $songUid);

            $author = $this->employeeRepo->show(
                uid: 'id',
                select: 'id,nickname',
                where: 'user_id = '.auth()->id()
            );

            StoreLogAction::run(
                type: TaskSongLogType::RejectRequestEdit->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'entertainment_task_song_id' => 0,
                    'project_id' => $projectId,
                    'employee_id' => null,
                ],
                params: [
                    'pm' => $author->nickname,
                ]
            );

            RejectRequestEditSongJob::dispatch($payload, $projectUid, $songUid)->afterCommit();

            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.requestEditSongHasBeenRejected'),
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Delete song
     */
    public function deleteSong(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            // check validation
            $song = $this->projectSongListRepo->show(
                $songUid,
                'id,project_id,name',
                [
                    'task:id,project_song_list_id,employee_id',
                    'task.employee:id,name,nickname',
                    'project:id,name',
                ]
            );

            if (! $song) {
                throw new SongNotFound;
            }

            if ($song->is_request_edit) {
                throw new FailedModifyWaitingApprovalSong(message: __('notification.failedDeleteRequestEditSong'));
            }

            if ($song->task) {
                // request changes to entertainment first
                $this->projectSongListRepo->update([
                    'is_request_edit' => false,
                    'is_request_delete' => true,
                    'target_name' => null,
                ], $songUid);

                // send notification to PM entertainment
                $requesterId = auth()->id();
                RequestDeleteSongJob::dispatch($song, $requesterId);

                goto result;
            }

            $this->doDeleteSong($song->id, $song);

            result:
            // get current data
            $currentData = $this->detailCacheAction->run($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.successDeleteSong'),
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Actual function to delete song
     */
    public function doDeleteSong(int $songId, object $song): void
    {
        $songName = $song->name;
        $projectName = $song->project->name;
        $this->projectSongListRepo->delete(id: $songId);

        $requesterId = auth()->id();
        DeleteSongJob::dispatch($songName, $projectName, $requesterId)->afterCommit();
    }

    /**
     * Distribute song to selected employee
     */
    public function distributeSong(array $payload, string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $employeeId = $this->generalService->getIdFromUid($payload['employee_uid'], new Employee);

            $song = $this->projectSongListRepo->show($songUid, 'id');

            if (! $song) {
                throw new SongNotFound;
            }

            // check assignment to prevent double job
            $currentSongTask = $this->entertainmentTaskSongRepo->show(
                uid: $songUid,
                select: 'id,employee_id',
                relation: [
                    'employee:id,nickname',
                ],
                where: "employee_id = {$employeeId} and project_song_list_id = {$song->id}"
            );

            if ($currentSongTask) {
                DB::rollBack();

                return generalResponse(
                    message: __('notification.employeeAlreadyAssignedForThisSong', ['name' => $currentSongTask->employee->nickname]),
                    error: false,
                );
            }

            DistributeSong::run($payload, $projectUid, $songUid, $this->generalService);

            // get current datad
            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.songHasBeenDistributed'),
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to get detail information of selected song
     *
     * @param  sting  $projectUid
     */
    public function detailSong(string $projectUid, string $songUid): array
    {
        try {
            $user = auth()->user();
            $data = $this->projectSongListRepo->show(
                uid: $songUid,
                select: 'id,project_id,uid,name,is_request_edit,is_request_delete,target_name',
                relation: [
                    'project:id,uid,name,project_date',
                    'logs:id,project_song_list_id,text,param_text,created_at',
                    'task:id,project_song_list_id,employee_id,status,created_at',
                    'task.employee:id,name,employee_id,uid',
                    'task.results:id,task_id,nas_path,note',
                    'task.results.images:id,result_id,path',
                    'task.revises:project_song_list_id,entertainment_task_song_id,id,reason,created_at',
                ]
            );
            // format results
            if (($data->task) && (! $data->task->results->isEmpty())) {
                $path = asset("storage/projects/{$data->project_id}/entertainment/song/{$data->id}");
                $results = collect($data->task->results)->map(function ($item) use ($path) {
                    return [
                        'images' => collect($item->images)->map(function ($image) use ($path) {
                            return $path.'/'.$image->path;
                        })->toArray(),
                        'note' => $item->note,
                        'nas_path' => $item->nas_path,
                    ];
                })->toArray();
            }

            $data = $this->formatSingleSongStatus($data);

            $task = null;

            if ($data->task) {
                $task = [
                    'employee_uid' => $data->task->employee->uid,
                    'name' => $data->task->employee->name,
                    'employee_id' => $data->task->employee->employee_id,
                    'status' => TaskSongStatus::getLabel($data->task->status),
                    'status_color' => TaskSongStatus::getColor($data->task->status),
                    'revises' => $data->task->revises,
                ];
            }

            $logs = [
                'main' => [],
                'more' => [],
            ];
            $moreLogs = [];
            if (count($data->logs) > 0) {
                $rawLogs = collect($data->logs)->map(function ($logItem) {
                    return [
                        'text' => $logItem->formatted_text,
                        'time' => date('d F Y H:i', strtotime($logItem->created_at)),
                    ];
                })->toArray();

                $mainLogs = array_splice($rawLogs, 0, 3);
                $moreLogs = $rawLogs;

                $logs['main'] = $mainLogs;
                $logs['more'] = $moreLogs;
            }

            $allowedStatusToAction = [
                TaskSongStatus::OnFirstReview->value,
                TaskSongStatus::OnLastReview->value,
            ];

            if ($user->hasRole(BaseRole::ProjectManagerEntertainment->value)) {
                $allowedStatusToAction = [
                    TaskSongStatus::OnFirstReview->value,
                ];
            }
            if ($user->hasRole(BaseRole::ProjectManager->value) || $user->hasRole(BaseRole::ProjectManagerAdmin->value)) {
                $allowedStatusToAction = [
                    TaskSongStatus::OnLastReview->value,
                ];
            }

            $canTaskAction = ! $data->task ? false : (in_array($data->task->status, $allowedStatusToAction) ? true : false);

            $output = [
                'uid' => $data->uid,
                'name' => $data->name,
                'status_text' => $data->status_text,
                'status_color' => $data->status_color,
                'status_request' => $data->status_request,
                'can_take_action' => $canTaskAction,
                'target_name' => $data->target_name,
                'is_request_edit' => $data->is_request_edit,
                'is_request_delete' => $data->is_request_delete,
                'is_complete' => ! $data->task ? false : ($data->task->status == TaskSongStatus::Completed->value || $data->task->status == TaskSongStatus::Revise->value ? true : false),
                'results' => $results ?? [],
                'project' => [
                    'uid' => $data->project->uid,
                    'name' => $data->project->name,
                    'project_date' => $data->project->project_date ? date('d F Y', strtotime($data->project->project_date)) : '-',
                ],
                'worker' => $task,
                'logs' => $logs,
            ];

            return generalResponse(
                message: 'success',
                error: false,
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Change worker song
     */
    public function subtituteSongPic(array $payload, string $projectUid, string $songUid): array
    {
        $switch = SwitchSongWorker::run($payload['employee_uid'], $songUid);

        $currentData = $this->detailCacheAction->handle($projectUid);

        if (! $switch['error']) {
            return generalResponse(
                message: __('notification.successSubtituteSongPic'),
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } else {
            return errorResponse($switch['message']);
        }
    }

    /**
     * Function to start work. Time tracker will start here
     * This function will handle start to work on the first time and start doing revise
     */
    public function startWorkOnSong(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $task = $this->entertainmentTaskSongRepo->show(
                uid: 'id',
                select: 'id,time_tracker,status',
                where: "project_id = {$projectId} AND project_song_list_id = {$songId}"
            );

            if ($task->status == TaskSongStatus::OnProgress->value) {
                DB::rollBack();

                return errorResponse(message: __('notification.songAlreadyInProgress'));
            }

            // set time tracker
            $currentTimeTracker = $task->time_tracker;
            $currentTimeTracker[] = [
                'type' => $task->status == TaskSongStatus::Active->value ? 'start_working' : 'revise',
                'start_time' => date('Y-m-d H:i'),
                'end_time' => null,
            ];

            $this->entertainmentTaskSongRepo->update(
                data: [
                    'status' => TaskSongStatus::OnProgress->value,
                    'time_tracker' => $currentTimeTracker,
                ],
                where: "project_song_list_id = {$songId}"
            );

            // logging
            StoreLogAction::run(
                type: TaskSongLogType::StartWorking->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                    'employee_id' => null,
                ],
                params: [
                    'user' => auth()->user()->load('employee')->employee->nickname,
                ]
            );

            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.startWorkNow'),
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function used to user to report the task
     *
     * Goal of this function is:
     * 1. Task status will be change from onprogress to on first review
     * 2. Time tracker will be updated. Now start time and end time will be filled
     * 3. Store related proof of work to server
     * 3. Send notification to both worker and PM
     *
     * @param  array  $payloas
     */
    public function songReportAsDone(array $payload, string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);

            $task = $this->entertainmentTaskSongRepo->show(
                uid: 'id,project_id,project_song_list_id,status',
                select: 'id,time_tracker',
                relation: [
                    'project:id,name',
                    'song:id,name',
                ],
                where: "project_id = {$projectId} AND project_song_list_id = {$songId}"
            );

            if (! $task) {
                throw new SongHaveNoTask;
            }

            if ($task->status == TaskSongStatus::OnFirstReview->value || $task->status == TaskSongStatus::OnLastReview->value) {
                throw new TaskAlreadyBeingChecked;
            }

            // update time tracker
            // Tracker is should be exists. If not it'll return an error
            $currentTracker = $task->time_tracker;
            $lastTracker = array_pop($currentTracker);
            $lastTracker['end_time'] = date('Y-m-d H:i');
            array_push($currentTracker, $lastTracker);

            $this->entertainmentTaskSongRepo->update(
                data: [
                    'time_tracker' => $currentTracker,
                    'status' => TaskSongStatus::OnFirstReview->value,
                ],
                id: (string) $task->id
            );

            // logging
            StoreLogAction::run(
                type: TaskSongLogType::ReportAsDone->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                ],
                params: [
                    'user' => $user->load('employee')->employee->nickname,
                ]
            );

            ReportAsDone::run($payload, $projectUid, $songUid, $this->generalService);

            SongReportAsDone::dispatch($task, $user->load('employee')->employee->id)->afterCommit();

            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    protected function mainSongApproveWork(string $projectUid, string $songUid, object $user, bool $sendNotification = true, bool $forceToComplete = false, bool $forceRecordPoint = false)
    {
        $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
        $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

        $task = $this->entertainmentTaskSongRepo->show(
            uid: 'id',
            select: 'id,status,employee_id,project_song_list_id,project_id,time_tracker',
            where: "project_song_list_id = {$songId} and project_id = {$projectId}",
            relation: [
                'employee:id,nickname,telegram_chat_id,uid',
                'song:id,name',
                'project:id,name',
                'project.personInCharges:project_id,pic_id',
                'project.personInCharges.employee:id,nickname,telegram_chat_id',
            ]
        );

        $currentStatus = $task->status;

        if ($currentStatus == TaskSongStatus::OnFirstReview->value) {
            $payloadUpdate['status'] = TaskSongStatus::OnLastReview->value;
        } elseif ($currentStatus == TaskSongStatus::OnLastReview->value) {
            $payloadUpdate['status'] = TaskSongStatus::Completed->value;
        } elseif ($currentStatus == TaskSongStatus::OnProgress->value) {
            $payloadUpdate['status'] = TaskSongStatus::OnFirstReview->value;
        }

        if ($forceToComplete) {
            $payloadUpdate['status'] = TaskSongStatus::Completed->value;
        }

        // record the point if entertainment PM do this action, do not record if this song came from revise task.
        // to check this song came from revise task or not, we will check the last time tracker type in the entertainment_task_songs
        // if $currentStatus is onLastReview, thats mean projectPM do this action
        if ($currentStatus == TaskSongStatus::OnFirstReview->value || $forceRecordPoint) {
            // build payload for point record
            $pointPayload = [
                'points' => [
                    [
                        'uid' => $task->employee->uid,
                        'point' => 1,
                        'additional_point' => 0,
                        'tasks' => [$task->id],
                    ],
                ],
            ];
            PointRecord::run(
                $pointPayload,
                $projectUid,
                'entertainment'
            );
        }

        // update status
        $this->entertainmentTaskSongRepo->update(
            data: $payloadUpdate,
            id: $task->id
        );

        // add logs
        if (BaseRole::ProjectManagerEntertainment->value == $user->roles[0]['name']) {
            StoreLogAction::run(
                type: TaskSongLogType::ApprovedByEntertainmentPM->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                ],
                params: [
                    'pm' => $user->load('employee')->employee->nickname,
                    'user' => $task->employee->nickname,
                ]
            );
        } else {
            // add root, director and other PM log
            StoreLogAction::run(
                type: TaskSongLogType::ApprovedByEventPM->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                ],
                params: [
                    'pm' => $user->load('employee')->employee->nickname,
                ]
            );
        }

        if ($sendNotification) {
            TaskSongApprovedJob::dispatch($task, $user->load('employee'))->afterCommit();
        }
    }

    /**
     * Function to approve song task.
     * This function hit by Project Manager or ROOT
     * Approve will work with these conditions:
     * 1. Status should be on progress -> author is PM Entertainment or root
     * 2. Status should be OnFirstReview -> author is Project Manager or root
     */
    public function songApproveWork(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $userRole = $user->roles[0];

            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $task = $this->entertainmentTaskSongRepo->show(
                uid: 'id',
                select: 'id,status,employee_id,project_song_list_id,project_id,time_tracker',
                where: "project_song_list_id = {$songId} and project_id = {$projectId}",
                relation: [
                    'employee:id,nickname,telegram_chat_id,uid',
                    'song:id,name',
                    'project:id,name',
                    'project.personInCharges:project_id,pic_id',
                    'project.personInCharges.employee:id,nickname,telegram_chat_id',
                ]
            );

            $currentStatus = $task->status;
            $payloadUpdate = [];

            // validate before go
            $allowedStatuses = [
                BaseRole::ProjectManagerEntertainment->value => [TaskSongStatus::OnFirstReview->value],
                BaseRole::Root->value => [TaskSongStatus::OnFirstReview->value, TaskSongStatus::OnLastReview->value],
                BaseRole::ProjectManager->value => [TaskSongStatus::OnLastReview->value],
                BaseRole::ProjectManagerAdmin->value => [TaskSongStatus::OnLastReview->value],
            ];

            // Check if role has permission for current status
            $isAllowed = false;
            foreach ($allowedStatuses as $role => $statuses) {
                if ($userRole->name === $role && in_array($currentStatus, $statuses)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (! $isAllowed) {
                DB::commit();

                return errorResponse(__('notification.failedToAproveTask'));
            }

            $this->mainSongApproveWork($projectUid, $songUid, $user);

            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.taskSongHasBeenApproved'),
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to get all entertainment member with the workload around project date
     */
    public function entertainmentListMember(string $projectUid): array
    {
        try {
            $project = $this->repo->show(
                uid: $projectUid,
                select: 'id,name,project_date',
            );

            // validate project
            if (! $project) {
                return errorResponse(message: __('notification.projectNotFound'), code: 500);
            }

            // get the workload -7 days, +7 days and in the selected project date
            $projectDate = Carbon::parse($project->project_date);
            $startDate = $projectDate->subDay(7)->format('Y-m-d');
            $endDate = $projectDate->addDay(7)->format('Y-m-d');

            // get entertainment peoples based on Entertainment Role
            $entertainments = \App\Models\User::selectRaw('id,employee_id,email')
                ->with([
                    'roles',
                    'employee' => function ($queryEmployee) {
                        return $queryEmployee->selectRaw('id,uid,name,employee_id')
                            ->whereRaw('deleted_at IS NULL');
                    },
                ])->get()->filter(
                    fn ($user) => $user->roles->whereIn('name', [BaseRole::Entertainment->value, BaseRole::ProjectManagerEntertainment->value])->toArray()
                );

            $output = [];
            foreach ($entertainments->values() as $key => $people) {
                if ($people->employee) {
                    $workload = $this->entertainmentTaskSongRepo->list(
                        select: 'id,project_song_list_id',
                        where: 'employee_id = '.$people->employee->id,
                        relation: [
                            'project' => function ($query) use ($startDate, $endDate) {
                                return $query->whereBetween('projectDate', [$startDate, $endDate]);
                            },
                        ]
                    )->count();

                    $output[] = [
                        'uid' => $people->employee->uid,
                        'name' => $people->employee->name,
                        'employee_id' => $people->employee->employee_id,
                        'workload' => $workload,
                    ];
                }
            }

            return generalResponse(
                message: 'success',
                error: false,
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    // TODO: Next development
    public function bulkAssignWorkerForSong(array $payload, string $projectUid)
    {
        DB::beginTransaction();
        try {
            $songUids = [];

            foreach ($payload['workers'] as $worker) {
                foreach ($worker['songs'] as $song) {
                    $songUids[] = $song;
                }
            }

            // validate unique songs
            $unique = array_unique($songUids);
            if (count($unique) != count($songUids)) {
                DB::rollBack();

                return errorResponse(__('notification.duplicateSongOnBulkAssign'));
            }

            // process
            foreach ($payload['workers'] as $worker) {
                foreach ($worker['songs'] as $songUid) {
                    DistributeSong::run(
                        [
                            'employee_uid' => $worker['uid'],
                        ],
                        $projectUid,
                        $songUid,
                        $this->generalService
                    );
                }
            }

            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.songHasBeenDistributed'),
                error: false,
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Revise the task of JB
     * What this function will do?
     * 1. Change status of task to revise (Base on \App\Enums\Production\TaskSongStatus)
     * 2. Store image if exists, and store the reason
     * 3. Inform worker
     * 4. Add to song logs
     * 5. Run notification
     */
    public function songRevise(array $payload, string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $user = auth()->user();

            $task = $this->entertainmentTaskSongRepo->show(
                uid: 'id',
                select: 'id,employee_id,status',
                where: "project_id = {$projectId} and project_song_list_id = {$songId}",
                relation: [
                    'employee:id,nickname',
                ]
            );

            // validate status
            if ($task->status == TaskSongStatus::Revise->value) {
                return errorResponse(__('notification.songAlreadyOnRevise'));
            }
            $allowedStatus = [
                TaskSongStatus::OnFirstReview->value,
                TaskSongStatus::OnLastReview->value,
            ];
            if (! in_array($task->status, $allowedStatus)) {
                return errorResponse(__('notification.songCannotBeRevise'));
            }

            $newPayload = [
                'reason' => $payload['reason'],
                'project_song_list_id' => $songId,
                'entertainment_task_song_id' => $task->id,
            ];

            if ((isset($payload['images'])) && (! empty($payload['images']))) {
                foreach ($payload['images'] as $image) {
                    $name = $this->generalService->uploadImageandCompress(
                        path: "projects/{$projectId}/song/{$songId}/revise",
                        compressValue: 0,
                        image: $image
                    );

                    if ($name) {
                        $newPayload['images'][] = $name;
                    }
                }
            }

            // edit status of task
            $this->entertainmentTaskSongRepo->update([
                'status' => TaskSongStatus::Revise->value,
            ], $task->id);

            $this->entertainmentTaskSongRevise->store($newPayload);

            // write log
            StoreLogAction::run(
                type: TaskSongLogType::RevisedByPM->value,
                payload: [
                    'project_song_list_id' => $songId,
                    'project_id' => $projectId,
                    'employee_id' => null,
                ],
                params: [
                    'pm' => $user->load('employee')->employee->nickname,
                    'user' => $task->employee->nickname,
                ]
            );

            // notification
            SongReviseJob::dispatch($payload, $projectUid, $songUid, $user->id)->afterCommit();

            // current data
            $currentData = $this->detailCacheAction->handle($projectUid);

            DB::commit();

            return generalResponse(
                message: __('notification.successReviseSong'),
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to renew project detail cache
     */
    public function renewCache(string $projectUid)
    {
        // get current data
        $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
        $currentData = $this->generalService->getCache('detailProject'.$projectId);

        if (! $currentData) {
            $this->show($projectUid);
            $currentData = $this->generalService->getCache('detailProject'.$projectId);
        }

        $currentData = $this->formatTasksPermission($currentData, $projectId);

        return $currentData;
    }

    /**
     * Check all tasks status before user complete the project
     */
    public function precheck(string $projectUid): array
    {
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
            // get all project task with status is not the same with completed
            $tasksProjects = $this->getUnfinishedTasks($projectId);
            $tasks = $tasksProjects['production_tasks'];
            $songs = $tasksProjects['songs'];

            $needToCompleteTasks = $tasks->count() > 0 || $songs->count() > 0 ? true : false;

            return generalResponse(
                message: 'Success',
                data: [
                    'needToCompleteTask' => $needToCompleteTasks,
                    'tasks' => $tasks,
                    'songs' => $songs,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function getUnfinishedTasks(int $projectId): array
    {
        $notAllowed = [
            TaskStatus::Completed->value,
            TaskStatus::WaitingDistribute->value,
        ];

        $tasks = $this->taskRepo->list(
            select: 'id,name,status,uid',
            where: 'status NOT IN ('.implode(',', $notAllowed).") AND status IS NOT NULL AND project_id = {$projectId}",
            relation: [
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,nickname',
            ]
        );

        $tasks = collect((object) $tasks)->map(function ($task) {
            $task->makeHidden(['proof_of_works_detail', 'revise_detail', 'performance_recap', 'proofOfWorks', 'revises']);
            $pic = '';
            if ($task->pics->count() > 0) {
                $pic = collect((object) $task->pics)->pluck('employee.nickname')->toArray();
                $pic = implode(',', $pic);
            }
            $task['pic'] = $pic;

            $task->makeHidden(['pics']);

            return $task;
        });

        // get all unfinished songs
        $songs = $this->projectSongListRepo->list(
            select: 'id,uid,project_id,name',
            where: "project_id = {$projectId}",
            relation: [
                'task' => function ($query) {
                    $query->selectRaw('id,project_song_list_id,employee_id,status')
                        ->with(['employee:id,nickname'])
                        ->whereNotIn('status', [TaskSongStatus::Completed->value]);
                },
            ]
        );

        $songs = collect((object) $songs)->filter(function ($filterSong) {
            return $filterSong->task;
        })->values();

        return [
            'production_tasks' => $tasks,
            'songs' => $songs,
        ];
    }

    /**
     * Complete all unfinished tasks
     */
    public function completeUnfinishedTask(string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);
            $unfinishedTasks = $this->getUnfinishedTasks($projectId);

            if (count($unfinishedTasks['production_tasks']) > 7) {
                return errorResponse(message: __('notification.toManyUnfinishedTask'));
            }

            foreach ($unfinishedTasks['production_tasks'] as $task) {
                // mark as complete
                if ($task->status != TaskStatus::CheckByPm->value) {
                    $this->mainProofOfWork(
                        data: [
                            'nas_link' => 'http://proofbypm',
                            'task_id' => $task->uid,
                            'board_id' => null,
                            'manual_approve' => true,
                            'preview' => 'preview',
                        ],
                        projectUid: $projectUid,
                        taskUid: $task->uid,
                        useDefaultImage: true
                    );
                }

                // complete the task
                $this->mainMarkAsCompleted(projectUid: $projectUid, taskUid: $task->uid, sendNotification: false);
            }

            // complete song tasks
            foreach ($unfinishedTasks['songs'] as $song) {
                $this->mainSongApproveWork(
                    projectUid: $projectUid,
                    songUid: $song->uid,
                    user: $user,
                    sendNotification: false,
                    forceToComplete: true,
                    forceRecordPoint: true
                );
            }

            // refresh all cache
            $currentData = $this->detailCacheAction->run(projectUid: $projectUid, forceUpdateAll: true);

            DB::commit();

            return generalResponse(
                message: 'Success',
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function filterTasks(array $payload, string $projectUid): array
    {
        try {
            $myTask = $payload['my_task']; // search only user task
            $search = $payload['search']; // this is used to search by task name and pic name

            $boards = FormatBoards::run($projectUid, $search, (bool) $myTask);

            return generalResponse(
                message: 'Success',
                data: [
                    'boards' => $boards,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Here we'll remove pic from selected song
     * Step to produce:
     * 1. Delete pic from entertainment_task_song table
     */
    public function removePicSong(string $projectUid, string $songUid): array
    {
        DB::beginTransaction();
        try {
            $songId = $this->generalService->getIdFromUid($songUid, new ProjectSongList);
            $projectId = $this->generalService->getIdFromUid($projectUid, new Project);

            $currentTask = $this->entertainmentTaskSongRepo->show(
                uid: 'uid',
                select: 'id,project_song_list_id,employee_id,project_id',
                where: "project_song_list_id = {$songId}",
                relation: [
                    'employee:id,nickname,telegram_chat_id',
                    'project:id,name',
                    'song:id,name',
                ]
            );

            $this->entertainmentTaskSongRepo->delete(id: 0, where: "project_song_list_id = {$songId} and project_id = {$projectId}");

            // send notification to the pic
            RemovePicFromSong::dispatch($currentTask)->afterCommit();

            // refresh all cache
            $currentData = $this->detailCacheAction->run(projectUid: $projectUid, forceUpdateAll: true);

            DB::commit();

            return generalResponse(
                message: 'Success remove PIC',
                data: [
                    'full_detail' => $currentData,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Check the given date is categorize as 'high season' or not
     *
     * @param  array  $payload  with this following structure:
     *                          - string $project_date
     */
    public function checkHighSeason(array $payload): array
    {
        try {
            $projectDate = $payload['project_date'];

            $firstWeek = Carbon::parse($projectDate)->startOfWeek()->format('Y-m-d');
            $endWeek = Carbon::parse($projectDate)->endOfWeek()->format('Y-m-d');

            $projects = $this->repo->list(
                select: 'id,project_date,name',
                where: "project_date BETWEEN '{$firstWeek}' AND '{$endWeek}'"
            );

            return generalResponse(
                message: 'Success',
                data: [
                    'is_high_season' => $projects->count() > 3 ? true : false,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Calculate project price based on given data
     *
     * @param  array  $payload  with the following structure
     *                          - bool $high_season
     *                          - string $project_date
     *                          - string $equipment
     *                          - array $led_detail {            with the following structure
     *                          - string $name
     *                          - string $textDetail
     *                          - string $total
     *                          - string $totalRaw
     *                          - array $led {              with the following structure
     *                          - string $height
     *                          - string $width
     *                          }
     *                          }
     */
    public function calculateProjectPrice(array $payload): array
    {
        try {

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get all formula and price setting
     */
    public function getCalculationFormula(): array
    {
        try {
            $keys = [
                'discount_type',
                'discount',
                'markup_type',
                'markup',
                'high_season_type',
                'high_season',
                'equipment_type',
                'equipment',
            ];
            $data = $this->settingRepo->list(
                select: '`key`, `value`',
                where: "`key` IN ('".implode("','", $keys)."')"
            );

            $highSeasonSetting = $data->filter(function ($filter) {
                return $filter->key == 'high_season' || $filter->key == 'high_season_type';
            })->values();

            // validate
            foreach ($data as $setting) {
                if (empty($setting['value']) || ! $setting) {
                    return errorResponse('Price formula is not found');
                }
            }

            // build formula string
            $mainLedFormula = '{total_main_led}*{area_price}';
            $prefunctionFormula = '{total_main_prefunc}*{area_price}';

            $highSeasonFormula = '({total_main_price}+{total_prefunc_price})*';

            return generalResponse(
                message: 'Success',
                data: []
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Generate the available quotation number for the upcoming project deals
     */
    public function getQuotationNumber(): array
    {
        $quotation = GenerateQuotationNumber::run(projectQuotationRepo: $this->projectQuotationRepo);

        return generalResponse(
            message: 'Success',
            data: [
                'number' => $quotation,
            ]
        );
    }

    /**
     * Create project deals and generate quotation
     */
    public function storeProjectDeals(array $payload): array
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {

            $project = $this->projectDealRepo->store(
                collect($payload)
                    ->except(['marketing_id', 'quotation'])
                    ->toArray()
            );

            // insert project details marketing
            $project->marketings()->createMany(
                collect($payload['marketing_id'])->map(function ($item) {
                    return [
                        'employee_id' => $this->generalService->getIdFromUid($item, new \Modules\Hrd\Models\Employee),
                    ];
                })->toArray()
            );

            // insert quotations
            $payload['quotation']['project_deal_id'] = $project->id;
            $url = CreateQuotation::run($payload, $this->projectQuotationRepo);

            // handle when project deal have a final status
            if ($payload['status'] == ProjectDealStatus::Final->value) {
                // here we edit the project deal data, we update identifier number
                // $this->projectDealRepo->update(data: [
                //     'identifier_number' => $this->generalService->setProjectIdentifier()
                // ], id: $project->id);

                $realProject = \App\Actions\CopyDealToProject::run($project, $this->generalService, $payload['is_have_interactive_element']);

                // create interactive project if needed
                if ($payload['is_have_interactive_element']) {
                    CreateInteractiveProject::run($realProject->id);
                }

                // gerenrate invoice master
                \App\Actions\Finance\CreateMasterInvoice::run(projectDealId: $project->id);

                ProjectHasBeenFinal::dispatch($project->id)->afterCommit();
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successCreateProjectDeals'),
                data: [
                    'url' => $url,
                ]
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Create project deals and generate quotation
     */
    public function updateProjectDeals(array $payload, string $projectDealUid): array
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $projectDealUid = \Illuminate\Support\Facades\Crypt::decryptString($projectDealUid);

            $project = $this->projectDealRepo->show(
                uid: (string) $projectDealUid,
                select: 'id',
                relation: [
                    'marketings:id,project_deal_id,employee_id',
                    'latestQuotation',
                ]
            );

            $this->projectDealRepo->update(
                data: collect($payload)
                    ->except(['marketing_id', 'quotation', 'status', 'request_type'])
                    ->toArray(),
                id: $projectDealUid
            );

            // delete all first
            $project->marketings()->delete();

            // insert project details marketing
            $project->marketings()->createMany(
                collect($payload['marketing_id'])->map(function ($item) {
                    return [
                        'employee_id' => $this->generalService->getIdFromUid($item, new \Modules\Hrd\Models\Employee),
                    ];
                })->toArray()
            );

            // update latest quotation
            $this->projectQuotationRepo->update(
                data: collect($payload['quotation'])
                    ->except(['status', 'items', 'quotation_id'])
                    ->toArray(),
                id: $project->latestQuotation->id
            );

            // update quotation items
            $latestQuotation = $project->latestQuotation;
            $latestQuotation->items()->delete();

            foreach ($payload['quotation']['items'] as $item) {
                $latestQuotation->items()->create([
                    'item_id' => $item,
                ]);
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successUpdateProjectDeals'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Function to initialize project count
     */
    public function initProjectCount(): array
    {
        $projectDealId = ! empty(request('projectDealUid')) ? \Illuminate\Support\Facades\Crypt::decryptString(request('projectDealUid')) : null;

        // if projectDealId exist, get the identity number instead of generate new one
        if ($projectDealId) {
            $count = $this->projectDealRepo->show(uid: $projectDealId, select: 'identifier_number')->identifier_number;
        } else {
            $count = $this->generalService->generateDealIdentifierNumber();
        }

        return generalResponse(
            message: 'Success',
            data: [
                'count' => $count,
            ]
        );
    }
}
