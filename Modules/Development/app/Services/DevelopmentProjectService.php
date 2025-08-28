<?php

namespace Modules\Development\Services;

use Exception;
use Carbon\Carbon;
use App\Actions\Development\DefineTaskAction;
use App\Enums\Development\Project\ReferenceType;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Enums\ErrorCode\Code;
use App\Enums\System\BaseRole;
use App\Repository\UserRepository;
use App\Services\GeneralService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Development\app\Services\DevelopmentProjectCacheService;
use Modules\Development\Jobs\NotifyTaskAssigneeJob;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Models\DevelopmentProjectTask;
use Modules\Development\Repository\DevelopmentProjectBoardRepository;
use Modules\Development\Repository\DevelopmentProjectReferenceRepository;
use Modules\Development\Repository\DevelopmentProjectRepository;
use Modules\Development\Repository\DevelopmentProjectTaskRepository;
use Modules\Development\Repository\DevelopmentProjectTaskPicRepository;
use Modules\Development\Repository\DevelopmentProjectTaskDeadlineRepository;
use Modules\Development\Repository\DevelopmentProjectTaskAttachmentRepository;
use Modules\Development\Repository\DevelopmentProjectTaskPicHoldstateRepository;
use Modules\Development\Repository\DevelopmentProjectTaskPicWorkstateRepository;
use Modules\Development\Repository\DevelopmentProjectTaskPicHistoryRepository;
use Modules\Development\Repository\DevelopmentTaskProofRepository;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;

class DevelopmentProjectService {
    private $repo;

    private GeneralService $generalService;

    private DevelopmentProjectCacheService $cacheService;

    private EmployeeRepository $employeeRepo;

    private DevelopmentProjectTaskRepository $projectTaskRepo;

    private DevelopmentProjectBoardRepository $projectBoardRepo;

    private DevelopmentProjectReferenceRepository $projectReferenceRepo;

    private DevelopmentProjectTaskPicRepository $projectTaskPicRepo;

    private DevelopmentProjectTaskDeadlineRepository $projectTaskDeadlineRepo;

    private DevelopmentProjectTaskAttachmentRepository $projectTaskAttachmentRepo;

    private DevelopmentProjectTaskPicHoldstateRepository $projectTaskHoldStateRepo;

    private DevelopmentProjectTaskPicWorkstateRepository $projectTaskWorkStateRepo;

    private DevelopmentProjectTaskPicHistoryRepository $projectTaskPicHistoryRepo;

    private DevelopmentTaskProofRepository $taskProofRepo;
    
    private UserRepository $user;

    private const MEDIAPATH = 'development/projects/references';

    private const PROOFPATH = 'development/projects/tasks/proofs';

    private const MEDIATASKPATH = 'development/projects/tasks';

    private const REVISEPATH = 'development/projects/tasks/revises';

    /**
     * Construction Data
     */
    public function __construct(
        DevelopmentProjectRepository $repo,
        GeneralService $generalService,
        DevelopmentProjectCacheService $cacheService,
        EmployeeRepository $employeeRepo,
        DevelopmentProjectTaskRepository $projectTaskRepo,
        DevelopmentProjectBoardRepository $projectBoardRepo,
        DevelopmentProjectReferenceRepository $projectReferenceRepo,
        DevelopmentProjectTaskPicRepository $projectTaskPicRepo,
        DevelopmentProjectTaskDeadlineRepository $projectTaskDeadlineRepo,
        DevelopmentProjectTaskAttachmentRepository $projectTaskAttachmentRepo,
        DevelopmentProjectTaskPicHoldstateRepository $projectTaskHoldStateRepo,
        DevelopmentProjectTaskPicWorkstateRepository $projectTaskWorkStateRepo,
        DevelopmentTaskProofRepository $taskProofRepo,
        DevelopmentProjectTaskPicHistoryRepository $projectTaskPicHistoryRepo,
        UserRepository $user
    )
    {
        $this->repo = $repo;
        $this->generalService = $generalService;
        $this->cacheService = $cacheService;
        $this->employeeRepo = $employeeRepo;
        $this->projectTaskRepo = $projectTaskRepo;
        $this->projectBoardRepo = $projectBoardRepo;
        $this->projectReferenceRepo = $projectReferenceRepo;
        $this->projectTaskPicRepo = $projectTaskPicRepo;
        $this->projectTaskDeadlineRepo = $projectTaskDeadlineRepo;
        $this->projectTaskAttachmentRepo = $projectTaskAttachmentRepo;
        $this->projectTaskHoldStateRepo = $projectTaskHoldStateRepo;
        $this->projectTaskWorkStateRepo = $projectTaskWorkStateRepo;
        $this->taskProofRepo = $taskProofRepo;
        $this->projectTaskPicHistoryRepo = $projectTaskPicHistoryRepo;
        $this->employeeRepo = $employeeRepo;
        $this->user = $user;
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
            $user = $this->user->detail(id: Auth::id(), select: 'id,email,employee_id', relation: [
                'employee:id'
            ]);

            $itemsPerPage = request('itemsPerPage') ?? 50;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            // $rawData = $this->cacheService->getFilteredProjects(filters: $param, page: $page, perPage: $itemsPerPage);
            

            // $paginated = $rawData['data'] ?? [];
            // $totalData = $rawData['total'] ?? 0;

            $where = "id > 0";

            // show list based on role
            $whereHas = [];

            if ($user->hasRole(BaseRole::Production->value)) {
                $tasks = $this->projectTaskRepo->list(
                    select: 'id',
                    whereHas: [
                        [
                            'relation' => 'pics',
                            'query' => "employee_id = {$user->employee->id}"
                        ]
                    ]
                );

                $taskIds = $tasks->pluck('id')->implode(',');
                $query = $tasks->count() > 0 ? "id IN ({$taskIds})" : "1 = 0";

                $whereHas[] = [
                    'relation' => 'tasks',
                    'query' => $query
                ];
            }

            if ($user->hasRole(BaseRole::ProjectManager->value) || $user->hasRole(BaseRole::ProjectManagerAdmin->value) || $user->hasRole(BaseRole::ProjectManagerEntertainment->value)) {
                $whereHas[] = [
                    'relation' => 'pics',
                    'query' => "employee_id = {$user->employee->id}"
                ];
            }

            // applied filter
            if (request('status')) {
                $statusIds = collect(request('status'))->pluck('id')->implode(',');

                if (!empty($statusIds)) {
                    $where .= " and status IN ({$statusIds})";
                }
            }

            if (request('pics')) {
                $employeeUids = collect(request('pics'))->map(function ($picUid) {
                    if ($picUid) {
                        return $this->generalService->getIdFromUid($picUid, new Employee());
                    }
                })->implode(',');

                if (!empty($employeeUids)) {
                    $whereHas[] = [
                        'relation' => 'pics',
                        'query' => "employee_id IN ({$employeeUids})"
                    ];
                }
            }

            if (request('event') && !empty(request('event'))) {
                $where .= " and name LIKE '%" . request('event') . "%'";
            }

            $paginated = $this->repo->pagination(
                select: $select,
                where: $where,
                relation: $relation,
                itemsPerPage: $itemsPerPage,
                page: $page,
                whereHas: $whereHas
            );
            $paginated = $paginated->map(function ($project) {
                return [
                    'id' => $project->id,
                    'uid' => $project->uid,
                    'name' => $project->name,
                    'description' => $project->description,
                    'status' => $project->status,
                    'status_text' => $project->status->label(),
                    'status_color' => $project->status->color(),
                    'project_date' => $project->project_date ? $project->project_date->format('Y-m-d') : null,
                    'project_date_text' => $project->project_date_text,
                    'created_by' => $project->created_by,
                    'pic_name' => $project->pics->pluck('employee.nickname')->implode(','),
                    'total_task' => $project->tasks->count(),
                    'pics' => $project->pics->map(function ($pic) {
                        return [
                            'id' => $pic->employee_id,
                            'nickname' => $pic->employee->nickname
                        ];
                    })->toArray(),
                    'pic_uids' => $project->pics->pluck('employee.uid')->toArray()
                ];
            });

            $totalData = $this->repo->list(select: 'id', where: $where, whereHas: $whereHas)->count();

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

    public function datatable()
    {
        //
    }

    /**
     * Calculate data for completed task
     * We need total task and total completed task
     */
    protected function calculateCompletedTask(Collection|DevelopmentProject $project)
    {
        $totalTasks = $project->count();
        $completedTasks = $project->where('status', TaskStatus::Completed)->count();
        $percentage = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

        return [
            'total' => $totalTasks,
            'completed' => $completedTasks,
            'percentage' => $percentage,
        ];
    }

    protected function calculateWeeklyProgress(Collection|DevelopmentProject $project)
    {

    }

    protected function getPicTeams(int $bossId): Collection
    {
        return $this->employeeRepo->list(
            select: 'id,uid,nickname,name,position_id,avatar_color',
            where: "boss_id = {$bossId}",
            relation: [
                'position:id,name'
            ]
        );
    }

    protected function getTotalTaskInEachEmployeeForEachEvent(int $employeeId, int $projectId)
    {
        return $this->projectTaskRepo->list(
            select: 'id',
            where: "development_project_id = {$projectId}",
        )->count();
    }

    public function getBoardTasks()
    {

    }

    public function getProjectBoards(int $projectId): SupportCollection
    {
        $data = $this->projectBoardRepo->list(
            select: 'id,name',
            relation: [
                'tasks:id,uid,name,development_project_id,development_project_board_id,description,status,deadline,created_at',
                'tasks.attachments:uid,task_id,file_path,created_at',
                'tasks.pics:id,task_id,employee_id',
                'tasks.pics.employee:id,uid,nickname,avatar_color,name',
                'tasks.taskProofs:id,task_id,nas_path,created_at',
                'tasks.taskProofs.images:id,development_task_proof_id,image_path',
                'tasks.revises.images'
            ],
            where: "development_project_id = {$projectId}"
        );

        return $data->map(function ($board) {
            return [
                'id' => $board->id,
                'name' => $board->name,
                'tasks' => $board->tasks->map(function ($task) use ($board) {
                    $task['proofOfWorks'] = collect([]);

                    return [
                        'uid' => $task->uid,
                        'name' => $task->name,
                        'description' => $task->description,
                        'start_date' => date('d F Y H:i', strtotime($task->created_at)),
                        'end_date' => $task->deadline ? date('d M Y, H:i', strtotime($task->deadline)) : null,
                        'status' => $task->status,
                        'status_text' => $task->status->label(),
                        'status_color' => $task->status->color(),
                        'board_id' => $board->id,
                        'revises' => $task->revises->map(function ($revise) {
                            return [
                                'revise_at' => date('d F Y H:i', strtotime($revise->created_at)),
                                'images' => $revise->images,
                                'id' => $revise->id,
                                'reason' => $revise->reason,
                            ];
                        }),
                        'proof_of_works' => $task->taskProofs->map(function ($proof) {
                            $items = $proof->images->map(function ($image) {
                                return $image->real_image_path;
                            });
                            return [
                                'images' => $items,
                                'id' => $proof->id,
                                'nas_path' => $proof->nas_path,
                                'created_at' => Carbon::parse($proof->created_at)->format('d F Y H:i')
                            ];
                        })->groupBy('created_at'),
                        'medias' => $task->attachments->map(function ($attachment) {
                            // get extenstion type
                            $extension = pathinfo($attachment->real_file_path, PATHINFO_EXTENSION);
                            return [
                                'id' => $attachment->uid,
                                'media_type' => 'media',
                                'media_link' => $attachment->real_file_path,
                                'ext' => $extension,
                                'update_timing' => date('d F Y H:i', strtotime($attachment->created_at))
                            ];
                        }),
                        'pics' => $task->pics->map(function ($pic) {
                            // set initial name based on name value
                            $initial = substr($pic->employee->name, 0, 1);
                            return [
                                'uid' => $pic->employee->uid,
                                'name' => $pic->employee->name,
                                'avatar_color' => $pic->employee->avatar_color,
                                'initial' => $initial
                            ];
                        }),
                        'can_delete_attachment' => true,
                        'action_list' => DefineTaskAction::run($task)
                    ];
                }),
            ];
        })->values();
    }

    protected function getProjectReferences(int $projectId)
    {
        $data = $this->projectReferenceRepo->list(
            select: 'id,type,media_path,link,link_name',
            where: "development_project_id = {$projectId}"
        );

        // format first
        $data = $data->map(function ($reference) {
            // get extension if reference type is media
            // define type
            // if reference type is media, variable $type will be 'files'
            // if reference type is media and have extension .docx, .doc, .pdf then $type will be 'pdf'
            $type = 'link';
            $extension = null;
            if ($reference->type == \App\Enums\Development\Project\ReferenceType::Media->value) {
                $extension = pathinfo(storage_path('app/public/' . $reference->full_path), PATHINFO_EXTENSION);

                if (in_array($extension, ['docx', 'doc', 'pdf'])) {
                    $type = 'pdf';
                }
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $type = 'files';
                }
            }


            return [
                'id' => $reference->id,
                'type' => $type,
                'extension' => $extension,
                'media_path' => $reference->real_media_path,
                'link' => $reference->link,
                'link_name' => $reference->link_name,
                'image_name' => $reference->media_path
            ];
        });

        // group by types
        $groups = $data->groupBy('type');

        return $groups;
    }

    /**
     * Get detail data for show
     *
     * @param string $uid
     * @return array
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show(uid: $uid, relation: [
                'tasks:id,name',
                'pics',
                'pics.employee:id,name,position_id,avatar_color',
                'pics.employee.position:id,name',
            ]);

            // get complete task percentage
            $completeTaskPercentage = $this->calculateCompletedTask($data);
            
            $teams = [];
            foreach ($data->pics as $pic) {
                $teams[] = $this->getPicTeams(bossId: $pic->employee_id);
            }

            $teams = collect($teams)->flatten(1)->unique('id')->values()->map(function ($team) {
                return [
                    'uid' => $team->uid,
                    'name' => $team->name,
                    'position' => [
                        'name' => $team->position->name,
                    ],
                    'loan' => false,
                    'is_lead_modeller' => false,
                    'total_task' => 0,
                    'avatar_color' => $team->avatar_color,
                    'image' => asset('images/user.png')
                ];
            });

            // get project boards include with all task in each board
            $boards = $this->getProjectBoards(projectId: $data->id);

            // get project references
            $references = $this->getProjectReferences(projectId: $data->id);

            $output = [
                'completeTaskPercentage' => $completeTaskPercentage,
                'uid' => $data->uid,
                'name' => $data->name,
                'description' => $data->description,
                'status_raw' => $data->status->value,
                'status_text' => $data->status->label(),
                'status_color' => $data->status->color(),
                'project_date' => $data->project_date_text,
                'pic_names' => $data->pics->pluck('employee.name')->implode(','),
                'teams' => $teams,
                'references' => $references,
                'boards' => $boards,
                'project_is_complete' => $data->status === \App\Enums\Development\Project\ProjectStatus::Completed ? true : false,
                'permission_list' => [
                    'add_task' => true
                ]
            ];

            return generalResponse(
                message: "Success",
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get detail data for edit
     *
     * @param string $uid
     * @return array
     */
    public function edit(string $uid): array
    {
        try {
            $data = $this->repo->show(
                uid: $uid,
                select: 'id,uid,name,description,status,project_date,created_by',
                relation: [
                    'pics:id,development_project_id,employee_id',
                    'pics.employee:id,uid',
                    'references'
                ]
            );

            return generalResponse(
                'success',
                false,
                $data->toArray(),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    protected function uploadProjectReferences(Collection|DevelopmentProject $project, array $references): void
    {
        // upload image if type = media
        foreach ($references as $reference) {
            if ($reference['type'] != 'remove') {
                $payloadReferences[] = [
                    'type' => $reference['type'],
                ];
            }

            if ($reference['type'] === ReferenceType::Media->value) {
                // handle media upload
                $media = $this->generalService->uploadImageandCompress(
                    path: self::MEDIAPATH,
                    compressValue: 0,
                    image: $reference['image']
                );
                $payloadReferences[count($payloadReferences) - 1]['media_path'] = $media;
            } else if ($reference['type'] === ReferenceType::Link->value) {
                $payloadReferences[count($payloadReferences) - 1]['link'] = $reference['link'];
                $payloadReferences[count($payloadReferences) - 1]['link_name'] = $reference['link_name'];
            }
        }


        $project->references()->createMany($payloadReferences);
    }

    /**
     * Store data
     *
     * @param array $data               With these following structure
     * - name: string
     * - description: string|null
     * - references: array|null
     * - pics: array|null
     * - project_date: string (format: Y-m-d)
     * @return array
     */
    public function store(array $data): array
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->store($data);

            // attach references if exists
            if (!empty($data['references'])) {
                // create new variables as payload to references table
                $payloadReferences = [];

                // upload image if type = media
                $this->uploadProjectReferences($project, $data['references']);
            }

            // attach pics if exists
            if (!empty($data['pics'])) {
                // get id of each employee id. Employee_id is string which is using uid
                $employees = collect($data['pics'])->map(function ($pic) {
                    return [
                        'employee_id' => $this->generalService->getIdFromUid($pic['employee_id'], new Employee()),
                    ];
                })->toArray();

                $project->pics()->createMany($employees);
            }

            // attach boards
            $defaultBoards = json_decode($this->generalService->getSettingByKey('default_boards'), true);
            $defaultBoards = collect($defaultBoards)->map(function ($item) {
                return [
                    'name' => $item['name'],
                ];
            })->values()->toArray();
            if ($defaultBoards) {
                $project->boards()->createMany($defaultBoards);
            }

            // push new data to current cache
            $this->cacheService->pushNewProjectToAllProjectCache($project->uid);
            $this->cacheService->invalidateAllCacheExceptBase();

            DB::commit();

            return generalResponse(message: __('notification.successCreateDevelopmentProject'));
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Update selected data
     * Here we only update name, project date and description
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
            // update main table
            $this->repo->update(data: $data, id: $id);

            // update cache in all project cache
            $project = $this->cacheService->formatingProjectOutput(
                project: $this->repo->show(uid: $id, select: '*')
            );

            $this->cacheService->updateSpecificCache(payload: $project);
            $this->cacheService->invalidateAllCacheExceptBase();

            return generalResponse(message: __('notification.successUpdateDevelopmentProject'));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }   

    /**
     * Delete selected data
     * What should be done in this function:
     * 1. Validate status. Only on hold and cancelled that can be deleted
     * 2. Remove all references from database, remove image references from storage folder
     * 3. Remove all tasks
     * 4. Remove all boards
     * 5. Remove project
     * 6. delete cache
     *
     * @param integer $id
     * 
     * @return array
     */
    public function delete(string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->show(
                uid: $projectUid,
                select: 'id',
                relation: [
                    'references',
                ]
            );

            foreach ($project->references as $reference) {
                if ($reference->type == ReferenceType::Media->value) {
                    // check if file exists
                    if (Storage::disk('public')->exists(self::MEDIAPATH . '/' . $reference->media_path)) {
                        // delete file
                        Storage::disk('public')->delete(self::MEDIAPATH . '/' . $reference->media_path);
                    }
                }

                $reference->delete();
            }

            $project->pics()->delete();
            $project->tasks()->delete();
            $project->boards()->delete();
            $project->delete();

            // delete cache
            $this->cacheService->deleteSpecificProjectByUid(projectUid: $projectUid);
            $this->cacheService->invalidateAllCacheExceptBase();

            DB::commit();

            return generalResponse(
                __('notification.successDeleteDevelopmentProject'),
                false
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
     * Assign PIC to task
     *
     * @param array $payload
     * @param string $taskUid
     * @param boolean $useTransaction
     * @return array
     */
    public function assignDeadlineToTask(array $payload, string $taskUid, bool $useTransaction = true): array
    {
        if ($useTransaction) {
            DB::beginTransaction();
        }
        try {
            if ($useTransaction) {
                DB::commit();
            }

            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id', relation: [
                'pics'
            ]);

            $payloadUpsert = [
                ['task_id' => $task->id, 'deadline' => $payload['end_date']]
            ];

            if ($task->pics->count() > 0) {
                // reset variable
                $payloadUpsert = [];

                // re-assign variable
                foreach ($task->pics as $pic) {
                    $payloadUpsert[] = [
                        'task_id' => $task->id,
                        'employee_id' => $pic->employee_id,
                        'deadline' => $payload['end_date']
                    ];
                }
            }

            // upsert deadline
            $this->projectTaskDeadlineRepo->upsert(
                payload: $payloadUpsert,
                uniqueBy: ['task_id', 'employee_id'],
                updateValue: ['end_date']
            );

            return generalResponse(
                message: __('notification.successAddDeadline')
            );
        } catch (\Throwable $th) {
            if ($useTransaction) {
                DB::rollBack();
            }
            return errorResponse($th);
        }
    }

    /**
     * Assign PIC to task
     *
     * @param array $payload
     * @param integer $projectId
     * @return array
     */
    public function assignPicToTask(array $payload, string $taskUid, bool $useTransaction = true): array
    {
        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, relation: [
                'deadlines'
            ]);
    
            // attach pics if payload contain 'pics' and payload['pics'] is not empty
            if (
                (isset($payload['pics'])) &&
                (!empty($payload['pics']))
            ) {
                foreach ($payload['pics'] as $pic) {
                    $picId = $this->generalService->getIdFromUid($pic['employee_uid'], new \Modules\Hrd\Models\Employee());

                    // assign to main table
                    // if pic_id and employee_id combination already exists, do not insert the record
                    $check = $this->projectTaskPicRepo->show(uid: 'id', select: 'id', where: "task_id = {$task->id} AND employee_id = {$picId}");
                    if (!$check) {
                        $task->pics()->create([
                            'employee_id' => $picId
                        ]);
                    }

                    // assign to pic histories table
                    $this->projectTaskPicHistoryRepo->upsert(
                        payload: [
                            ['task_id' => $task->id, 'employee_id' => $picId, 'is_until_finish' => true]
                        ],
                        uniqueBy: ['task_id', 'employee_id'],
                        updateValue: ['is_until_finish']
                    );

                    // if task already have a deadline and give picId is not associated with this task deadline, we need to add this pic to table development_project_task_deadlines
                    if ($task->deadline && $task->deadlines->where('employee_id', $picId)->isEmpty()) {
                        $this->projectTaskDeadlineRepo->store([
                            'employee_id' => $picId,
                            'deadline' => $task->deadline,
                            'task_id' => $task->id,
                            // if task status already InProgress, then start time should be Carbon::now()
                            'start_time' => $task->status === \App\Enums\Development\Project\Task\TaskStatus::InProgress ? Carbon::now() : null
                        ]);
                    }

                    // if task status is InProgress, insert new pic to workstate table
                    if ($task->status === \App\Enums\Development\Project\Task\TaskStatus::InProgress) {
                        $this->projectTaskWorkStateRepo->store([
                            'employee_id' => $picId,
                            'task_id' => $task->id,
                            'started_at' => Carbon::now(),
                        ]);
                    }
                }
            }

            if ($useTransaction) {
                DB::commit();
            }

            return generalResponse(
                message: __('notification.successAssignPicToTask')
            );
        } catch (\Throwable $th) {
            if ($useTransaction) {
                DB::rollBack();
            }

            return errorResponse($th);
        }
    }

    /**
     * Update all tasks inside of selected board.
     * Get all tasks from selected board
     *
     * @param string $projectUid
     * @return array
     */
    public function updateProjectBoards(string $projectUid): array
    {
        $projectId = $this->generalService->getIdFromUid($projectUid, new \Modules\Development\Models\DevelopmentProject());

        $boards = $this->getProjectBoards($projectId);

        return generalResponse(
            message: "Success",
            data: $boards->toArray()
        );
    }

    /**
     * Create task for development project task
     * 
     * @param array $payload                    With these following structure:
     * - string $name
     * - string $description
     * - int $board_id
     * - array $images                              With these following structure:
     *   - File $image
     * - array $pics                                 With these following structure:
     *   - string $employee_uid
     * - string $end_date
     * @param string $projectUid
     * 
     * @return array
     */
    public function storeTask(array $payload, string $projectUid): array
    {
        DB::beginTransaction();

        $tmpFiles = [];
        try {
            $project = $this->repo->show(uid: $projectUid, select: 'id');
            $payload['status'] = (isset($payload['pics'])) && (!empty($payload['pics'])) ? TaskStatus::WaitingApproval->value : TaskStatus::Draft->value;

            $task = $project->tasks()->create([
                'development_project_board_id' => $payload['board_id'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'],
                'deadline' => $payload['end_date'] ?? null,
            ]);

            // upload task attachments if any
            if (
                (isset($payload['images'])) &&
                (!empty($payload['images']))
            ) {
                foreach ($payload['images'] as $image) {
                    $media = $this->generalService->uploadImageandCompress(
                        path: self::MEDIATASKPATH,
                        compressValue: 0,
                        image: $image['image']
                    );

                    if (!$media) {
                        // return error
                        throw new \Exception(__('notification.errorUploadTaskImage'));
                    }

                    $tmpFiles[] = $media;
                }
            }

            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    $task->attachments()->create([
                        'file_path' => $tmpFile,
                    ]);
                }
            }

            if (isset($payload['pics'])) {
                $pic = $this->assignPicToTask(payload: $payload, taskUid: $task->uid, useTransaction: false);
    
                if ($pic['error']) {
                    throw new Exception($pic['message']);
                }

                // send notification
                NotifyTaskAssigneeJob::dispatch(
                    asignessUids: collect($payload['pics'])->pluck('employee_uid')->toArray(),
                    task: $task
                )->afterCommit();
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successCreateTask'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            // delete tmp files
            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    if (Storage::disk('public')->exists(self::MEDIATASKPATH . '/' . $tmpFile)) {
                        Storage::disk('public')->delete(self::MEDIATASKPATH . '/' . $tmpFile);
                    }
                }
            }

            return errorResponse($th);
        }
    }

    public function downloadAttachment(string $taskId, string $attachmentId)
    {
        try {
            $data = $this->projectTaskAttachmentRepo->show($attachmentId, 'file_path,task_id', [], "id = {$attachmentId}");
            
            return \Illuminate\Support\Facades\Storage::download(self::MEDIATASKPATH . '/' . $data->file_path);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete a task attachment.
     * 
     * @param string $projectUId
     * @param string $taskUid
     * @param string $attachmentId
     * 
     * @return array
     */
    public function deleteTaskAttachment(string $projectUid, string $taskUid, string $attachmentId): array
    {
        try {
            $image = $this->projectTaskAttachmentRepo->show(uid: $attachmentId);

            if (Storage::disk('public')->exists(self::MEDIATASKPATH . '/' . $image->file_path)) {
                Storage::disk('public')->delete(self::MEDIATASKPATH . '/' . $image->file_path);
            }

            $image->delete();

            // get detail of project board
            $projectId = $this->generalService->getIdFromUid($projectUid, new DevelopmentProject());
            $boards = $this->getProjectBoards(projectId: $projectId);

            return generalResponse(
                message: __('notification.attachmentHasBeenDeleted'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Remove current members from a task.
     *
     * @param array $memberIds
     * @param integer $taskId
     * @return void
     */
    protected function removeMembersFromTask(array $memberIds, int $taskId): void
    {
        foreach ($memberIds as $memberId) {
            // Remove deadline history for selected member
            $this->projectTaskDeadlineRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId}");

            // Remove from task pics
            $this->projectTaskPicRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId}");

            // remove from workstates
            $this->projectTaskWorkStateRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId}");

            // remove from holdstates
            $this->projectTaskHoldStateRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId}");

            // update task pic histories
            $this->projectTaskPicHistoryRepo->update(
                data: [
                    'is_until_finish' => false,
                ],
                where: "employee_id = {$memberId} and task_id = {$taskId}"
            );
        }
    }

    /**
     * Assign pictures to a task.
     *
     * @param array $payload
     * @param string $taskUid
     * @return array
     */
    public function addTaskMember(array $payload, string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $taskId = $this->generalService->getIdFromUid($taskUid, new \Modules\Development\Models\DevelopmentProjectTask());

            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,development_project_id', relation: [
                'picHistories'
            ]);

            // remove pic if needed
            if (
                (isset($payload['removed'])) &&
                ($payload['removed'])
            ) {
                $removedIds = collect($payload['removed'])->map(function ($item) {
                    return $this->generalService->getIdFromUid($item, new Employee());
                })->toArray();

                if (!empty($removedIds)) {
                    $this->removeMembersFromTask(memberIds: $removedIds, taskId: $taskId);
                }
            }

            // add new pics
            $this->assignPicToTask(
                payload: [
                    'pics' => collect($payload['users'])->map(function ($user) {
                        return [
                            'employee_uid' => $user
                        ];
                    })->toArray()
                ],
                taskUid: $taskUid,
                useTransaction: false
            );

            // if task pics is empty and task status is InProgress, then change task status to draft
            $newTask = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,deadline,name', relation: [
                'pics'
            ]);
            if ($newTask->status === TaskStatus::InProgress && $newTask->pics->isEmpty()) {
                $this->projectTaskRepo->update(
                    data: [
                        'status' => TaskStatus::Draft->value,
                    ],
                    id: $taskUid
                );
            }

            // if current task status is Completed or Draft, and we have pics, change status to Waiting Approval
            if (
                ($newTask->status === TaskStatus::Completed || $newTask->status === TaskStatus::Draft) &&
                $newTask->pics->count() > 0
            ) {
                $this->projectTaskRepo->update(
                    data: [
                        'status' => TaskStatus::WaitingApproval->value,
                    ],
                    id: $taskUid
                );
            }

            if (!empty($payload['users'])) {
                // send notification
                NotifyTaskAssigneeJob::dispatch(
                    asignessUids: $payload['users'],
                    task: $newTask
                )->afterCommit();
            }

            // get project boards
            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.successAssignPicToTask'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Delete a task and all its related resources.
     *
     * @param string $taskUid
     * @return array
     */
    public function deleteTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, relation: [
                'pics',
                'attachments',
                'deadlines'
            ]);

            $currentProjectId = $task->development_project_id;

            // delete all pics
            $task->pics()->delete();

            $task->picHistories()->delete();

            // delete all attachment in the storage first
            $task->attachments->each(function ($attachment) {
                if (Storage::disk('public')->exists(self::MEDIATASKPATH . '/' . $attachment->file_path)) {
                    Storage::delete(self::MEDIATASKPATH . '/' . $attachment->file_path);
                }
            });

            // delete all attachments
            $task->attachments()->delete();

            // delete all deadlines
            $task->deadlines()->delete();

            $task->delete();

            // get boards
            $boards = $this->getProjectBoards(projectId: $currentProjectId);

            DB::commit();

            return generalResponse(
                message: __('notification.successDeleteTask'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Record the work state of a task.
     *
     * @param DevelopmentProjectTask|Collection $task
     */
    public function recordWorkState(DevelopmentProjectTask|Collection $task): void
    {
        foreach ($task->pics as $pic) {
            $this->projectTaskWorkStateRepo->store([
                'started_at' => Carbon::now(),
                'finished_at' => null,
                'task_id' => $task->id,
                'employee_id' => $pic->employee_id,
            ]);
        }
    }

    /**
     * Record the hold state of a task.
     *
     * @param DevelopmentProjectTask|Collection $task
     * @return void
     */
    public function recordHoldState(DevelopmentProjectTask|Collection $task): void
    {
        foreach ($task->pics as $pic) {
            $workState = $this->projectTaskWorkStateRepo->show(
                uid: 'uid',
                select: 'id',
                where: "employee_id = {$pic->employee_id} and task_id = {$task->id}"
            );

            $workState->holdStates()->create([
                'employee_id' => $pic->employee_id,
                'task_id' => $task->id,
                'holded_at' => Carbon::now()
            ]);
        }
    }

    public function stopHoldState(DevelopmentProjectTask|Collection $task): void
    {
        foreach ($task->pics as $pic) {
            // get current work state in each pic
            $currentWorkState = $this->projectTaskWorkStateRepo->show(
                uid: 'uid',
                select: 'id',
                where: "employee_id = {$pic->employee_id} and task_id = {$task->id}"
            );

            // stop the hold state
            $this->projectTaskHoldStateRepo->update(
                data: [
                    'unholded_at' => Carbon::now()
                ],
                where: "work_state_id = {$currentWorkState->id}"
            );
        }
    }

    /**
     * Approve a task and update its deadlines.
     * 
     * @param string $taskUid
     * 
     * @return array
     */
    public function approveTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,development_project_id', relation: [
                'deadlines',
                'pics'
            ]);

            $this->projectTaskRepo->update(
                data: [
                    'status' => TaskStatus::InProgress->value
                ],
                id: $taskUid
            );

            // update start time in the deadlines table
            if ($task->deadlines->count() > 0) {
                foreach ($task->deadlines as $deadline) {
                    $this->projectTaskDeadlineRepo->update(
                        data: [
                            'start_time' => Carbon::now()
                        ],
                        id: $deadline->id
                    );
                }
            }

            // record workstate
            $this->recordWorkState(task: $task);

            // should update boards
            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.taskHasBeenApproved'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Hold a task and update its hold states.
     *
     * @param string $taskUid
     *
     * @return array
     */
    public function holdTask(string $taskUid): array
    {
        DB::beginTransaction();

        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,development_project_id', relation: [
                'pics:id,task_id,employee_id'
            ]);

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => TaskStatus::OnHold->value
                ],
                id: $taskUid
            );

            // record for hold states
            $this->recordHoldState(task: $task);

            // refresh boards
            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.taskHasBeenHold'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Start the task after tas has been hold
     * 
     * @param string $taskUid
     * 
     * @return array
     */
    public function startTaskAfterHold(string $taskUid): array
    {
        DB::beginTransaction();

        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,development_project_id', relation: [
                'pics:id,task_id,employee_id'
            ]);

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => TaskStatus::InProgress->value
                ],
                id: $taskUid
            );

            // end the hold state
            $this->stopHoldState(task: $task);

            // refresh the boards
            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.taskHasBeenStartedAgain'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorResponse($th);
        }
    }

    /**
     * Submit task proofs.
     * 
     * @param array $payload
     * @param string $taskUid
     * 
     * @return array
     */
    public function submitTaskProofs(array $payload, string $taskUid): array
    {
        $tmpFiles = [];

        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,development_project_id', relation: [
                'pics:id,task_id,employee_id',
                'workStates',
                'deadlines:id,task_id'
            ]);

            // upload proof of works
            foreach ($payload['images'] as $image) {
                $media = $this->generalService->uploadImageandCompress(
                    path: self::PROOFPATH,
                    compressValue: 0,
                    image: $image['image']
                );

                if (!$media) {
                    // return error
                    throw new \Exception(__('notification.errorUploadTaskImage'));
                }

                $tmpFiles[] = $media;
            }

            $bossIds = [];
            foreach ($task->pics as $pic) {
                $proof = $task->taskProofs()->create([
                    'nas_path' => $payload['nas_path'],
                    'employee_id' => $pic->employee_id
                ]);

                foreach ($tmpFiles as $file) {
                    $proof->images()->create([
                        'image_path' => $file
                    ]);
                }

                // get boss id
                $boss = $this->employeeRepo->show(uid: 'id', where: "id = {$pic->employee_id}", select: 'id,boss_id');
                $bossIds[] = $boss->boss_id;
            }

            // remove duplicate from bossIds
            $bossIds = array_values(array_unique($bossIds));

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => TaskStatus::CheckByPm->value,
                    'current_pic_id' => $task->pics->pluck('employee_id')->implode(',')
                ],
                id: $taskUid
            );

            // remove all current pics
            $task->pics()->delete();

            // assign to boss
            foreach ($bossIds as $bossId) {
                $task->pics()->create([
                    'employee_id' => $bossId
                ]);
            }

            // update finished_at in workstate stable
            foreach ($task->workStates as $state) {
                $this->projectTaskWorkStateRepo->update(
                    data: [
                        'finished_at' => Carbon::now()
                    ],
                    id: $state->id
                );
            }

            // update deadline actual_end_time if exists
            foreach ($task->deadlines as $deadline) {
                $this->projectTaskDeadlineRepo->update(
                    data: [
                        'actual_end_time' => Carbon::now()
                    ],
                    id: $deadline->id
                );
            }

            // update boards
            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.taskSubmitted'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $file) {
                    if (Storage::disk('public')->exists(self::PROOFPATH . '/' . $file)) {
                        Storage::disk('public')->delete(self::PROOFPATH . '/' . $file);
                    }
                }
            }

            return errorResponse($th);
        }
    }

    /**
     * Complete a task
     * 
     * @param string $taskUid
     * 
     * @return array
     */
    public function completeTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            // get task detail
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,development_project_id,development_project_board_id', relation: [
                'pics:id,task_id,employee_id',
                'developmentProject:id',
                'developmentProject.boards'
            ]);

            $payloadTask = [
                'status' => TaskStatus::Completed
            ];

            // move to the next board
            $currentBoards = $task->developmentProject->boards->pluck('id')->toArray();
            $currentBoardKey = array_search($task->development_project_board_id, $currentBoards);
            $nextKey = $currentBoardKey + 1;
            if (isset($currentBoards[$nextKey])) {
                $payloadTask['development_project_board_id'] = $currentBoards[$nextKey];
            }
                
            // update task status
            $this->projectTaskRepo->update(
                data: $payloadTask,
                id: $taskUid
            );

            // TODO: Calculate duration of work

            // detach all pics from this task and put to pic histories table
            $task->pics()->delete();

            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.taskHasBeenComplete'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Revise a task
     * 
     * @param array $paylad                 With these following structure
     * - array $images                          With these following structure
     *      - File $image
     * - string $reason
     */
    public function reviseTask(array $payload, string $taskUid): array
    {
        $tmpFiles = [];
        DB::beginTransaction();
        try {
            // get detail task
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,development_project_id,current_pic_id', relation: [
                'pics:id,task_id,employee_id'
            ]);

            foreach ($payload['images'] as $image) {
                $media = $this->generalService->uploadImageandCompress(
                    path: self::REVISEPATH,
                    compressValue: 0,
                    image: $image['image']
                );

                if (!$media) {
                    // return error
                    throw new \Exception(__('notification.errorUploadTaskImage'));
                }

                $tmpFiles[] = $media;
            }

            $revise = $task->revises()->create([
                'reason' => $payload['reason'],
                'assigned_by' => Auth::id()
            ]);

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => TaskStatus::Revise
                ],
                id: $taskUid
            );

            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    $revise->images()->create([
                        'image_path' => $tmpFile,
                    ]);
                }
            }

            // detach current pics, which is pm
            $task->pics()->delete();

            // reassign current worker
            $currentPicIds = explode(',', $task->current_pic_id);
            // $currentPicUids = collect($currentPicIds)->map(function ($item) {
            //     $employee = $this->employeeRepo->show(uid: 'uid', select: 'id,uid', where: "id = {$item}");

            //     return [
            //         'employee_uid' => $employee->uid
            //     ];
            // })->toArray();

            // // add new pics
            // $this->assignPicToTask(
            //     payload: [
            //         'pics' => $currentPicUids
            //     ],
            //     taskUid: $taskUid,
            //     useTransaction: false
            // );
            foreach ($currentPicIds as $currentPicId) {
                $task->pics()->create([
                    'employee_id' => $currentPicId
                ]);
            }

            // refresh boards
            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.revisedHasBeenSubmitted'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            // delete tmp files
            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    if (Storage::disk('public')->exists(self::MEDIATASKPATH . '/' . $tmpFile)) {
                        Storage::disk('public')->delete(self::MEDIATASKPATH . '/' . $tmpFile);
                    }
                }
            }

            return errorResponse($th);
        }
    }

    /**
     * Move task to a different board
     * 
     * @param string $taskUid
     * @param int $boardId
     * 
     * @return array
     */
    public function moveBoardId(string $taskUid, int $boardId): array
    {
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,development_project_id', relation: [
                'pics:id,task_id,employee_id'
            ]);

            $this->projectTaskRepo->update(
                data: [
                    'development_project_board_id' => $boardId
                ],
                id: $taskUid
            );

            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            return generalResponse(
                message: __('notification.taskBoardHasBeenUpdated'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store project references.
     * 
     * @param array $payload
     * @param string $projectUid
     * 
     * @return array
     */
    public function storeReferences(array $payload, string $projectUid): array
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->show(uid: $projectUid, select: 'id');

            $this->uploadProjectReferences($project, $payload['references']);

            $references = $this->getProjectReferences(projectId: $project->id);

            DB::commit();

            return generalResponse(
                message: __('notification.successAddReference'),
                data: $references->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Delete a project reference.
     * 
     * @param string $taskUid
     * @param int $referenceId
     * 
     * @return array
     */
    public function deleteReference(string $projectUid, int $referenceId): array
    {
        try {
            $project = $this->repo->show(uid: $projectUid, select: 'id');

            $reference = $this->projectReferenceRepo->show(uid: $referenceId, select: 'id,media_path,type');

            if (Storage::disk('public')->exists(self::MEDIAPATH . '/' . $reference->media_path)) {
                Storage::disk('public')->delete(self::MEDIAPATH . '/' . $reference->media_path);
            }

            $reference->delete();

            $references = $this->getProjectReferences(projectId: $project->id);

            return generalResponse(
                message: __('notification.referenceHasBeenDeleted'),
                data: $references->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Get related tasks for a specific project.
     * 
     * @param string $projectUid
     * @param string $taskUid
     * 
     * @return array
     */
    public function getRelatedTask(string $projectUid, string $taskUid): array
    {
        try {
            $projectId = $this->generalService->getIdFromUid($projectUid, new DevelopmentProject());

            $tasks = $this->projectTaskRepo->list(
                relation: [],
                select: 'id,uid,name',
                where: "development_project_id = {$projectId} and uid != '{$taskUid}' and status != " . TaskStatus::Draft->value,
            );

            return generalResponse(
                message: "Success",
                data: $tasks->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Store attachments for a specific task.
     * 
     * @param array $payload
     * @param string $taskUid
     * 
     * @return array
     */
    public function storeAttachments(array $payload, string $taskUid): array
    {
        DB::beginTransaction();
        $tmpFiles = [];
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,development_project_id', relation: [
                'attachments'
            ]);

            // upload task attachments if any
            if (
                (isset($payload['images'])) &&
                (!empty($payload['images']))
            ) {
                foreach ($payload['images'] as $image) {
                    $media = $this->generalService->uploadImageandCompress(
                        path: self::MEDIATASKPATH,
                        compressValue: 0,
                        image: $image['image']
                    );

                    if (!$media) {
                        // return error
                        throw new \Exception(__('notification.errorUploadTaskImage'));
                    }

                    $tmpFiles[] = $media;
                }
            }

            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    $task->attachments()->create([
                        'file_path' => $tmpFile,
                    ]);
                }
            }

            $boards = $this->getProjectBoards(projectId: $task->development_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.successAddAttachment'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            // delete tmp files
            if (!empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    if (Storage::disk('public')->exists(self::MEDIATASKPATH . '/' . $tmpFile)) {
                        Storage::disk('public')->delete(self::MEDIATASKPATH . '/' . $tmpFile);
                    }
                }
            }

            return errorResponse($th);
        }
    }
}