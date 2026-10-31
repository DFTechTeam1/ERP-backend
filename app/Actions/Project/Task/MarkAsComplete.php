<?php

namespace App\Actions\Project\Task;

use App\Actions\PartialTaskPermissionCheck;
use App\Actions\Production\SummarizeTaskTimeline;
use App\Actions\Project\DetailCache;
use App\Actions\Project\SaveTaskState;
use App\Enums\Production\TaskStatus;
use App\Enums\Production\WorkType;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Modules\Hrd\Repository\EmployeeRepository;
use Modules\Production\Jobs\TaskIsCompleteJob;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Repository\ProjectBoardRepository;
use Modules\Production\Repository\ProjectTaskPicApprovalstateRepository;
use Modules\Production\Repository\ProjectTaskPicLogRepository;
use Modules\Production\Repository\ProjectTaskPicWorkstateRepository;
use Modules\Production\Repository\ProjectTaskRepository;

/**
 * Complete a production task and advance the board pipeline.
 *
 * This action finalises a task that a PIC has finished working on. It has two distinct
 * branches, chosen by the task's exclude_from_point_calculation flag:
 *
 * 1. Excluded task (exclude_from_point_calculation = true) - see completeExcludedTask():
 *    the task carries no production point, so there is nothing to summarise or score.
 *    Its status is simply reset to null, it is returned to the pool (is_pool_task = true)
 *    and its current PIC(s) are detached. No board move, timeline summary, task-state
 *    snapshot or completion notification happens.
 *
 * 2. Normal task - the full completion pipeline:
 *    - updateWorkingTime(): stamp a WorkType::Finish log row for every current PIC.
 *    - changeTaskBoard(): move the task to the next board in the project (CheckByPm).
 *    - DetachTaskPic(): remove the PIC assignment rows (work state and current_pics are
 *      preserved here because the completion itself closes them below).
 *    - mark the task Completed, clear its deadline and return it to the pool.
 *    - close the open work state(s) and approval state(s) for the current PIC(s).
 *    - SummarizeTaskTimeline(): persist a per-PIC duration summary for the project.
 *    - SaveTaskState(): snapshot the employee task state.
 *    - dispatch TaskIsCompleteJob (unless notifications are suppressed).
 *
 * Both branches finish by force-rebuilding the project detail cache and returning the
 * freshly formatted task together with that cache payload.
 *
 * Invoked through the AsAction trait (MarkAsComplete::run(...)), wrapped in a DB
 * transaction by ProjectService::markAsCompleted().
 */
class MarkAsComplete
{
    use AsAction;

    private readonly ProjectTaskRepository $taskRepo;

    private readonly ProjectTaskPicLogRepository $taskPicLogRepo;

    private readonly ProjectBoardRepository $boardRepo;

    private readonly ProjectTaskPicWorkstateRepository $projectTaskWorkStateRepo;

    private readonly ProjectTaskPicApprovalstateRepository $projectTaskPicApprovalstateRepo;

    private readonly EmployeeRepository $employeeRepo;

    /**
     * Resolve every repository this action depends on from the container.
     *
     * Called at the top of handle() because the AsAction instance is built by the
     * container without constructor arguments.
     */
    protected function registerService(): void
    {
        $this->taskRepo = app(ProjectTaskRepository::class);
        $this->taskPicLogRepo = app(ProjectTaskPicLogRepository::class);
        $this->boardRepo = app(ProjectBoardRepository::class);
        $this->projectTaskWorkStateRepo = app(ProjectTaskPicWorkstateRepository::class);
        $this->projectTaskPicApprovalstateRepo = app(ProjectTaskPicApprovalstateRepository::class);
        $this->employeeRepo = app(EmployeeRepository::class);
    }

    /**
     * Load the task being completed with the data every branch needs.
     *
     * Eager-loads the current PIC rows and each PIC's employee (uid is required to detach
     * them) and selects exclude_from_point_calculation so handle() can pick its branch.
     *
     * @param  string  $taskUid  The task uid to complete.
     */
    protected function getTask(string $taskUid): ProjectTask
    {
        return $this->taskRepo->show(
            uid: $taskUid,
            select: 'id,uid,project_board_id,exclude_from_point_calculation,project_id,current_pics,current_board,project_board_id,is_pool_type',
            relation: [
                'pics:id,project_task_id,employee_id',
                'pics.employee:id,uid',
            ]
        );
    }

    /**
     * Stamp a "finish" working-time log row for every current PIC of the task.
     *
     * One ProjectTaskPicLog row per PIC with WorkType::Finish and the current timestamp,
     * written in a single bulk insert.
     *
     * @param  ProjectTask  $task  Task with its `pics` relation loaded.
     */
    protected function updateWorkingTime(ProjectTask $task): void
    {
        $payload = [];
        foreach ($task->pics as $pic) {
            $payload[] = [
                'project_task_id' => $task->id,
                'employee_id' => $pic->employee_id,
                'work_type' => WorkType::Finish->value,
                'time_added' => Carbon::now(),
            ];
        }

        $this->taskPicLogRepo->insert($payload);
    }

    /**
     * Resolve the board a completed task should move to.
     *
     * Walks the project's boards in list order and returns the one after the source board.
     * When the source board is already the last board the task stays on it. Returns null
     * when the source board is not found in the project.
     *
     * @param  mixed  $sourceBoardId  The board the task currently sits on.
     * @param  int  $projectId  The owning project id.
     * @return int|null The next board id, the same board id when last, or null if unmatched.
     */
    protected function getNextBoardId(mixed $sourceBoardId, int $projectId): ?int
    {
        $boardId = null;
        $boardList = $this->boardRepo->list('id,name', 'project_id = '.$projectId);
        foreach ($boardList as $keyBoard => $boardData) {
            if ($boardData->id == $sourceBoardId) {
                if (isset($boardList[$keyBoard + 1])) {
                    $boardId = (int) $boardList[$keyBoard + 1]->id;
                    break;
                } else {
                    $boardId = (int) $sourceBoardId;
                    break;
                }
            }
        }

        return $boardId;
    }

    /**
     * Move the task onto the next board and flag it for PM review.
     *
     * Delegates to the ChangeTaskBoard action with the resolved next board, setting the
     * status to CheckByPm and refreshing current_pics from the task's PIC rows.
     *
     * @param  ProjectTask  $task  The task being completed.
     */
    protected function changeTaskBoard(ProjectTask $task): void
    {
        $nextBoardId = $this->getNextBoardId($task->project_board_id, $task->project_id);
        $payload = [
            'board_id' => $nextBoardId,
            'task_id' => $task->id,
            'board_source_id' => $task->project_board_id,
        ];

        ChangeTaskBoard::run($payload, TaskStatus::CheckByPm->value, true);
    }

    /**
     * Complete a task that is excluded from point calculation.
     *
     * Such a task earns no production point, so completion is reduced to returning it to
     * the pool (status null, is_pool_task = true) and detaching its current PIC(s) - no
     * board move, timeline summary, state snapshot or notification.
     *
     * @param  ProjectTask  $task  Excluded task with its `pics.employee` relation loaded.
     */
    protected function completeExcludedTask(ProjectTask $task): void
    {
        // Just change the status only and remove the current PIC
        $this->taskRepo->update([
            'status' => null,
            'is_pool_task' => true,
        ], $task->uid);

        // Detach PIC
        DetachTaskPic::run(
            ids: $task->pics->pluck('employee.uid')->toArray(),
            taskId: $task->id,
        );
    }

    /**
     * Complete a task and return the refreshed task plus the project detail cache.
     *
     * Excluded tasks take the completeExcludedTask() short path; every other task runs the
     * full completion pipeline described on the class. Both branches force-rebuild the
     * project detail cache before returning.
     *
     * @param  string  $projectUid  Uid of the project the task belongs to.
     * @param  string  $taskUid  Uid of the task to complete.
     * @param  object|null  $employee  Acting employee when invoked outside an HTTP request
     *                                 (e.g. a Telegram bot context); null uses the auth user.
     * @param  bool  $sendNotification  Whether to dispatch TaskIsCompleteJob (normal path only).
     * @return array{task: ProjectTask, full_detail: mixed} The formatted task and detail cache.
     */
    public function handle(string $projectUid, string $taskUid, ?object $employee = null, bool $sendNotification = true): array
    {
        $this->registerService();

        $task = $this->getTask($taskUid);

        if ($task->exclude_from_point_calculation) {
            $this->completeExcludedTask($task);
            goto output;
        }

        // Register working time for current pic
        $this->updateWorkingTime($task);

        // Change task board
        $this->changeTaskBoard($task);

        // Detach current PIC from task
        DetachTaskPic::run(
            ids: $task->pics->pluck('employee.uid')->toArray(),
            taskId: $task->id,
            removeWorkState: false,
            removeCurrentPic: false
        );

        // Update task data
        $this->taskRepo->update([
            'status' => TaskStatus::Completed->value,
            'end_date' => null,
            'is_pool_task' => true, // !! Always set pool task to true, every completed task will be on pool section
        ], $task->uid);

        // Complete work state
        $currentPics = json_decode($task->current_pics, true) ?? [];
        $currentPicData = $this->employeeRepo->list(select: 'id', where: 'id IN ('.implode(',', $currentPics).')');
        $currentPicIds = $currentPicData->pluck('id')->toArray();

        $this->projectTaskWorkStateRepo->update(
            data: [
                'complete_at' => Carbon::now(),
            ],
            where: "task_id = {$task->id} AND employee_id IN (".implode(',', $currentPics).') AND complete_at IS NULL'
        );

        // Mark current approval state as complete
        $this->projectTaskPicApprovalstateRepo->update(
            data: [
                'approved_at' => Carbon::now(),
            ],
            id: 'id',
            where: "task_id = {$task->id} AND approved_at IS NULL"
        );

        // Summarize task timeline
        SummarizeTaskTimeline::run($taskUid, $currentPics);

        // save task state
        SaveTaskState::run($currentPicIds, $taskUid);

        if ($sendNotification) {
            TaskIsCompleteJob::dispatch($currentPicIds, $task->id)->afterCommit();
        }

        output:
        $currentData = DetailCache::run(
            projectUid: $projectUid,
            forceUpdateAll: true,
        );

        $payloadOutput = [
            'task' => $this->formattingTask($taskUid),
            'full_detail' => $currentData,
        ];

        return $payloadOutput;
    }

    /**
     * Relations eager-loaded when re-reading the task for the response payload.
     *
     * @return array<int, string>
     */
    protected function defaultTaskRelation(): array
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

    /**
     * Re-read the task with its full relation set and apply per-user permission flags.
     *
     * @param  string  $taskUid  The task uid to format for the response.
     */
    protected function formattingTask(string $taskUid): ProjectTask
    {
        $taskDetail = $this->taskRepo->show($taskUid, '*', $this->defaultTaskRelation());

        // format time tracker
        $task = PartialTaskPermissionCheck::run($taskDetail);

        return $task;
    }
}
