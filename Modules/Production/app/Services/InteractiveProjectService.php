<?php

namespace Modules\Production\Services;

use App\Actions\Interactive\DefineTaskAction;
use App\Actions\Interactve\SummarizeTaskTimeline;
use App\Enums\Development\Project\ReferenceType;
use App\Enums\Interactive\InteractiveTaskStatus;
use App\Enums\Production\ProjectStatus;
use App\Enums\System\BaseRole;
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
use Modules\Production\Jobs\AssignInteractiveProjectPicJob;
use Modules\Production\Jobs\InteractiveProjectHasBeenCanceledJob;
use Modules\Production\Jobs\InteractiveTaskHasBeenCompleteJob;
use Modules\Production\Jobs\SubmitInteractiveTaskJob;
use Modules\Production\Jobs\UpdateInteractiveTaskDeadline;
use Modules\Production\Models\InteractiveProject;
use Modules\Production\Models\InteractiveProjectTask;
use Modules\Production\Repository\InteractiveProjectBoardRepository;
use Modules\Production\Repository\InteractiveProjectPicRepository;
use Modules\Production\Repository\InteractiveProjectReferenceRepository;
use Modules\Production\Repository\InteractiveProjectRepository;
use Modules\Production\Repository\InteractiveProjectTaskApprovalStateRepository;
use Modules\Production\Repository\InteractiveProjectTaskAttachmentRepository;
use Modules\Production\Repository\InteractiveProjectTaskDeadlineRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicHistoryRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicHoldstateRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicRepository;
use Modules\Production\Repository\InteractiveProjectTaskPicWorkstateRepository;
use Modules\Production\Repository\InteractiveProjectTaskRepository;
use Modules\Production\Repository\InteractiveProjectTaskRevisestateRepository;
use Modules\Production\Repository\ProjectTaskDurationHistoryRepository;

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

    private InteractiveProjectTaskAttachmentRepository $projectTaskAttachmentRepo;

    private InteractiveProjectReferenceRepository $projectReferenceRepo;

    private InteractiveProjectPicRepository $projectPicRepo;

    private InteractiveProjectTaskRevisestateRepository $projectTaskRevisestateRepo;

    private InteractiveProjectTaskApprovalStateRepository $projectTaskApprovalStateRepo;

    private ProjectTaskDurationHistoryRepository $projectTaskDurationHistoryRepo;

    private const MEDIAPATH = 'interactives/projects/references';

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
        EmployeeRepository $employeeRepo,
        InteractiveProjectTaskAttachmentRepository $projectTaskAttachmentRepo,
        InteractiveProjectReferenceRepository $projectReferenceRepo,
        InteractiveProjectPicRepository $projectPicRepo,
        InteractiveProjectTaskRevisestateRepository $projectTaskRevisestateRepo,
        InteractiveProjectTaskApprovalStateRepository $projectTaskApprovalStateRepo,
        ProjectTaskDurationHistoryRepository $projectTaskDurationHistoryRepo,
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
        $this->projectTaskAttachmentRepo = $projectTaskAttachmentRepo;
        $this->projectReferenceRepo = $projectReferenceRepo;
        $this->projectPicRepo = $projectPicRepo;
        $this->projectTaskRevisestateRepo = $projectTaskRevisestateRepo;
        $this->projectTaskApprovalStateRepo = $projectTaskApprovalStateRepo;
        $this->projectTaskDurationHistoryRepo = $projectTaskDurationHistoryRepo;
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
            $itemsPerPage = request('itemsPerPage') ?? 50;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            if (request('status')) {
                $status = request('status');

                if (empty($where)) {
                    $where = 'status IN ('.implode(',', $status).')';
                } else {
                    $where .= ' AND status IN ('.implode(',', $status).')';
                }
            }

            if (request('name')) {
                $name = request('name');
                if (empty($where)) {
                    $where = "name LIKE '%{$name}%'";
                } else {
                    $where .= " AND name LIKE '%{$name}%'";
                }
            }

            if (request('date')) {
                $date = request('date');
                [$startDate, $endDate] = explode(' - ', $date);

                if (empty($where)) {
                    $where = "project_date BETWEEN '{$startDate}' AND '{$endDate}'";
                } else {
                    $where .= " AND project_date BETWEEN '{$startDate}' AND '{$endDate}'";
                }
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
                $item['pic_name'] = $item->pics->isEmpty() ? '-' : $item->pics->pluck('employee.nickname')->implode(',');
                $item['actions'] = [
                    'can_assign_pic' => $item->pics->isEmpty() ? true : false,
                    'can_substitute_pic' => $item->pics->isEmpty() ? false : true,
                ];

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

    /**
     * Get tasks for a specific project board.
     */
    public function getProjectBoardTasks(int $boardId): Collection
    {
        $tasks = $this->projectTaskRepo->list(
            select: 'id,uid,name,intr_project_id,intr_project_board_id,description,status,deadline,created_at',
            where: "intr_project_board_id = {$boardId}",
            relation: [
                'attachments:uid,intr_project_task_id,file_path,created_at',
                'pics:id,task_id,employee_id',
                'pics.employee:id,uid,nickname,avatar_color,name',
                'taskProofs:id,task_id,nas_path,created_at',
                'taskProofs.images:id,intr_task_proof_id,image_path',
                'revises.images',
            ]
        );

        return $tasks->map(function ($task) {
            return [
                'uid' => $task->uid,
                'name' => $task->name,
                'description' => $task->description,
                'start_date' => date('d F Y H:i', strtotime($task->created_at)),
                'end_date' => $task->deadline ? date('d M Y, H:i', strtotime($task->deadline)) : null,
                'status' => $task->status,
                'status_text' => $task->status->label(),
                'status_color' => $task->status->color(),
                'board_id' => $task->intr_project_board_id,
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
                'can_add_description' => true,
                'can_edit_description' => true,
                'action_list' => DefineTaskAction::run($task),
            ];
        });
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
                'tasks' => $this->getProjectBoardTasks($board->id),
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
                'pics:id,intr_project_id,employee_id',
                'pics.employee:id,nickname',
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
                'pic_names' => $data->pics->isNotEmpty() ? $data->pics->pluck('employee.nickname')->implode(', ') : '-',
                'is_have_pic' => $data->pics->isNotEmpty() ? true : false,
                'teams' => $this->getTeamList()['data'],
                'references' => $this->getProjectReferences($data->id),
                'boards' => $data->boards->map(function ($board) {
                    return [
                        'id' => $board->id,
                        'name' => $board->name,
                        'tasks' => $this->getProjectBoardTasks($board->id),
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
            $definedPosition = json_decode($this->generalService->getSettingByKey('position_in_interactive_task'), true);

            $output = [];

            // get position id. Convert that uid to id
            if ($definedPosition) {
                $teams = collect($definedPosition)->map(function ($team) {
                    return $this->generalService->getIdFromUid($team, new PositionBackup);
                });

                $output = $this->employeeRepository->list(
                    select: 'uid,name,email,position_id',
                    where: 'position_id IN ('.implode(',', $teams->toArray()).')',
                    relation: [
                        'position:id,name',
                    ]
                );

                $output = $output->map(function ($item) {
                    $item['total_task'] = 0;

                    return $item;
                })->toArray();
            }

            return generalResponse(
                'success',
                false,
                $output
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
            $payload['status'] = (isset($payload['pics'])) && (! empty($payload['pics'])) ? InteractiveTaskStatus::WaitingApproval->value : InteractiveTaskStatus::Draft->value;

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

            // refresh the board
            $boards = $this->getProjectBoards(projectId: $project->id);

            DB::commit();

            return generalResponse(
                message: __('notification.successCreateTask'),
                data: [
                    'boards' => $boards,
                ]
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
     * Expected speps will implement in this function
     * 1. Check 'pics' in payload. Pics will have structure like this: [pics => [['employee_uid' => 'uid1'], ['employee_uid' => 'uid2']]]
     * 2. Looping pics and get employee id from employee uid
     * 3. Insert to table development_project_task_pics if combination of task_id and employee_id not exists
     * 4. Insert to table development_project_task_pic_histories if combination of task_id and employee_id not exists
     * 5. If task already have a deadline and give picId is not associated with this task deadline, we need to add this pic to table development_project_task_deadlines
     * 6. If task status is InProgress, insert new pic to workstate table
     * 7. Return success message
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
            $this->projectTaskWorkStateRepo->delete(id: 0, where: "employee_id = {$memberId} and task_id = {$taskId} and first_finish_at is null");

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
     * Expected steps:
     * 1. Check if the task is already approved. If so, return an error message.
     * 2. Check if the user has the necessary permissions to approve the task.
     *    Only users with root role, director role, project PIC for the task's project,
     *    or PIC for the task itself can approve the task.
     * 3. If the task has no PICs assigned, return an error message.
     * 4. Update the task status to "In Progress".
     * 5. Update the start time for all deadlines associated with the task.
     * 6. Record the work state for each PIC assigned to the task.
     * 7. Retrieve and return the updated project boards for the task's project.
     * 8. Handle any exceptions that may occur during the process and roll back the transaction if necessary.
     */
    public function approveTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $user = \App\Models\User::select('id', 'email')->find($user->id);

            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,intr_project_id', relation: [
                'deadlines',
                'pics',
                'interactiveProject:id',
                'interactiveProject.pics',
            ]);

            $isMyTask = in_array($user->employee_id, $task->pics->pluck('employee_id')->toArray()) ? true : false;

            $isMyProject = in_array($user->employee_id, $task->interactiveProject->pics->pluck('employee_id')->toArray()) ? true : false;

            // return with proper message if task already approved
            if ($task->status == InteractiveTaskStatus::InProgress) {
                return errorResponse(
                    message: __('notification.taskAlreadyApproved'),
                );
            }

            $disallowedStatusesToChange = [
                InteractiveTaskStatus::Completed,
                InteractiveTaskStatus::OnHold,
                InteractiveTaskStatus::CheckByPm,
            ];

            if (in_array($task->status, $disallowedStatusesToChange)) {
                return errorResponse(
                    message: __('notification.taskCannotBeApproved'),
                );
            }

            // only user with root role, director role,
            // project pic for task project, pic for the task can approved this task
            $doNotHavePic = $task->pics->isEmpty() ? true : false;
            if (
                ! $isMyTask &&
                (
                    ! $user->hasRole(BaseRole::Root->value) &&
                    ! $user->hasRole(BaseRole::Director->value)
                ) &&
                ! $isMyProject
            ) {
                return errorResponse(
                    message: __('notification.youAreNotAllowedToApproveThisTask'),
                );
            }

            if ($doNotHavePic) {
                return errorResponse(
                    message: __('notification.cannotApproveTaskWithoutPic'),
                );
            }

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
     * This function will create record in the intr_project_task_pic_workstates table
     */
    public function recordWorkState(InteractiveProjectTask|Collection $task): void
    {
        foreach ($task->pics as $pic) {
            $this->projectTaskWorkStateRepo->store([
                'started_at' => Carbon::now(),
                'first_finish_at' => null,
                'complete_at' => null,
                'task_id' => $task->id,
                'employee_id' => $pic->employee_id,
            ]);
        }
    }

    /**
     * Record approval state for Project Manager
     * Expected steps:
     * 1. Looping all project PICs or Project Managers
     * 2. Get current workstate for the task
     * 3. Insert record to intr_task_approval_states table
     */
    protected function recordApprovalState(InteractiveProjectTask|Collection $task): void
    {
        logging('RECORD APPROVAL STATE', [
            'task' => $task->toArray(),
            'project' => $task->interactiveProject->toArray(),
        ]);
        foreach ($task->interactiveProject->pics as $pic) {
            $currentWorkState = $this->projectTaskWorkStateRepo->show(
                uid: 'uid',
                select: 'id',
                where: "task_id = {$task->id} AND complete_at IS NULL"
            );
            $this->projectTaskApprovalStateRepo->store(
                data: [
                    'pic_id' => $pic->employee_id,
                    'task_id' => $task->id,
                    'project_id' => $task->intr_project_id,
                    'started_at' => Carbon::now(),
                    'work_state_id' => $currentWorkState->id,
                ]
            );
        }
    }

    /**
     * Mark current approval task state as complete
     */
    protected function recordApprovalAsFinish(InteractiveProjectTask|Collection $task, ?string $completeTime = null): void
    {
        $this->projectTaskApprovalStateRepo->update(
            data: [
                'approved_at' => $completeTime ? Carbon::parse($completeTime) : Carbon::now(),
            ],
            id: 'id',
            where: "task_id = {$task->id} AND approved_at IS NULL"
        );
    }

    /**
     * Submit task proofs.
     * Expected steps:
     * 1. Upload proof of works to storage.
     * 2. Insert record to intr_task_proofs and intr_task_proof_images tables
     * 3. Update task status to CheckByPm and assign to project PICs
     * 4. Update first_finish_at in workstates table when first_finish_at is null
     * 5. If there is revise state, update the finish_at column
     * 6. Return the updated project boards for the task's project.
     * 7. Handle any exceptions that may occur during the process and roll back the transaction
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
            $task = $this->projectTaskRepo->show(
                uid: $taskUid,
                select: 'uid,id,status,intr_project_id,name',
                relation: [
                    'pics:id,task_id,employee_id',
                    'workStates',
                    'deadlines:id,task_id',
                    'interactiveProject:id,name',
                    'interactiveProject.pics:id,intr_project_id,employee_id',
                ],
            );

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
     * Expected steps:
     * 1. Upload proof of works to storage.
     * 2. Insert record to intr_task_proofs and intr_task_proof_images tables
     * 3. Update task status to CheckByPm and assign to project PICs
     * 4. Update first_finish_at in workstates table when first_finish_at is null
     * 5. If there is revise state, update the finish_at column
     * 6. Record approval state for project managers
     * 7. Return the updated project boards for the task's project.
     * 8. Handle any exceptions that may occur during the process and roll back the transaction
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

        // update first_finish_at in workstates table when first_finish_at is null
        foreach ($task->workStates as $state) {
            $this->projectTaskWorkStateRepo->update(
                data: [
                    'first_finish_at' => Carbon::now(),
                ],
                where: "id = {$state->id} AND complete_at IS NULL AND first_finish_at IS NULL"
            );

            // check revise state, if exists the update the finish_at column
            $this->projectTaskRevisestateRepo->update(
                data: [
                    'finish_at' => Carbon::now(),
                ],
                where: "work_state_id = {$state->id} AND assign_at IS NOT NULL AND start_at IS NOT NULL and finish_at IS NULL"
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

        // record approval state
        $this->recordApprovalState(task: $task);
    }

    /**
     * Change project status
     *
     * @param  array  $payload  With these following structure:
     *                          - int $status
     */
    public function changeStatus(array $payload, string $interactiveUid): array
    {
        try {
            $this->repo->update(
                data: [
                    'status' => $payload['status'],
                ],
                id: $interactiveUid
            );

            return generalResponse(
                message: __('notification.successChangeProjectStatus'),
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
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

            // TODO: Complete workstate in each pic
            $completeTime = '2025-10-10 16:02:10';
            $this->projectTaskWorkStateRepo->update(
                data: [
                    'complete_at' => Carbon::parse($completeTime),
                ],
                where: "task_id = {$task->id} AND employee_id IN ({$task->current_pic_id}) AND complete_at IS NULL"
            );

            // mark current approval state as complete
            $this->recordApprovalAsFinish(task: $task, completeTime: $completeTime);

            // Summarize task timeline
            SummarizeTaskTimeline::run($taskUid);

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

            // mark current approval state to complete
            $this->recordApprovalAsFinish(task: $task);

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

                // get active workstate
                $activeWorkstate = $this->projectTaskWorkStateRepo->show(
                    uid: 'id',
                    select: 'id',
                    where: "complete_at is null and task_id = {$task->id} and employee_id = {$currentPicId}"
                );

                // record revise state
                if ($activeWorkstate) {
                    $this->projectTaskRevisestateRepo->store(data: [
                        'task_id' => $task->id,
                        'work_state_id' => $activeWorkstate->id,
                        'employee_id' => $currentPicId,
                        'assign_at' => Carbon::now(),
                        'start_at' => Carbon::now(),
                        'finish_at' => null,
                    ]);
                }
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

    /**
     * Delete a task and all its related resources.
     *
     * @return array<string, mixed>
     */
    public function deleteTask(string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, relation: [
                'pics',
                'attachments',
                'deadlines',
            ]);

            $currentProjectId = $task->intr_project_id;

            // delete all pics
            $task->pics()->delete();

            $task->picHistories()->delete();

            // delete all attachment in the storage first
            $task->attachments->each(function ($attachment) {
                if (Storage::disk('public')->exists(self::MEDIATASKPATH.'/'.$attachment->file_path)) {
                    Storage::delete(self::MEDIATASKPATH.'/'.$attachment->file_path);
                }
            });

            // delete all attachments
            $task->attachments()->delete();

            // delete all deadlines
            $task->deadlines()->delete();

            // delete durations
            $this->projectTaskDurationHistoryRepo->delete(id: 0, where: "task_id = {$task->id}");

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

    public function downloadAttachment(string $taskId, string $attachmentId)
    {
        try {
            $data = $this->projectTaskAttachmentRepo->show(uid: 'dummy', select: 'id,file_path', where: "uid = '{$attachmentId}'");

            return \Illuminate\Support\Facades\Storage::download('interactives/projects/tasks/'.$data->file_path);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Delete selected attachments
     */
    public function deleteTaskAttachment(string $interactiveUid, string $taskUid, string $imageId): array
    {
        try {
            $image = $this->projectTaskAttachmentRepo->show(uid: $imageId);

            if (Storage::disk('public')->exists(self::MEDIATASKPATH.'/'.$image->file_path)) {
                Storage::disk('public')->delete(self::MEDIATASKPATH.'/'.$image->file_path);
            }

            $image->delete();

            // get detail of project board
            $projectId = $this->generalService->getIdFromUid($interactiveUid, new InteractiveProject);
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
     * Update the deadline for a specific task.
     *
     * @param  array  $payload  Required structure:
     *                          - end_date: string (format: Y-m-d H:i:s)
     */
    public function updateTaskDeadline(array $payload, string $taskUid): array
    {
        DB::beginTransaction();
        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,intr_project_id,name', relation: [
                'deadlines',
                'pics:id,task_id,employee_id',
                'pics.employee:id,name,email,telegram_chat_id',
                'interactiveProject:id,name',
            ]);
            // Update deadlines for each PIC if actual_end_time is null
            // task have pics, and actual_end_time in deadline model have null value then update the value based on task id and each pic.employee_id
            if ($task->pics->isNotEmpty()) {
                foreach ($task->pics as $pic) {
                    $targetDeadline = $this->projectTaskDeadlineRepo->show(uid: 'id', select: 'id', where: "task_id = {$task->id} and employee_id = {$pic->employee_id} and actual_end_time IS NULL");

                    // create if not exists and update if exists
                    if ($targetDeadline) {
                        $this->projectTaskDeadlineRepo->update(
                            data: [
                                'deadline' => Carbon::parse($payload['end_date'])->format('Y-m-d H:i:s'),
                            ],
                            id: $targetDeadline->id
                        );
                    } else {
                        $this->projectTaskDeadlineRepo->store([
                            'task_id' => $task->id,
                            'employee_id' => $pic->employee_id,
                            'deadline' => Carbon::parse($payload['end_date'])->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            // update deadline in the task repo
            $this->projectTaskRepo->update(
                data: [
                    'deadline' => Carbon::parse($payload['end_date'])->format('Y-m-d H:i:s'),
                ],
                id: $taskUid
            );

            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

            // notify all pics if exists
            if ($task->pics->isNotEmpty()) {
                UpdateInteractiveTaskDeadline::dispatch($task, $payload)->afterCommit();
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successUpdateDeadline'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Hold a task and update its hold states.
     * Expected steps:
     * 1. Begin a database transaction.
     * 2. Retrieve the task by its UID.
     * 3. Update the task's status to "On Hold".
     * 4. Record the hold state for each PIC assigned to the task.
     * 5. Commit the transaction.
     * 6. Return a success response with the updated project boards.
     */
    public function holdTask(array $payload, string $taskUid): array
    {
        DB::beginTransaction();

        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,intr_project_id', relation: [
                'pics:id,task_id,employee_id',
            ]);

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => InteractiveTaskStatus::OnHold->value,
                ],
                id: $taskUid
            );

            // record for hold states
            $this->recordHoldState(task: $task, payload: $payload);

            // refresh boards
            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

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
     * Record the hold state of a task.
     */
    public function recordHoldState(InteractiveProjectTask|Collection $task, array $payload): void
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
                'holded_at' => Carbon::now(),
                'reason' => $payload['reason'],
            ]);
        }
    }

    /**
     * Start the task after tas has been hold
     * Expected steps:
     * 1. Begin a database transaction.
     * 2. Retrieve the task by its UID.
     * 3. Update the task's status to "In Progress".
     * 4. End the hold state for each PIC assigned to the task.
     * 5. Commit the transaction.
     * 6. Return a success response with the updated project boards.
     */
    public function startTaskAfterHold(string $taskUid): array
    {
        DB::beginTransaction();

        try {
            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,status,intr_project_id', relation: [
                'pics:id,task_id,employee_id',
            ]);

            // update task status
            $this->projectTaskRepo->update(
                data: [
                    'status' => InteractiveTaskStatus::InProgress->value,
                ],
                id: $taskUid
            );

            // end the hold state
            $this->stopHoldState(task: $task);

            // refresh the boards
            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

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

    public function stopHoldState(InteractiveProjectTask|Collection $task): void
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
                    'unholded_at' => Carbon::now(),
                ],
                where: "work_state_id = {$currentWorkState->id}"
            );
        }
    }

    /**
     * Store project references.
     *
     * @param  array  $payload  With these following structure:
     *                          - array $references                          With these following structure:
     *                          - File $file
     *                          - string $type
     *                          - string $description
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

    protected function getProjectReferences(int $projectId)
    {
        $data = $this->projectReferenceRepo->list(
            select: 'id,uid,type,media_path,link,link_name',
            where: "project_id = {$projectId}"
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
                $extension = pathinfo(storage_path('app/public/'.$reference->full_path), PATHINFO_EXTENSION);

                if (in_array($extension, ['docx', 'doc', 'pdf'])) {
                    $type = 'pdf';
                }
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $type = 'files';
                }
            }

            return [
                'uid' => $reference->uid,
                'type' => $type,
                'extension' => $extension,
                'media_path' => $reference->real_media_path,
                'link' => $reference->link,
                'link_name' => $reference->link_name,
                'image_name' => $reference->media_path,
            ];
        });

        // group by types
        $groups = $data->groupBy('type');

        return $groups;
    }

    protected function uploadProjectReferences(Collection|InteractiveProject $project, array $references): void
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
            } elseif ($reference['type'] === ReferenceType::Link->value) {
                $payloadReferences[count($payloadReferences) - 1]['link'] = $reference['link'];
                $payloadReferences[count($payloadReferences) - 1]['link_name'] = $reference['link_name'];
            } elseif ($reference['type'] === ReferenceType::Document->value) {
                // upload document
                $document = $this->generalService->uploadFile(path: self::MEDIAPATH, file: $reference['image']);
                $payloadReferences[count($payloadReferences) - 1]['media_path'] = $document;
            }
        }

        $project->references()->createMany($payloadReferences);
    }

    /**
     * Delete a project reference.
     *
     * @param  string  $taskUid
     */
    public function deleteReference(string $projectUid, string $referenceId): array
    {
        try {
            $project = $this->repo->show(uid: $projectUid, select: 'id');

            $reference = $this->projectReferenceRepo->show(uid: $referenceId, select: 'id,media_path,type');

            if (Storage::disk('public')->exists(self::MEDIAPATH.'/'.$reference->media_path)) {
                Storage::disk('public')->delete(self::MEDIAPATH.'/'.$reference->media_path);
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
     * Store or update task description.
     *
     * @param  array  $payload  With these following structure:
     *                          - string $description
     * @return array
     */
    public function storeDescription(array $payload, string $taskUid)
    {
        try {
            $this->projectTaskRepo->update(
                data: [
                    'description' => $payload['description'],
                ],
                id: $taskUid
            );

            $task = $this->projectTaskRepo->show(uid: $taskUid, select: 'id,intr_project_id');

            $boards = $this->getProjectBoards(projectId: $task->intr_project_id);

            return generalResponse(
                message: __('notification.successUpdateDescription'),
                data: $boards->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    public function mainAssignPicProject(InteractiveProject|Collection $project, array $payload)
    {
        $employeeIds = collect($payload['pics'])->map(function ($item) use ($project) {
            return [
                'employee_id' => $this->generalService->getIdFromUid($item['employee_uid'], new Employee),
                'intr_project_id' => $project->id,
            ];
        })->toArray();

        // upsert data, update or create if not exists
        $this->projectPicRepo->upsert(
            payload: $employeeIds,
            uniqueBy: ['employee_id', 'intr_project_id'],
            updateValue: ['employee_id'],
        );

        AssignInteractiveProjectPicJob::dispatch($project, $employeeIds)->afterCommit();
    }

    /**
     * Assign PIC to a project.
     *
     * @param  array  $payload  With these following structure:
     *                          - array $pics                          With these following structure:
     *                          - string $employee_uid
     * @return array
     */
    public function assignPicToProject(array $payload, string $interactiveUid)
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->show(uid: $interactiveUid, select: 'id,name,project_date', relation: [
                'pics',
            ]);

            $this->mainAssignPicProject(project: $project, payload: $payload);

            DB::commit();

            return generalResponse(
                message: __('notification.successAssignPicToProject'),
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    /**
     * Subtitute PIC in a project. Remove and assign pic
     *
     * @param  array<mixed>  $payload  With these following structure:
     *                                 - array $pics                          With these following structure:
     *                                 - string $employee_uid
     *                                 - array $remove                        With these following structure:
     *                                 - string $employee_uid
     * @return array<mixed>
     */
    public function substitutePicInProject(array $payload, string $interactiveUid)
    {
        DB::beginTransaction();
        try {
            $project = $this->repo->show(uid: $interactiveUid, select: 'id,name,project_date', relation: [
                'pics',
            ]);

            if (isset($payload['remove'])) {
                $removeIds = collect($payload['remove'])->map(function ($removeItem) {
                    return $this->generalService->getIdFromUid($removeItem['employee_uid'], new Employee);
                })->implode(',');

                $this->projectPicRepo->delete(id: 0, where: 'employee_id IN ('.$removeIds.") and intr_project_id = {$project->id}");
            }

            if (isset($payload['pics'])) {
                $this->mainAssignPicProject(project: $project, payload: $payload);
            }

            DB::commit();

            return generalResponse(
                message: __('notification.successSubtitutePicToProject'),
                data: []
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }

    public function getPicScheduler(string $interactiveUid)
    {
        try {
            $project = $this->repo->show(uid: $interactiveUid, select: 'id,name,project_date,parent_project', relation: [
                'parentProject:id,name,project_date',
            ]);
            $startDate = date('Y-m-d', strtotime('-7 days', strtotime($project->parentProject->project_date)));
            $endDate = date('Y-m-d', strtotime('+7 days', strtotime($project->parentProject->project_date)));

            $pics = $this->generalService->mainProcessToGetPicScheduler($project->parent_project, $startDate, $endDate);

            return generalResponse(
                message: 'Success',
                data: $pics
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Cancel a interactive project
     */
    public function cancelProject(string $interactiveUid): array
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $this->repo->update(
                data: [
                    'status' => ProjectStatus::Canceled->value,
                    'canceled_by' => $user->id,
                ],
                id: $interactiveUid
            );

            InteractiveProjectHasBeenCanceledJob::dispatch(user: $user, interactiveUid: $interactiveUid)->afterCommit();

            DB::commit();

            return generalResponse(
                message: __('notification.projectHasBeenCanceled'),
            );
        } catch (\Throwable $th) {
            DB::rollBack();

            return errorResponse($th);
        }
    }
}
