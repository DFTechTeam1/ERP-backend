<?php

namespace Modules\Development\Services;

use App\Enums\Development\Project\ReferenceType;
use App\Enums\Development\Project\Task\TaskStatus;
use App\Enums\ErrorCode\Code;
use App\Services\GeneralService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Development\app\Services\DevelopmentProjectCacheService;
use Modules\Development\Models\DevelopmentProject;
use Modules\Development\Repository\DevelopmentProjectBoardRepository;
use Modules\Development\Repository\DevelopmentProjectRepository;
use Modules\Development\Repository\DevelopmentProjectTaskRepository;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;

class DevelopmentProjectService {
    private $repo;

    private GeneralService $generalService;

    private DevelopmentProjectCacheService $cacheService;

    private EmployeeRepository $employeeRepo;

    private DevelopmentProjectTaskRepository $projectTaskRepo;

    private DevelopmentProjectBoardRepository $projectBoardRepo;

    private const MEDIAPATH = 'development/projects/references';

    /**
     * Construction Data
     */
    public function __construct(
        DevelopmentProjectRepository $repo,
        GeneralService $generalService,
        DevelopmentProjectCacheService $cacheService,
        EmployeeRepository $employeeRepo,
        DevelopmentProjectTaskRepository $projectTaskRepo,
        DevelopmentProjectBoardRepository $projectBoardRepo
    )
    {
        $this->repo = $repo;
        $this->generalService = $generalService;
        $this->cacheService = $cacheService;
        $this->employeeRepo = $employeeRepo;
        $this->projectTaskRepo = $projectTaskRepo;
        $this->projectBoardRepo = $projectBoardRepo;
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
            $itemsPerPage = request('itemsPerPage') ?? 50;
            $page = request('page') ?? 1;
            // $page = $page == 1 ? 0 : $page;
            // $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (!empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            // make filter as array
            $param = [];
            
            if (request('name')) {
                $param['name'] = request('name');
            }

            if (request('status')) {
                $param['status'] = request('status');
            }

            if (request('pics')) {
                $param['pics'] = request('pics');
            }

            if (request('start_date')) {
                $param['start_date'] = request('start_date');
            }

            if (request('end_date')) {
                $param['end_date'] = request('end_date');
            }

            $rawData = $this->cacheService->getFilteredProjects(filters: $param, page: $page, perPage: $itemsPerPage);

            $paginated = $rawData['data'] ?? [];
            $totalData = $rawData['total'] ?? 0;

            // $paginated = $this->repo->pagination(
            //     $select,
            //     $where,
            //     $relation,
            //     $itemsPerPage,
            //     $page
            // );
            // $totalData = $this->repo->list('id', $where)->count();

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
            select: 'id,uid,nickname,name,position_id',
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
                'tasks:id,name,development_project_id,development_project_board_id,description,status'
            ],
            where: "development_project_id = {$projectId}"
        );

        return $data->map(function ($board) {
            return [
                'id' => $board->id,
                'name' => $board->name,
                'tasks' => $board->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'description' => $task->description,
                        'status' => $task->status,
                    ];
                }),
            ];
        })->values();
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
                'pics.employee:id,name,position_id',
                'pics.employee.position:id,name',
                'references'
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
                    'total_task' => 0
                ];
            });

            // get project boards include with all task in each board
            $boards = $this->getProjectBoards(projectId: $data->id);

            $output = [
                'completeTaskPercentage' => $completeTaskPercentage,
                'uid' => $data->uid,
                'name' => $data->name,
                'description' => $data->description,
                'status_text' => $data->status->label(),
                'status_color' => $data->status->color(),
                'project_date' => $data->project_date_text,
                'pic_names' => $data->pics->pluck('employee.nickname')->implode(','),
                'teams' => $teams,
                'references' => $data->references,
                'boards' => $boards,
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
                foreach ($data['references'] as $reference) {
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
}