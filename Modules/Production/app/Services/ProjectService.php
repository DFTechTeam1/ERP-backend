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

class ProjectService {
    private $repo;

    private $referenceRepo;

    private $employeeRepo;

    private $taskRepo;

    private $boardRepo;

    private $taskPicRepo;

    private $projectEquipmentRepo;

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
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
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
        foreach ($project->personInCharges as $key => $pic) {
            $pics[] = $pic->employee->name . '('. $pic->employee->employee_id .')';   
            $picIds[] = $pic->pic_id;
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
        ];
    }

    protected function formattedBoards(string $projectUid)
    {
        $projectId = getIdFromUid($projectUid, new \Modules\Production\Models\Project());

        $data = $this->boardRepo->list('id,project_id,name,sort', 'project_id = ' . $projectId, [
            'tasks',
            'tasks.pics:id,project_task_id,employee_id',
            'tasks.pics.employee:id,name,email,uid',
        ]);

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

    /**
     * Get detail data
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $output = getCache('detailProject' . getIdFromUid($uid, new \Modules\Production\Models\Project()));

            if (!$output) {
                $data = $this->repo->show($uid, '*', [
                    'marketing:id,name,employee_id',
                    'personInCharges:id,pic_id,project_id',
                    'personInCharges.employee:id,name,employee_id',
                    'references:id,project_id,media_path,name,type',
                ]);
    
                $eventTypes = \App\Enums\Production\EventType::cases();
                $classes = \App\Enums\Production\Classification::cases();

                // get teams
                $projectTeams = $this->getProjectTeams($data);
                $teams = $projectTeams['teams'];
                $pics = $projectTeams['pics'];
    
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
                ];
    
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
        try {
            $this->repo->update($data, $id);

            $project = $this->repo->show($id, 'id,client_portal,collaboration,event_type,note,status,venue');

            $currentData = getCache('detailProject' . $project->id);
            $currentData['venue'] = $project->venue;
            $currentData['event_type'] = $project->event_type_text;
            $currentData['event_type_raw'] = $project->event_type;
            $currentData['collaboration'] = $project->collaboration;
            $currentData['status'] = $project->status_text;
            $currentData['status_raw'] = $project->status;
            $currentData['note'] = $project->note ?? '-';
            $currentData['client_portal'] = $project->client_portal;

            storeCache('detailProject' . $project->id, $currentData);

            return generalResponse(
                __('global.successUpdateBasicInformation'),
                false,
                $currentData
            );
        } catch (\Throwable $th) {
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
        try {
            $this->taskRepo->update($data, $taskId);

            $task = $this->taskRepo->show($taskId, '*', [
                'project:id,uid',
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,name,email,uid',
            ]);
            
            $currentData = getCache('detailProject' . $task->project_id);

            $boards = $this->formattedBoards($task->project->uid);
            $currentData['boards'] = $boards;

            $currentData['boards'] = $boards;

            storeCache('detailProject' . $task->project_id, $currentData);

            return generalResponse(
                __('global.descriptionAdded'),
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
                }
            }

            foreach ($data['removed'] as $removedUser) {
                $employeeId = getIdFromUid($removedUser, new \Modules\Hrd\Models\Employee());

                $this->taskPicRepo->deleteWithCondition('employee_id = ' . $employeeId . ' AND project_task_id = ' . $taskId);
            }

            logging('notifiedNewTask', $notifiedNewTask);

            $task = $this->taskRepo->show($taskUid, '*', [
                'project:id,uid',
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,name,email,uid',
            ]);

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
     * Store task on selected board
     *
     * @param array $data
     * @param integer $boardId
     * @return array
     */
    public function storeTask(array $data, int $boardId)
    {
        try {
            $board = $this->boardRepo->show($boardId, 'project_id', ['project:id,uid']);
            $data['project_id'] = $board->project_id;
            $data['project_board_id'] = $boardId;
            $task = $this->taskRepo->store($data);
            $task = $this->taskRepo->show($task->uid);

            \Illuminate\Support\Facades\Log::debug('res store task: ', $task->toArray());

            $boards = $this->formattedBoards($board->project->uid);
            $currentData = getCache('detailProject' . $board->project->id);
            $currentData['boards'] = $boards;

            storeCache('detailProject' . $board->project_id, $currentData);

            return generalResponse(
                __('global.taskCreated'),
                false,
                $currentData
            );
        } catch (\Throwable $th) {
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
        try {
            $project = $this->repo->show($projectUid);
            foreach ($data['items'] as $item) {
                $inventoryId = getIdFromUid($item['id'], new \Modules\Inventory\Models\Inventory());
                
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

            \Modules\Production\Jobs\RequestEquipmentJob::dispatch($project);
            
            return generalResponse(
                'success',
                false,
            );
        } catch (\Throwable $th) {
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

        $data = $this->projectEquipmentRepo->list('id,uid,project_id,inventory_id,qty,status', 'project_id = ' . $projectId, [
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
            ];
        })->toArray();

        return generalResponse(
            'Success',
            false,
            $data,
        );
    }

    public function updateEquipment(array $data, string $projectId)
    {
        try {
            foreach ($data['items'] as $item) {
                $requestEquipmentId = getIdFromUid($item['id'], new \Modules\Production\Models\ProjectEquipment());

                // $this->projectEquipmentRepo->update([
                //     'status' => $item['status']
                // ], $requestEquipmentId);
            }

            $userCanAcceptRequest = auth()->user()->can('request_inventory'); // if TRUE than he is INVENTARIS

            \Modules\Production\Jobs\PostEquipmentUpdateJob::dispatch($projectId, $data, $userCanAcceptRequest);

            return generalResponse(
                'success',
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
            $this->repo->bulkDelete($ids, 'uid');

            return generalResponse(
                'success',
                false,
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
}