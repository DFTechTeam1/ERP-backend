<?php

namespace Modules\Production\Services;

use App\Enums\ErrorCode\Code;
use Illuminate\Support\Facades\DB;
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

    /**
     * Construction Data
     */
    public function __construct()
    {
        $this->repo = new ProjectRepository;

        $this->referenceRepo = new ProjectReferenceRepository;

        $this->employeeRepo = new EmployeeRepository;

        $this->taskRepo = new ProjectTaskRepository;

        $this->boardRepo = new ProjectBoardRepository;

        $this->taskPicRepo = new ProjectTaskPicRepository;

        $this->projectEquipmentRepo = new ProjectEquipmentRepository;

        $this->projectTaskAttachmentRepo = new ProjectTaskAttachmentRepository;

        $this->projectPicRepository = new ProjectPersonInChargeRepository;

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
     * @param array $ids
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

            $projectManagerRole = getSettingByKey('project_manager_role');
            $isPMRole = $roles[0]->id == $projectManagerRole;

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
                    if ($isSuperAdmin) {
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
                    }
                }
            }

            // get project that only related to authorized user
            if ($isProductionRole) {
                $employeeId = $this->employeeRepo->show('dummy', 'id', [], 'id = ' . auth()->user()->employee_id);

                if ($employeeId) {
                    $taskIds = $this->taskPicLogRepo->list('id,project_task_id', 'employee_id = ' . $employeeId->id);
                    $taskIds = collect($taskIds)->pluck('project_task_id')->unique()->values()->toArray();

                    $newWhereHas = [
                        [
                            'relation' => 'tasks',
                            'query' => 'id IN (' . implode(',', $taskIds) . ')'
                        ]
                    ];

                    $whereHas = array_merge($whereHas, $newWhereHas);
                }
            }

            if ($isPMRole) {
                $whereHas[] = [
                    'relation' => 'personInCharges',
                    'query' => 'pic_id = ' . auth()->user()->employee_id,
                ];
            }

            logging('where has condition', [auth()->user()]);

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page,
                $whereHas
            );
            $totalData = $this->repo->list('id', $where)->count();

            $eventTypes = \App\Enums\Production\EventType::cases();
            $classes = \App\Enums\Production\Classification::cases();
            $statusses = \App\Enums\Production\ProjectStatus::cases();

            $paginated = collect($paginated)->map(function ($item) use ($eventTypes, $classes, $statusses) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name . '(' . $pic->employee->employee_id . ')',
                    ];
                })->pluck('name')->values()->toArray();

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
                foreach ($statusses as $statusData) {
                    if ($statusData->value == $item->status) {
                        $status = $statusData->label();
                        $statusColor = $statusData->color();
                    }
                }

                $eventClass = '-';
                $eventClassColor = null;
                foreach ($classes as $class) {
                    if ($class->value == $item->classification) {
                        $eventClass = $class->label();
                        $eventClassColor = $class->color();
                    }
                }

                return [
                    'uid' => $item->uid,
                    'marketing' => $marketing,
                    'pic' => count($pics) > 0  ? implode(', ', $pics) : '-',
                    'name' => $item->name,
                    'project_date' => date('d F Y', strtotime($item->project_date)),
                    'venue' => $item->venue,
                    'event_type' => $eventType,
                    'led_area' => $item->led_area,
                    'event_class' => $eventClass,
                    'status' => $status,
                    'status_color' => $statusColor,
                    'status_raw' => $item->status,
                    'event_class_color' => $eventClassColor,
                ];
            });

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
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

        $data = collect($data)->map(function ($project) {
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
    protected function formatingReferenceFiles(object $references)
    {
        return collect($references)->map(function ($reference) {
            return [
                'id' => $reference->id,
                'media_path' => $reference->media_path_text,
                'name' => $reference->name,
                'type' => $reference->type,
            ];
        })->toArray();
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
        foreach ($project->personInCharges as $key => $pic) {
            $pics[] = $pic->employee->name . '(' . $pic->employee->employee_id . ')';
            $picIds[] = $pic->pic_id;
            $picUids[] = $pic->employee->uid;
        }

        // get another teams from approved transfer team
        $user = auth()->user();
        $roles = $user->roles;
        $roleId = $roles[0]->id;
        $superUserRole = getSettingByKey('super_user_role');
        $transferCondition = 'status = ' . \App\Enums\Production\TransferTeamStatus::Approved->value;
        if ($roleId != $superUserRole) {
            $transferCondition .= ' and requested_by = ' . $user->employee_id; 
        }
        $transfers = $this->transferTeamRepo->list('id,employee_id', $transferCondition, ['employee:id,name,uid,email,employee_id']);

        $transfers = collect($transfers)->map(function ($transfer) {
            return [
                'id' => $transfer->employee->id,
                'uid' => $transfer->employee->uid,
                'email' => $transfer->employee->email,
                'name' => $transfer->employee->name,
                'last_update' => '-',
                'current_task' => '-',
                'image' => asset('images/user.png'),
            ];
        })->toArray();

        // get special position that will be append on each project manager team members
        $specialPosition = getSettingByKey('special_production_position');
        $specialEmployee = [];
        if ($specialPosition) {
            $specialPosition = getIdFromUid($specialPosition, new \Modules\Company\Models\Position());

            $specialEmployee = $this->employeeRepo->list('id,uid,name,email', 'position_id = ' . $specialPosition)->toArray();
        }

        $teams = $this->employeeRepo->list(
            'id,uid,name,email',
            '',
            [],
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
            ],
            [
                'key' => 'boss_id',
                'value' => $picIds,
            ]
        );
        $teams = collect($teams)->map(function ($team) {
            $team['last_update'] = '-';
            $team['current_task'] = '-';
            $team['image'] = asset('images/user.png');

            return $team;
        })->toArray();

        $teams = collect($teams)->merge($transfers)->toArray();

        $teams = collect($teams)->merge($specialEmployee)->toArray();

        return [
            'pics' => $pics,
            'teams' => $teams,
            'picUids' => $picUids,
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

                // override is_active where task status is ON PROGRESS
                if ($task->status == \App\Enums\Production\TaskStatus::OnProgress->value) {
                    $isActive = true;
                }

                $outputTask[$keyTask]['stop_action'] = $task->project->status == \App\Enums\Production\ProjectStatus::Draft->value ? true : false;

                $outputTask[$keyTask]['need_approval_pm'] = $isProjectPic && $task->status == \App\Enums\Production\TaskStatus::CheckByPm->value;

                $outputTask[$keyTask]['time_tracker'] = $this->formatTimeTracker($task->times->toArray());

                $outputTask[$keyTask]['is_project_pic'] = $isProjectPic;

                $outputTask[$keyTask]['is_director'] = $isDirector;

                if ($superUserRole || $isProjectPic || $isDirector) {
                    $isActive = true;
                }

                // check the ownership of task
                $picIds = collect($task->pics)->pluck('employee_id')->toArray();
                $haveTaskAccess = true;
                if (!$superUserRole && !$isProjectPic && !$isDirector) {
                    if (!in_array($employeeId, $picIds)) {
                        $haveTaskAccess = false;
                    }
                }

                if (
                    in_array($employeeId, $picIds) && 
                    $task->project->status == \App\Enums\Production\ProjectStatus::OnGoing->value &&
                    $task->status == \App\Enums\Production\TaskStatus::OnProgress->value
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
        $project = $this->repo->show($projectUid, 'id,uid,event_type,classification,name,project_date');

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

        $eventClass = '-';
        $eventClassColor = null;
        foreach ($classes as $class) {
            if ($class->value == $project->classification) {
                $eventClass = $class->label();
                $eventClassColor = $class->color();
            }
        }

        return [
            'pics' => $pics,
            'teams' => $teams,
            'name' => $project->name,
            'project_date' => date('d F Y', strtotime($project->project_date)),
            'event_type' => $eventType,
            'event_type_raw' => $project->event_type,
            'event_class_raw' => $project->classification,
            'event_class' => $eventClass,
            'event_class_color' => $eventClassColor,
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

        $equipments = collect($equipments)->map(function ($item) {
            $item['is_cancel'] = $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ? true : false;

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
                    'personInCharges.employee:id,name,employee_id,uid',
                    'references:id,project_id,media_path,name,type',
                    'equipments.inventory:id,name',
                    'equipments.inventory.image',
                    'marketings:id,marketing_id,project_id',
                    'marketings.marketing:id,name',
                    'country:id,name',
                    'state:id,name',
                    'city:id,name'
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
                $allowedUploadShowreels = false;
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
                if ($currentTaskStatusses == $completedStatus) {
                    $allowedUploadShowreels = true;
                }

                $output = [
                    'allowed_upload_showreels' => $allowedUploadShowreels,
                    'uid' => $data->uid,
                    'name' => $data->name,
                    'country_id' => $data->country_id,
                    'state_id' => $data->state_id,
                    'city_id' => $data->city_id,
                    'event_type' => $eventType,
                    'event_type_raw' => $data->event_type,
                    'event_class_raw' => $data->classification,
                    'event_class' => $eventClass,
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
                    'references' => $this->formatingReferenceFiles($data->references),
                    'boards' => $boardsData,
                    'teams' => $teams,
                    'task_type' => $data->task_type,
                    'task_type_text' => $data->task_type_text,
                    'task_type_color' => $data->task_type_color,
                    'progress' => $progress,
                    'equipments' => $equipments,
                    'showreels' => $data->showreels_path,
                    'person_in_charges' => $data->personInCharges
                ];

                storeCache('detailProject' . $data->id, $output);
            }

            $output = $this->formatTasksPermission($output, $projectId);

            $serviceEncrypt = new \App\Services\EncryptionService();
            $encrypts = $serviceEncrypt->encrypt(json_encode($output), env('SALT_KEY'));

            $outputData = [
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
            in_array($employeeId, $picIds) && 
            $task['project']->status == \App\Enums\Production\ProjectStatus::OnGoing->value &&
            $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value
        ) {
            $task['action_to_complete_task'] = true;
        } else {
            $task['action_to_complete_task'] = false;
        }

        if ($superUserRole || $isProjectPic || $isDirector) {
            $isActive = true;
            $haveTaskAccess = true;
        }

        $task['is_active'] = $isActive;

        $task['has_task_access'] = $haveTaskAccess;

        return $task;
    }

    protected function formatTasksPermission($project, int $projectId)
    {
        $output = [];

        $user = auth()->user();
        $employeeId = $user->employee_id;
        $superUserRole = isSuperUserRole();
        $isDirector = isDirector();

        // get teams
        $projectId = getIdFromUid($project['uid'], new \Modules\Production\Models\Project());
        $personInCharges = $this->projectPicRepository->list('*', 'project_id = ' . $projectId);
        $project['personInCharges'] = $personInCharges;
        $projectTeams = $this->getProjectTeams((object) $project);
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
                $project['status_raw'] == \App\Enums\Production\ProjectStatus::Draft->value
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
                if (!$superUserRole && !$isProjectPic && !$isDirector) {
                    if (!in_array($employeeId, $picIds)) { // where logged user is not a in task pic except the project manager
                        $haveTaskAccess = false;
                    }
                }

                if (
                    in_array($employeeId, $picIds) &&
                    $project['status_raw'] == \App\Enums\Production\ProjectStatus::OnGoing->value &&
                    $task['status'] == \App\Enums\Production\TaskStatus::OnProgress->value
                ) {
                    $outputTask[$keyTask]['action_to_complete_task'] = true;
                } else {
                    $outputTask[$keyTask]['action_to_complete_task'] = false;
                }

                $outputTask[$keyTask]['picIds'] = $picIds;
                $outputTask[$keyTask]['has_task_access'] = $haveTaskAccess;

                if ($superUserRole || $isProjectPic || $isDirector) {
                    $outputTask[$keyTask]['is_active'] = true;
                }
            }

            $output[$keyBoard]['tasks'] = $outputTask;
        }

        $project['boards'] = $output;

        // showreels
        $showreels = $this->repo->show($project['uid'], 'id,showreels');
        $project['showreels'] = $showreels->showreels_path;

        $allowedUploadShowreels = false;
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
        if ($currentTaskStatusses == $completedStatus) {
            $allowedUploadShowreels = true;
        }
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
            $data['led_detail'] = json_encode($data['led']);

            if (isset($data['seeder'])) { // if came from seeder
                $data['status'] = \App\Enums\Production\ProjectStatus::OnGoing->value;
            } else {
                $userRole = auth()->user()->getRoleNames()[0];
                $data['status'] = strtolower($userRole) != 'project manager' ? $data['status'] : \App\Enums\Production\ProjectStatus::Draft->value;
            }

            $city = \Modules\Company\Models\City::select('name')->find($data['city_id']);

            $data['city_name'] = $city->name;

            $project = $this->repo->store(collect($data)->except(['led', 'marketing_id', 'pic', 'seeder'])->toArray());

            $marketings = collect($data['marketing_id'])->map(function ($marketing) {
                return [
                    'marketing_id' => getIdFromUid($marketing, new \Modules\Hrd\Models\Employee()),
                ];
            })->toArray();
            $project->marketings()->createMany($marketings);

            $pics = collect($data['pic'])->map(function ($item) {
                return [
                    'pic_id' => getidFromUid($item, new \Modules\Hrd\Models\Employee()),
                ];
            })->toArray();
            $project->personInCharges()->createMany($pics);

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

        $items = $this->customItemRepo->show('dummy', '*', ['items.inventory:id,name,uid'], 'default_request_item = 1');

        if ($items->items->count() > 0) {
            $payload = [];
            foreach ($items->items as $item) {
                $payload[] = [
                    'inventory_id' => $item->inventory->uid,
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
            $data['city_name'] = $city->name;

            $this->repo->update(collect($data)->except(['pic'])->toArray(), $id);
            $projectId = getIdFromUid($id, new \Modules\Production\Models\Project());

            foreach ($data['pic'] as $pic) {
                $employeeId = getIdFromUid($pic, new \Modules\Hrd\Models\Employee());

                $this->projectPicRepository->delete(0, 'project_id = ' . $projectId);

                $this->projectPicRepository->store([
                    'pic_id' => $employeeId,
                    'project_id' => $projectId,
                ]);
            }

            $project = $this->repo->show($id, 'id,client_portal,collaboration,event_type,note,status,venue,country_id,state_id,city_id', [
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id,uid',
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
            $currentData['note'] = $project->note ?? '-';
            $currentData['client_portal'] = $project->client_portal;
            $currentData['pic'] = implode(', ', $pics);
            $currentData['pic_ids'] = $picIds;
            $currentData['teams'] = $teams;

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
            $currentData['event_class'] = $format['event_class'];
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
        $fileImageType = ['jpg', 'jpeg', 'png', 'webp'];
        $project = $this->repo->show($id);
        try {
            $output = [];
            foreach ($data['files'] as $file) {
                $type = $file['path']->getClientOriginalExtension();

                if (gettype(array_search($type, $fileImageType)) != 'boolean') {
                    $fileData = uploadImageandCompress(
                        'projects/references/' . $project->id,
                        10,
                        $file['path']
                    );
                } else {
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

            $project->references()->createMany($output);

            return generalResponse(
                __("global.successCreateReferences"),
                false,
                $this->formatingReferenceFiles($project->references),
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
                \Modules\Production\Jobs\AssignTaskJob::dispatch($notifiedNewTask, $taskId)->afterCommit();
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
            logging('error assign member', [
                'file' => $th->getFile(),
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ]);
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
            $board = $this->boardRepo->show($boardId, 'project_id,name', ['project:id,uid']);
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
            $references = $this->formatingReferenceFiles($project->references);

            return generalResponse(
                __('global.successDeleteReference'),
                false,
                $references,
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
            // $currentTaskPics = collect($task->pics)->map(function ($item) {
            //     return [
            //         'uid' => $item->employee->uid,
            //         'name' => $item->employee->name,
            //         'email' => $item->employee->email,
            //         'image' => $item->employee->image ? asset('storage/employees/' . $item->employee->image) : asset('images/user.png'),
            //     ];
            // })->all();
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
            });

            $selectedKeys = [];
            foreach ($currentTaskPics as $c) {
                $selectedKeys[] = $c['employee']['uid'];
            }

            $memberKeys = array_column($teams, 'uid');

            $diff = array_diff($memberKeys, $selectedKeys);

            $availableKeys = [];
            $available = [];
            if (count($diff) > 0) {
                $availableKeys = array_values($diff);
                foreach ($teams as $key => $member) {
                    if (in_array($member['uid'], $availableKeys)) {
                        array_push($available, $member);
                    }
                }
            }

            $out = [
                'selected' => $outSelected,
                'available' => $available,
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
            // handle duplicate items
            $groupBy = collect($data['items'])->groupBy('inventory_id')->all();
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
        ]);

        $data = collect($data)->map(function ($item) {
            return [
                'uid' => $item->uid,
                'inventory_name' => $item->inventory->name,
                'inventory_image' => $item->inventory->display_image,
                'inventory_stock' => $item->inventory->stock,
                'qty' => $item->qty,
                'status' => $item->status_text,
                'status_color' => $item->status_color,
                'is_checked_pic' => $item->is_checked_pic,
                'is_cancel' => $item->status == \App\Enums\Production\RequestEquipmentStatus::Cancel->value ? true : false,
            ];
        })->toArray();

        return generalResponse(
            'Success',
            false,
            $data,
        );
    }

    public function updateEquipment(array $data, string $projectUid)
    {
        try {
            $picPermission = auth()->user()->can('accept_request_equipment');

            foreach ($data['items'] as $item) {
                $payload = [
                    'status' => $item['status'],
                    'is_checked_pic' => false,
                ];

                if ($picPermission) {
                    $payload['is_checked_pic'] = true;
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

            \Modules\Production\Jobs\PostEquipmentUpdateJob::dispatch($projectUid, $data, $userCanAcceptRequest);

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

                // move task
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
        $boardData = collect($boards)->filter(function ($filter) use ($data) {
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
        $marketings = $this->employeeRepo->list('id,uid,name', "position_id in ({$combinePositionIds}) and status != " . \App\Enums\Employee\Status::Inactive->value);

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
    public function approveTask(string $projectUid, string $taskUid)
    {
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());
            $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
            $employeeId = auth()->user()->employee_id;

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
        $tmpFile = null;
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());
        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());

        DB::beginTransaction();
        try {
            if (isset($data['file'])) {
                $tmpFile = uploadImageandCompress(
                    "projects/{$projectId}/task/{$taskId}/revise",
                    10,
                    $data['file']
                );
            }

            $this->taskReviseHistoryRepo->store([
                'project_task_id' => $taskId,
                'project_id' => $projectId,
                'reason' => $data['reason'],
                'file' => $tmpFile,
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
                'project_board_id' => $currentTaskData->current_board,
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
                'success',
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

            $currentPic = $this->taskPicRepo->list('employee_id', 'project_task_id = ' . $taskId, ['employee:id,uid']);

            // change worktime status of Project Manager
            foreach ($currentPic as $pic) {
                $this->setTaskWorkingTime($taskId, $pic->employee_id, \App\Enums\Production\WorkType::Finish->value);
            }

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

    public function getProjectStatusses()
    {
        $data = \App\Enums\Production\ProjectStatus::cases();

        $out = [];
        foreach ($data as $status) {
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

    public function changeStatus(array $data, string $projectUid)
    {
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

            return generalResponse(
                __('global.statusIsChanged'),
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

            $taskDateCondition = "project_date >= '" . $startDate . "' and project_date <= '" . $projectDate . "'";

            $data = $this->employeeRepo->list('id,uid,name,email', 'boss_id = ' . $bossId);

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

            $tmpFile = uploadFile(
                'projects/' . $projectId . '/showreels',
                $data['file']
            );

            $this->repo->update([
                'showreels' => $tmpFile,
            ], $projectUid);

            $currentData = getCache('detailProject' . $projectId);

            $currentData = $this->formatTasksPermission($currentData, $projectId);

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

        $data = collect($histories)->map(function ($item) {
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
                    'point' => $point['point'],
                    'additional_point' => $point['additional_point'],
                    'total_point' => $point['additional_point'] + $point['point'],
                    'total_task' => $point['total_task'],
                    'created_by' => auth()->user()->employee_id ?? 0,
                ]);
            }

            $this->repo->update([
                'status' => \App\Enums\Production\ProjectStatus::Completed->value
            ], $projectUid);

            // update project status cache
            $project = $this->repo->show($projectUid, 'id,status');

            $currentData = getCache('detailProject' . $projectId);
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
}
