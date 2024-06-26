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

class ProjectService {
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
            logging('path', [$path]);

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
    ): array
    {
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
                $employeeId = $this->employeeRepo->show('dummy', 'id', [], 'user_id = ' . auth()->id());

                if ($employeeId) {
                    $taskIds = $this->taskPicRepo->list('id,project_task_id', 'employee_id = ' . $employeeId->id);
                    $taskIds = collect($taskIds)->pluck('project_task_id')->toArray();

                    $newWhereHas = [
                        [
                            'relation' => 'tasks',
                            'query' => 'id IN ('. implode(',', $taskIds) .')'
                        ]
                    ];

                    $whereHas = array_merge($whereHas, $newWhereHas);
                }
            }

            logging('whereHas', $whereHas);

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

            $paginated = collect($paginated)->map(function ($item) use ($eventTypes, $classes) {
                $pics = collect($item->personInCharges)->map(function ($pic) {
                    return [
                        'name' => $pic->employee->name . '(' . $pic->employee->employee_id . ')',
                    ];
                })->pluck('name')->values()->toArray();

                $marketing = $item->marketing ? $item->marketing->name : '-';

                $eventType = '-';
                foreach ($eventTypes as $et) {
                    if ($et->value == $item->event_type) {
                        $eventType = $et->label();
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
        $pics = [];
        $teams = [];
        $picIds = [];
        $picUids = [];
        foreach ($project->personInCharges as $key => $pic) {
            $pics[] = $pic->employee->name . '('. $pic->employee->employee_id .')';   
            $picIds[] = $pic->pic_id;
            $picUids[] = $pic->employee->uid;
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

        return [
            'pics' => $pics,
            'teams' => $teams,
            'picUids' => $picUids,
        ];
    }

    protected function defaultTaskRelation()
    {
        return [
            'project:id,uid',
            'pics:id,project_task_id,employee_id',
            'pics.employee:id,name,email,uid',
            'medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
            'taskLink:id,project_id,project_task_id,media,display_name,related_task_id,type',
            'proofOfWorks',
            'logs',
        ];
    }

    protected function formattedDetailTask(string $taskUid)
    {
        $task = $this->taskRepo->show($taskUid, '*', $this->defaultTaskRelation());

        return $task;
    }

    protected function formattedBoards(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $data = $this->boardRepo->list('id,project_id,name,sort,based_board_id', 'project_id = ' . $projectId, [
            'tasks',
            'tasks.proofOfWorks',
            'tasks.logs',
            'tasks.pics:id,project_task_id,employee_id',
            'tasks.pics.employee:id,name,email,uid',
            'tasks.medias:id,project_id,project_task_id,media,display_name,related_task_id,type,updated_at',
            'tasks.taskLink:id,project_id,project_task_id,media,display_name,related_task_id,type',
        ]);

        $boardAsBacklog = getSettingByKey('board_as_backlog');
        $boardStartCheckByPm = getSettingByKey('board_to_check_by_pm');
        $boardStartCheckByClient = getSettingByKey('board_to_check_by_client');
        $boardStartCalculated = getSettingByKey('board_start_calculated');
        $boardCompleted = getSettingByKey('board_completed');

        $data = collect($data)->map(function ($item) use ($boardAsBacklog, $boardStartCheckByPm, $boardStartCalculated, $boardCompleted, $boardStartCheckByClient) {
            $item['board_as_backlog'] = $boardAsBacklog == $item->based_board_id ? true : false;
            $item['board_to_check_by_pm'] = $boardStartCheckByPm == $item->based_board_id ? true : false;
            $item['board_to_check_by_client'] = $boardStartCheckByClient == $item->based_board_id ? true : false;
            $item['board_start_calculated'] = $boardStartCalculated == $item->based_board_id ? true : false;
            $item['board_completed'] = $boardCompleted == $item->based_board_id ? true : false;

            return $item;
        });

        return $data;
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

    protected function formattedProjectProgress(object $tasks)
    {
        $grouping = collect($tasks)->groupBy('task_type')->all();

        $default = [];
        $types = \App\Enums\Production\TaskType::cases();
        foreach ($types as $type) {
            $default[] = [
                'text' => $type->label(),
                'id' => $type->value,
                'total' => 0,
                'completed' => 0,
                'percentage' => 0,
            ];
        }
        
        $out = [];
        foreach ($default as $key => $def) {
            $out[$key] = $def;

            foreach ($grouping as $taskType => $taskGroup) {
                if ($taskType == $def['id']) {
                    $completed = collect($taskGroup)->filter(function ($filter) {
                        return $filter->board->name == 'On Progress';
                    })->count();
        
                    $total = count($taskGroup);
                    $percentage = ceil($completed / $total * 100);
        
                    $out[$key]['total'] = $total;
                    $out[$key]['completed'] = $completed;
                    $out[$key]['percentage'] = $percentage;
                }
            }
        }

        logging('grouping task', $out);

        return $out;
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
            $output = getCache('detailProject' . getIdFromUid($uid, new \Modules\Production\Models\Project()));

            if (!$output) {
                $data = $this->repo->show($uid, '*', [
                    'marketing:id,name,employee_id',
                    'personInCharges:id,pic_id,project_id',
                    'personInCharges.employee:id,name,employee_id,uid',
                    'references:id,project_id,media_path,name,type',
                    'equipments.inventory:id,name',
                    'equipments.inventory.image'
                ]);

                $progress = $this->formattedProjectProgress($data->tasks);
    
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
    
                $output = [
                    'uid' => $data->uid,
                    'name' => $data->name,
                    'event_type' => $eventType,
                    'event_type_raw' => $data->event_type,
                    'event_class_raw' => $data->classification,
                    'event_class' => $eventClass,
                    'event_class_color' => $eventClassColor,
                    'project_date' => date('d F Y', strtotime($data->project_date)),
                    'venue' => $data->venue,
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
                ];
                logging('output', $output);
    
                storeCache('detailProject' . $data->id, $output);
            }

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
            $data['marketing_id'] = getIdFromUid($data['marketing_id'], new \Modules\Hrd\Models\Employee());
            
            $userRole = auth()->user()->getRoleNames()[0];
            $data['status'] = strtolower($userRole) != 'project manager' ? \App\Enums\Production\ProjectStatus::OnGoing->value : \App\Enums\Production\ProjectStatus::Draft->value;

            $project = $this->repo->store(collect($data)->except(['led'])->toArray());

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

            $project = $this->repo->show($id, 'id,client_portal,collaboration,event_type,note,status,venue', [
                'personInCharges:id,pic_id,project_id',
                'personInCharges.employee:id,name,employee_id,uid',
            ]);

            $projectTeams = $this->getProjectTeams($project);
            $teams = $projectTeams['teams'];
            $pics = $projectTeams['pics'];
            $picIds = $projectTeams['picUids'];

            $currentData = getCache('detailProject' . $project->id);
            $currentData['venue'] = $project->venue;
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
        } catch(\Throwable $th) {
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

            $task = $this->taskRepo->show($taskId, '*', $this->defaultTaskRelation());
            
            $currentData = getCache('detailProject' . $task->project_id);

            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            $currentData['boards'] = $boards;

            storeCache('detailProject' . $task->project_id, $currentData);

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
     * @param array $data
     * @param string $taskId
     * @return array
     */
    public function assignMemberToTask(array $data, string $taskUid)
    {
        DB::beginTransaction();
        try {
            $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());

            $notifiedNewTask = [];
            foreach ($data['users'] as $user) {
                $employeeId = getIdFromUid($user, new \Modules\Hrd\Models\Employee());

                $checkPic = $this->taskPicRepo->show(0, 'id', [], 'project_task_id = ' . $taskId . ' AND employee_id = ' . $employeeId);
                if (!$checkPic) {
                    $payload = [
                        'employee_id' => $employeeId,
                        'project_task_id' => $taskId,
                    ];

                    $this->taskPicRepo->store($payload);
                    $notifiedNewTask[] = $employeeId;

                    $this->loggingTask([
                        'task_id' => $taskId,
                        'employee_uid' => $user
                    ], 'assignMemberTask');
                }

            }

            foreach ($data['removed'] as $removedUser) {
                $removedEmployeeId = getIdFromUid($removedUser, new \Modules\Hrd\Models\Employee());

                $this->taskPicRepo->deleteWithCondition('employee_id = ' . $removedEmployeeId . ' AND project_task_id = ' . $taskId);

                $this->loggingTask([
                    'task_id' => $taskId,
                    'employee_uid' => $removedUser
                ], 'removeMemberTask');
            }

            logging('notifiedNewTask', $notifiedNewTask);

            $task = $this->taskRepo->show($taskUid, '*', $this->defaultTaskRelation());

            $currentData = getCache('detailProject' . $task->project->id);
            if (!$currentData) {
                $this->show($task->project->uid);
                $currentData = getCache('detailProject' . $task->project->id);
            }
            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $task->project_id, $currentData);

            \Modules\Production\Jobs\AssignTaskJob::dispatch($notifiedNewTask, $taskId)->afterCommit();

            DB::commit();

            return generalResponse(
                __('global.memberAdded'),
                false,
                [
                    'task' => $task,
                    'full_detail' => $currentData
                ]
            );
        } catch(\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
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
            $task = $this->taskRepo->store($data);
            $task = $this->taskRepo->show($task->uid);

            \Illuminate\Support\Facades\Log::debug('res store task: ', $task->toArray());

            // task log
            $this->loggingTask([
                'board_id' => $boardId, 
                'board' => $board,
                'task' => $task,
            ], 'addNewTask');

            $boards = $this->formattedBoards($board->project->uid);
            $currentData = getCache('detailProject' . $board->project->id);
            $currentData['boards'] = $boards;

            $projectTasks = $this->taskRepo->list('*', 'project_id = ' . $board->project_id, [
                'board:id,name,project_id'
            ]);
            $progress = $this->formattedProjectProgress($projectTasks);
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
    ): array
    {
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
            logging('payload update deadline', $data);
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

            $task = $this->taskRepo->show($data['task_id'], '*', $this->defaultTaskRelation());

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

    protected function uploadTaskMedia(array $data, int $taskId, int $projectId, string $projectUid, string $taskUid)
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

    public function proofOfWork(array $data, string $projectUid, string $taskUid)
    {
        DB::beginTransaction();
        $image = [];
        $selectedProjectId = null;
        $selectedTaskId = null;
        $taskId = getIdFromUid($taskUid, new \Modules\Production\Models\ProjectTask());
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

                logging('image', $image);

                $this->proofOfWorkRepo->store([
                    'project_task_id' => $taskId,
                    'project_id' => $projectId,
                    'nas_link' => $data['nas_link'],
                    'preview_image' => json_encode($image),
                    'created_by' => auth()->id(),
                ]);

                $this->taskRepo->update([
                    'project_board_id' => $data['board_id'],
                ], $taskUid);

                $task = $this->formattedDetailTask($taskUid);

                // notified project manager
                \Modules\Production\Jobs\ProofOfWorkJob::dispatch($projectId, $taskId, auth()->id())->afterCommit();

                $cache = $this->getDetailProjectCache($projectUid);
                $currentData = $cache['cache'];

                $boards = $this->formattedBoards($projectUid);
                $currentData['boards'] = $boards;

                storeCache('detailProject' . $projectId, $currentData);
            } else {
                $task = $this->formattedDetailTask($taskUid);
                $cache = $this->getDetailProjectCache($projectUid);
                $currentData = $cache['cache'];
                $projectId = $cache['projectId'];
            }

            DB::commit();
            
            return generalResponse(
                'success',
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
     * Change board of task (When user move a task)
     *
     * @param array $data
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
            // Init Worktime
            $startCalculatedBoard = getSettingByKey('board_start_calcualted');

            $taskId = getIdFromUid($data['task_id'], new \Modules\Production\Models\ProjectTask());

            $boardIds = [$data['board_id'], $data['board_source_id']];
            $boards = $this->boardRepo->list('id,name,based_board_id', "id IN (". implode(',', $boardIds) .")");
            $boardData = collect($boards)->filter(function ($filter) use ($data) {
                return $filter->id == $data['board_id'];
            })->values();

            $startWorkTime = null;
            /**
             * Only set worktime when task is have PIC
             */
            if (
                $boardData[0]->based_board_id == $startCalculatedBoard && 
                $this->taskPicRepo->list('id', 'project_task_id = ' . $taskId)->count() > 0
            ) {
                $startWorkTime = date('Y-m-d H:i:s');
            }

            $this->taskRepo->update([
                'project_board_id' => $data['board_id'],
                'start_working_at' => $startWorkTime
            ], '', "id = " . $taskId);

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
}