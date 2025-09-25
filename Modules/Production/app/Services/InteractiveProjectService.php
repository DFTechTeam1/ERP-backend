<?php

namespace Modules\Production\Services;

use App\Actions\Interactive\DefineTaskAction;
use App\Enums\Interactive\InteractiveTaskStatus;
use App\Enums\Production\ProjectStatus;
use App\Jobs\NotifyInteractiveTaskAssigneeJob;
use App\Services\GeneralService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Models\PositionBackup;
use Modules\Company\Repository\PositionRepository;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Jobs\InteractiveTaskHasBeenCompleteJob;
use Modules\Production\Jobs\SubmitInteractiveTaskJob;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Repository\InteractiveProjectBoardRepository;
use Modules\Production\Repository\InteractiveProjectRepository;
use Modules\Production\Repository\InteractiveProjectTaskDeadlineRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicHistoryRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicHoldstateRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicWorkstateRepository;
use Modules\Production\Repository\InteractiveProjectTaskRepository;

class InteractiveProjectService
{
    private InteractiveProjectRepository $repo;

    private GeneralService $generalService;

    private PositionRepository $positionRepository;

    private EmployeeRepository $employeeRepository;

    private InteractiveProjectTaskRepository $projectTaskRepo;

    private InteractiveProjectTaskPicRepository $projectTaskPicRepo;

    private InteractiveProjectTaskPicWorkstateRepository $projectTaskWorkStateRepo;

    private InteractiveProjectTaskDeadlineRepository $projectTaskDeadlineRepo;

    private InteractiveProjectTaskPicHistoryRepository $projectTaskPicHistoryRepo;

    private InteractiveProjectTaskPicHoldstateRepository $projectTaskHoldStateRepo;

    private InteractiveProjectBoardRepository $projectBoardRepo;

    private EmployeeRepository $employeeRepo;

    private const MEDIATASKPATH = 'interactives/projects/tasks';

    private const PROOFPATH = 'interactives/projects/tasks/proofs';

    private const REVISEPATH = 'interactives/projects/tasks/revises';

    private array $taskTmpProofFiles = [];

    /**
     * Construction Data
     */
    public function __construct(
        InteractiveProjectRepository $repo,
        GeneralService $generalService,
        PositionRepository $positionRepository,
        EmployeeRepository $employeeRepository,
        InteractiveProjectTaskRepository $projectTaskRepo,
        InteractiveProjectTaskPicRepository $projectTaskPicRepo,
        InteractiveProjectTaskPicWorkstateRepository $projectTaskWorkStateRepo,
        InteractiveProjectTaskDeadlineRepository $projectTaskDeadlineRepo,
        InteractiveProjectTaskPicHistoryRepository $projectTaskPicHistoryRepo,
        InteractiveProjectTaskPicHoldstateRepository $projectTaskHoldStateRepo,
        InteractiveProjectBoardRepository $projectBoardRepo,
        EmployeeRepository $employeeRepo
    ) {
        $this->repo = $repo;
        $this->generalService = $generalService;
        $this->positionRepository = $positionRepository;
        $this->employeeRepository = $employeeRepository;
        $this->projectTaskRepo = $projectTaskRepo;
        $this->projectTaskPicRepo = $projectTaskPicRepo;
        $this->projectTaskWorkStateRepo = $projectTaskWorkStateRepo;
        $this->projectTaskDeadlineRepo = $projectTaskDeadlineRepo;
        $this->projectTaskPicHistoryRepo = $projectTaskPicHistoryRepo;
        $this->projectTaskHoldStateRepo = $projectTaskHoldStateRepo;
        $this->projectBoardRepo = $projectBoardRepo;
        $this->employeeRepo = $employeeRepo;
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
            $itemsPerPage = request('itemsPerPage') ?? 2;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;
            $search = request('search');

            if (! empty($search)) {
                $where = "lower(name) LIKE '%{$search}%'";
            }

            $paginated = $this->repo->pagination(
                $select,
                $where,
                $relation,
                $itemsPerPage,
                $page
            );

            $paginated = $paginated->map(function ($item) {
                $item['project_date_text'] = date('d F Y', strtotime($item->project_date));
                $item['status_text'] = $item->status->label();
                $item['status_color'] = $item->status->color();
                $item['pic_name'] = '-';

                return $item;
            });

            $totalData = $this->repo->list('id', $where)->count();

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

    public function getProjectBoards(int $projectId): Collection
    {
        $data = $this->projectBoardRepo->list(
            select: 'id,name',
            relation: [
                'tasks:id,uid,name,intr_project_id,intr_project_board_id,description,status,deadline,created_at',
                'tasks.attachments:uid,intr_project_task_id,file_path,created_at',
                'tasks.pics:id,task_id,employee_id',
                'tasks.pics.employee:id,uid,nickname,avatar_color,name',
                'tasks.taskProofs:id,task_id,nas_path,created_at',
                'tasks.taskProofs.images:id,intr_task_proof_id,image_path',
                'tasks.revises.images',
            ],
            where: "project_id = {$projectId}"
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
                                'created_at' => Carbon::parse($proof->created_at)->format('d F Y H:i'),
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
                                'update_timing' => date('d F Y H:i', strtotime($attachment->created_at)),
                            ];
                        }),
                        'pics' => $task->pics->map(function ($pic) {
                            // set initial name based on name value
                            $initial = substr($pic->employee->name, 0, 1);

                            return [
                                'uid' => $pic->employee->uid,
                                'name' => $pic->employee->name,
                                'avatar_color' => $pic->employee->avatar_color,
                                'initial' => $initial,
                            ];
                        }),
                        'can_delete_attachment' => true,
                        'action_list' => DefineTaskAction::run($task),
                    ];
                }),
            ];
        })->values();
    }

    public function datatable()
    {
        //
    }

    /**
     * Get detail data
     */
    public function show(string $uid): array
    {
        try {
            $data = $this->repo->show($uid, '*', [
                'boards',
            ]);

            $output = [
                'uid' => $data->uid,
                'name' => $data->name,
                'description' => $data->note,
                'status_raw' => $data->status->value,
                'status_text' => $data->status->label(),
                'status_color' => $data->status->color(),
                'project_date' => date('d F Y', strtotime($data->project_date)),
                'led_detail' => $data->led_detail,
                'pic_names' => '-',
                'teams' => [],
                'references' => [],
                'boards' => $data->boards->map(function ($board) {
                    return [
                        'id' => $board->id,
                        'name' => $board->name,
                        'tasks' => [],
                    ];
                }),
                'project_is_complete' => $data->status === ProjectStatus::Completed ? true : false,
                'permission_list' => [
                    'add_task' => true,
                ],
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
     * Store data
     */
    public function store(array $data): array
    {
        try {
            $this->repo->store($data);

            return generalResponse(
                'success',
                false,
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
     * Delete bulk data
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
     * Get team list
     */
    public function getTeamList(): array
    {
        try {
            $teams = $this->generalService->getSettingByKey('interactive_team_positions');
            $teams = ! empty($teams) ? json_decode($teams, true) : [];

            // get posision id. Convert that uid to id
            $teams = collect($teams)->map(function ($team) {
                return $this->generalService->getIdFromUid($team, new PositionBackup);
            });

            $employees = $this->employeeRepository->list(
                select: 'id as value,name as text,email',
                where: 'position_id IN ('.implode(',', $teams->toArray()).')'
            );

            return generalResponse(
                'success',
                false,
                $employees->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Create task for development project task
     *
     * @param  array  $payload  With these following structure:
     *                          - string $name
     *                          - string $description
     *                          - int $board_id
     *                          - array $images                              With these following structure:
     *                          - File $image
     *                          - array $pics                                 With these following structure:
     *                          - string $employee_uid
     *                          - string $end_date
     */
    public function storeTask(array $payload, string $projectUid): array
    {
        DB::beginTransaction();

        $tmpFiles = [];
        try {
            $project = $this->repo->show(uid: $projectUid, select: 'id');
            $payload['status'] = (isset($payload['pics'])) && (! empty($payload['pics'])) ? InteractiveTaskStatus::Pending->value : InteractiveTaskStatus::Draft->value;

            $task = $project->tasks()->create([
                'intr_project_board_id' => $payload['board_id'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'],
                'deadline' => $payload['end_date'] ?? null,
            ]);

            // upload task attachments if any
            if (
                (isset($payload['images'])) &&
                (! empty($payload['images']))
            ) {
                foreach ($payload['images'] as $image) {
                    $media = $this->generalService->uploadImageandCompress(
                        path: self::MEDIATASKPATH,
                        compressValue: 0,
                        image: $image['image']
                    );

                    if (! $media) {
                        // return error
                        throw new \Exception(__('notification.errorUploadTaskImage'));
                    }

                    $tmpFiles[] = $media;
                }
            }

            if (! empty($tmpFiles)) {
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
                NotifyInteractiveTaskAssigneeJob::dispatch(
                    asignessIds: collect($payload['pics'])->pluck('employee_uid')->toArray(),
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
            if (! empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    if (Storage::disk('public')->exists(self::MEDIATASKPATH.'/'.$tmpFile)) {
                        Storage::disk('public')->delete(self::MEDIATASKPATH.'/'.$tmpFile);
                    }
                }
            }

            return errorResponse($th);
        }
    }

    /**
     * Assign PIC to task
     *
     * @param  int  $projectId
     */
    public function assignPicToTask(array $payload, string $taskUid, bool $useTransaction = true): array
    {
        if ($useTransaction) {
            DB::beginTransaction();
        }

        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, relation: [
                'deadlines',
            ]);

            // attach pics if payload contain 'pics' and payload['pics'] is not empty
            if (
                (isset($payload['pics'])) &&
                (! empty($payload['pics']))
            ) {
                foreach ($payload['pics'] as $pic) {
                    $picId = $this->generalService->getIdFromUid($pic['employee_uid'], new \Modules\Hrd\Models\Employee);

                    // assign to main table
                    // if pic_id and employee_id combination already exists, do not insert the record
                    $check = $this->projectTaskPicRepo->show(
                        uid: 'id',
                        select: 'id',
                        where: "task_id = {$task->id} AND employee_id = {$picId}"
                    );
                    if (! $check) {
                        $task->pics()->create([
                            'employee_id' => $picId,
                        ]);
                    }

                    // assign to pic histories table
                    $this->projectTaskPicHistoryRepo->upsert(
                        payload: [
                            ['task_id' => $task->id, 'employee_id' => $picId, 'is_until_finish' => true],
                        ],
                        uniqueBy: ['task_id', 'employee_id'],
                        updateValue: ['is_until_finish']
                    );

                    // if task already have a deadline and give picId is not associated with this task deadline, we need to add this pic to table development_project_task_deadlines
                    if ($task->deadline && $task->deadlines->where('employee_id', $picId)->where('actual_end_time', null)->isEmpty()) {
                        $this->projectTaskDeadlineRepo->store([
                            'employee_id' => $picId,
                            'deadline' => $task->deadline,
                            'task_id' => $task->id,
                            // if task status already InProgress, then start time should be Carbon::now()
                            'start_time' => $task->status === InteractiveTaskStatus::InProgress ? Carbon::now() : null,
                        ]);
                    }

                    // if task status is InProgress, insert new pic to workstate table
                    if ($task->status === InteractiveTaskStatus::InProgress) {
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
     * Assign member to a task.
     */
    public function addTaskMember(array $payload, string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $taskId = $this->generalService->getIdFromUid($taskUid, new InteractiveProjectTask);

            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,intr_project_id', relation: [
                'picHistories',
                'pics:id,task_id,employee_id',
            ]);

            $currentEmployeeIds = $task->pics->pluck('employee_id')->toArray();

            // remove pic if needed
            if (
                (isset($payload['removed'])) &&
                ($payload['removed'])
            ) {
                $removedIds = collect($payload['removed'])->map(function ($item) {
                    return $this->generalService->getIdFromUid($item, new Employee);
                })->toArray();

                if (! empty($removedIds)) {
                    $this->removeMembersFromTask(memberIds: $removedIds, taskId: $taskId);
                }
            }

            // add new pics
            $this->assignPicToTask(
                payload: [
                    'pics' => collect($payload['users'])->map(function ($user) {
                        return [
                            'employee_uid' => $user,
                        ];
                    })->toArray(),
                ],
                taskUid: $taskUid,
                useTransaction: false
            );

            // if task pics is empty and task status is InProgress, then change task status to draft
            $newTask = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,deadline,name', relation: [
                'pics',
            ]);
            if ($newTask->status === InteractiveTaskStatus::InProgress && $newTask->pics->isEmpty()) {
                $this->projectTaskRepo->update(
                    data: [
                        'status' => InteractiveTaskStatus::Draft->value,
                    ],
                    id: $taskUid
                );
            }

            // if current task status is Completed or Draft, and we have pics, change status to Waiting Approval
            if (
                ($newTask->status === InteractiveTaskStatus::Completed || $newTask->status === InteractiveTaskStatus::Draft) &&
                $newTask->pics->count() > 0
            ) {
                $this->projectTaskRepo->update(
                    data: [
                        'status' => InteractiveTaskStatus::WaitingApproval->value,
                    ],
                    id: $taskUid
                );
            }

            if (! empty($payload['users'])) {
                // only send notification for new users
                $newUsers = collect($payload['users'])->map(function ($user) {
                    return $this->generalService->getIdFromUid($user, new Employee);
                })->filter(function ($userId) use ($currentEmployeeIds) {
                    return ! in_array($userId, $currentEmployeeIds);
                })->toArray();

                // send notification
                NotifyInteractiveTaskAssigneeJob::dispatch(
                    asignessIds: $newUsers,
                    task: $newTask
                )->afterCommit();
            }

            // get project boards
            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

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
     * Remove current members from a task.
     */
    protected function removeMembersFromTask(array $memberIds, int $taskId): void
    {
        foreach ($memberIds as $memberId) {
            // Remove deadline history for selected member
            $this->projectTaskDeadlineRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId} and actual_end_time is null");

            // Remove from task pics
            $this->projectTaskPicRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId}");

            // remove from workstates
            $this->projectTaskWorkStateRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId} and finished_at is null");

            // remove from holdstates
            $this->projectTaskHoldStateRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId} and unholded_at is null");

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
     * Approve a task and update its deadlines.
     */
    public function approveTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,intr_project_id', relation: [
                'deadlines',
                'pics',
            ]);

            $this->projectTaskRepo->update(
                data: [
                    'status' => InteractiveTaskStatus::InProgress->value,
                ],
                id: $taskUid
            );

            // update start time in the deadlines table
            if ($task->deadlines->count() > 0) {
                foreach ($task->deadlines as $deadline) {
                    $this->projectTaskDeadlineRepo->update(
                        data: [
                            'start_time' => Carbon::now(),
                        ],
                        id: $deadline->id
                    );
                }
            }

            // record workstate
            $this->recordWorkState(task: $task);

            // should update boards
            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

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
     * Record the work state of a task.
     */
    public function recordWorkState(InteractiveProjectTask|Collection $task): void
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
     * Submit task proofs.
     *
     * @param  array  $payload  With these following structure:
     *                          - string $nas_path
     *                          - array $images                              With these following structure:
     *                          - File $image
     */
    public function submitTaskProofs(array $payload, string $taskUid): array
    {
        $tmpFiles = [];

        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'uid,id,status,intr_project_id,name', relation: [
                'pics:id,task_id,employee_id',
                'workStates',
                'deadlines:id,task_id',
                'interactiveProject:id,name',
            ]);

            $this->mainSubmitTask(payload: $payload, task: $task);

            // update boards
            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.taskSubmitted'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            if (! empty($this->taskTmpProofFiles)) {
                foreach ($this->taskTmpProofFiles as $file) {
                    if (Storage::disk('public')->exists(self::PROOFPATH.'/'.$file)) {
                        Storage::disk('public')->delete(self::PROOFPATH.'/'.$file);
                    }
                }
            }

            return errorResponse($th);
        }
    }

    /**
     * Main function to submit task proofs.
     *
     * @param  array  $payload  With these following structure:
     *                          - string $nas_path
     *                          - array $images                              With these following structure:
     *                          - File $image
     * @param  bool  $forceComplete  If true, the task will be marked as complete without assigning to the boss.
     *                               This is useful for admin or superadmin to directly complete the task.
     *
     * @throws \Exception
     */
    protected function mainSubmitTask(
        array $payload,
        InteractiveProjectTask|Collection $task,
        bool $forceComplete = false
    ): void {
        $user = Auth::user();

        // upload proof of works
        if ($forceComplete) {
            $this->taskTmpProofFiles = [$payload['images'][0]['image']];
        } else {
            foreach ($payload['images'] as $image) {
                $media = $this->generalService->uploadImageandCompress(
                    path: self::PROOFPATH,
                    compressValue: 0,
                    image: $image['image']
                );

                if (! $media) {
                    // return error
                    throw new \Exception(__('notification.errorUploadTaskImage'));
                }

                $this->taskTmpProofFiles[] = $media;
            }
        }

        $bossIds = [];
        foreach ($task->pics as $pic) {
            $proof = $task->taskProofs()->create([
                'nas_path' => $payload['nas_path'],
                'employee_id' => $pic->employee_id,
            ]);

            foreach ($this->taskTmpProofFiles as $file) {
                $proof->images()->create([
                    'image_path' => $file,
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
                'status' => $forceComplete ? InteractiveTaskStatus::Completed->value : InteractiveTaskStatus::CheckByPm->value,
                'current_pic_id' => $task->pics->pluck('employee_id')->implode(','),
            ],
            id: $task->uid
        );

        // remove all current pics
        $task->pics()->delete();

        // update finished_at in workstate stable
        foreach ($task->workStates as $state) {
            $this->projectTaskWorkStateRepo->update(
                data: [
                    'finished_at' => Carbon::now(),
                ],
                id: $state->id
            );
        }

        // update deadline actual_end_time if exists
        foreach ($task->deadlines as $deadline) {
            $this->projectTaskDeadlineRepo->update(
                data: [
                    'actual_end_time' => Carbon::now(),
                ],
                id: $deadline->id
            );
        }

        // assign to boss
        if (! $forceComplete) {
            foreach ($bossIds as $bossId) {
                $task->pics()->create([
                    'employee_id' => $bossId,
                ]);
            }

            SubmitInteractiveTaskJob::dispatch($task, $bossIds, $user)->afterCommit();
        }
    }

    /**
     * Complete a task
     */
    public function completeTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            // get task detail
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,intr_project_id,intr_project_board_id,current_pic_id', relation: [
                'pics:id,task_id,employee_id',
                'interactiveProject:id,name',
                'interactiveProject.boards',
            ]);

            $currentPicIds = explode(',', $task->current_pic_id);

            $payloadTask = [
                'status' => InteractiveTaskStatus::Completed,
            ];

            // move to the next board
            $currentBoards = $task->interactiveProject->boards->pluck('id')->toArray();
            $currentBoardKey = array_search($task->intr_project_board_id, $currentBoards);
            $nextKey = $currentBoardKey + 1;
            if (isset($currentBoards[$nextKey])) {
                $payloadTask['intr_project_board_id'] = $currentBoards[$nextKey];
            }

            // update task status
            $this->projectTaskRepo->update(
                data: $payloadTask,
                id: $taskUid
            );

            // TODO: Calculate duration of work

            // detach all pics from this task and put to pic histories table
            $task->pics()->delete();

            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

            InteractiveTaskHasBeenCompleteJob::dispatch($currentPicIds, $task)->afterCommit();

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
     * @param  array  $paylad  With these following structure
     *                         - array $images                          With these following structure
     *                         - File $image
     *                         - string $reason
     */
    public function reviseTask(array $payload, string $taskUid): array
    {
        $tmpFiles = [];
        DB::beginTransaction();
        try {
            // get detail task
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,intr_project_id,current_pic_id', relation: [
                'pics:id,task_id,employee_id',
            ]);

            foreach ($payload['images'] as $image) {
                $media = $this->generalService->uploadImageandCompress(
                    path: self::REVISEPATH,
                    compressValue: 0,
                    image: $image['image']
                );

                if (! $media) {
                    // return error
                    throw new \Exception(__('notification.errorUploadTaskImage'));
                }

                $tmpFiles[] = $media;
            }

            $revise = $task->revises()->create([
                'reason' => $payload['reason'],
                'assigned_by' => Auth::id(),
            ]);

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => InteractiveTaskStatus::Revise,
                ],
                id: $taskUid
            );

            if (! empty($tmpFiles)) {
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
                    'employee_id' => $currentPicId,
                ]);
            }

            // refresh boards
            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

            DB::commit();

            return generalResponse(
                message: __('notification.revisedHasBeenSubmitted'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            // delete tmp files
            if (! empty($tmpFiles)) {
                foreach ($tmpFiles as $tmpFile) {
                    if (Storage::disk('public')->exists(self::REVISEPATH.'/'.$tmpFile)) {
                        Storage::disk('public')->delete(self::REVISEPATH.'/'.$tmpFile);
                    }
                }
            }

            return errorResponse($th);
        }
    }
}
