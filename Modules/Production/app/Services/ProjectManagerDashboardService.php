<?php

namespace Modules\Production\Services;

use App\Enums\Production\ProjectStatus;
use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectPersonInCharge;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;

/**
 * Presentation-shape service for the Project Manager dashboard.
 *
 * Scope: projects where the acting PM is on ProjectPersonInCharge as pic_id.
 * PM Admin sees all projects unscoped. Regular PM only sees their own.
 *
 * Provides:
 *   - Personal KPIs (my projects, team open, team overdue, at-risk)
 *   - My projects table with progress %
 *   - Team workload (per-PIC open + overdue + heavy-revise counts)
 *   - Projects at risk (event date within a window)
 */
class ProjectManagerDashboardService
{
    private const HEAVY_REVISE_THRESHOLD = 3;

    private const AT_RISK_WINDOW_DAYS = 7;

    /**
     * Statuses that mean the project is still active (excludes Draft,
     * Completed, Canceled).
     */
    private const ACTIVE_STATUSES = [
        ProjectStatus::OnGoing,
        ProjectStatus::Revise,
        ProjectStatus::WaitingApprovalClient,
        ProjectStatus::ApprovedByClient,
        ProjectStatus::ReadyToGo,
    ];

    /**
     * @return array{error:bool, message:string, data:array<string,mixed>, code:int}
     */
    public function getKpi(): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $projectIds = $this->scopedProjectIds($user);
            $today = Carbon::today();
            $atRiskUntil = $today->copy()->addDays(self::AT_RISK_WINDOW_DAYS);

            $projectCount = count($projectIds);

            // team_open_tasks = work that STILL NEEDS TO BE DISPATCHED on my
            // projects. Excludes:
            //   - Finalize board (already delivered)
            //   - pool tasks (waiting for a picker to claim, out of PM hand)
            //   - tasks that already have an active PIC (someone owns it)
            $unassignedOpenQuery = ProjectTask::query()
                ->whereIn('project_id', $projectIds)
                ->where('is_pool_task', false)
                ->whereNotIn('status', [
                    TaskStatus::Completed->value,
                    TaskStatus::OnHold->value,
                ])
                ->whereDoesntHave('pics', fn (Builder $q) => $q
                    ->whereIn('status', [
                        TaskPicStatus::Approved->value,
                        TaskPicStatus::Revise->value,
                    ]))
                ->whereDoesntHave('board', fn (Builder $q) => $q
                    ->whereRaw('LOWER(name) = ?', ['finalize']));

            $teamOpen = (int) (clone $unassignedOpenQuery)->count();

            // team_overdue_tasks = subset of the "still-to-dispatch" set
            // whose parent project's event date is already past. Meaning:
            // "work I haven't handed out yet and the event already
            // happened / is happening today".
            $teamOverdue = (int) (clone $unassignedOpenQuery)
                ->whereHas('project', fn (Builder $q) => $q
                    ->whereDate('project_date', '<', $today))
                ->count();

            $atRisk = Project::query()
                ->whereIn('id', $projectIds)
                ->whereIn(
                    'status',
                    array_map(fn (ProjectStatus $s) => $s->value, self::ACTIVE_STATUSES),
                )
                ->where(function (Builder $q) use ($today, $atRiskUntil) {
                    $q->whereDate('project_date', '<', $today)
                        ->orWhereBetween('project_date', [$today, $atRiskUntil]);
                })
                ->count();

            return generalResponse(
                message: 'Success',
                data: [
                    'my_projects' => $projectCount,
                    'team_open_tasks' => (int) $teamOpen,
                    'team_overdue_tasks' => (int) $teamOverdue,
                    'projects_at_risk' => (int) $atRisk,
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * My active projects with progress % (Completed tasks / total tasks).
     *
     * @return array{error:bool, message:string, data:array{items:array<int,array<string,mixed>>, total:int}, code:int}
     */
    public function getMyProjects(int $limit = 10): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 100));
            $projectIds = $this->scopedProjectIds($user);

            $query = Project::query()
                ->with([
                    'personInCharges.employee:id,name,nickname',
                    'projectDeal:id,name,identifier_number',
                ])
                ->withCount([
                    'tasks as total_tasks',
                    'tasks as completed_tasks' => fn (Builder $q) => $q
                        ->where('status', TaskStatus::Completed->value),
                ])
                ->whereIn('id', $projectIds)
                ->whereIn(
                    'status',
                    array_map(fn (ProjectStatus $s) => $s->value, self::ACTIVE_STATUSES),
                )
                ->orderBy('project_date');

            $total = (clone $query)->count();

            $items = $query
                ->limit($limit)
                ->get()
                ->map(function (Project $project) {
                    $totalTasks = (int) $project->total_tasks;
                    $completed = (int) $project->completed_tasks;
                    $progress = $totalTasks > 0
                        ? (int) round(($completed / $totalTasks) * 100)
                        : 0;
                    $eventDate = $project->project_date;
                    $daysToEvent = $eventDate
                        ? (int) Carbon::today()
                            ->startOfDay()
                            ->diffInDays(
                                Carbon::parse($eventDate)->startOfDay(),
                                false,
                            )
                        : null;

                    return [
                        'id' => (int) $project->id,
                        'uid' => (string) ($project->uid ?? $project->id),
                        'name' => $project->name,
                        'event_date' => $eventDate
                            ? Carbon::parse($eventDate)->toDateString()
                            : null,
                        'days_to_event' => $daysToEvent,
                        'identifier_number' => optional($project->projectDeal)->identifier_number,
                        'pic_names' => $project->personInCharges
                            ->map(fn ($p) => optional($p->employee)->nickname
                                ?: optional($p->employee)->name)
                            ->filter()
                            ->values()
                            ->all(),
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completed,
                        'progress_percent' => $progress,
                    ];
                })
                ->all();

            return generalResponse(
                message: 'Success',
                data: ['items' => $items, 'total' => $total],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Team workload — for each PIC on the PM's projects, count their open
     * tasks, overdue tasks, and heavy-revise tasks (>= 3 revises).
     *
     * @return array{error:bool, message:string, data:array{items:array<int,array<string,mixed>>, total:int}, code:int}
     */
    public function getTeamWorkload(int $limit = 20): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 100));
            $projectIds = $this->scopedProjectIds($user);

            $taskIds = ProjectTask::query()
                ->whereIn('project_id', $projectIds)
                ->whereNotIn('status', [
                    TaskStatus::Completed->value,
                    TaskStatus::OnHold->value,
                ])
                ->pluck('id');

            // For a regular PM, "team" = employees whose boss_id points to
            // the PM's own employee row (their direct reports). For exec
            // callers (PM Admin / Director / Root) we fall back to "any
            // employee with open work on my scope" since they don't own a
            // direct-report tree the same way.
            $isExec = $user->hasRole([
                BaseRole::ProjectManagerAdmin->value,
                BaseRole::Director->value,
                BaseRole::Root->value,
            ]);

            if ($isExec) {
                $employeeIds = ProjectTaskPic::query()
                    ->whereIn('project_task_id', $taskIds)
                    ->whereIn('status', [
                        TaskPicStatus::Approved->value,
                        TaskPicStatus::Revise->value,
                    ])
                    ->pluck('employee_id')
                    ->unique()
                    ->values();
                $employees = Employee::query()
                    ->whereIn('id', $employeeIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'nickname']);
            } else {
                $pmEmployeeId = (int) ($user->employee_id ?? 0);
                if ($pmEmployeeId === 0) {
                    return generalResponse(
                        message: 'Success',
                        data: ['items' => [], 'total' => 0],
                    );
                }
                $employees = Employee::query()
                    ->where('boss_id', $pmEmployeeId)
                    ->orderBy('name')
                    ->get(['id', 'name', 'nickname']);
            }

            $rows = $employees
                ->map(function (Employee $emp) use ($taskIds) {
                    // Load each open task the PIC is working on so the
                    // frontend can expand the row to reveal what they're
                    // actually doing - not just the counts.
                    $tasks = ProjectTask::query()
                        ->with([
                            'project:id,uid,name,project_date',
                            'board:id,name',
                            'deadlines' => fn ($q) => $q
                                ->where('employee_id', $emp->id)
                                ->orderBy('deadline'),
                        ])
                        ->withCount('revises')
                        ->whereIn('id', $taskIds)
                        ->whereHas(
                            'pics',
                            fn (Builder $q) => $q
                                ->where('employee_id', $emp->id)
                                ->whereIn('status', [
                                    TaskPicStatus::Approved->value,
                                    TaskPicStatus::Revise->value,
                                ]),
                        )
                        ->get();

                    $today = Carbon::today();
                    $taskRows = $tasks->map(function (ProjectTask $task) use ($emp, $today) {
                        $deadlineRow = $task->relationLoaded('deadlines')
                            ? $task->deadlines->firstWhere(fn ($d) => (bool) $d->deadline)
                            : null;
                        $eventDate = optional($task->project)->project_date;

                        $effective = null;
                        $source = null;
                        if ($deadlineRow?->deadline) {
                            $effective = Carbon::parse($deadlineRow->deadline);
                            $source = 'personal';
                        } elseif ($eventDate) {
                            $effective = Carbon::parse($eventDate);
                            $source = 'event';
                        }

                        $daysLeft = $effective
                            ? (int) $today->copy()
                                ->startOfDay()
                                ->diffInDays($effective->copy()->startOfDay(), false)
                            : null;

                        $overdue = $source === 'personal'
                            ? $deadlineRow->actual_finish_time === null
                                && Carbon::parse($deadlineRow->deadline)->startOfDay()->lt($today)
                            : ($source === 'event' && $effective->startOfDay()->lt($today));

                        return [
                            'id' => (int) $task->id,
                            'uid' => (string) ($task->uid ?? $task->id),
                            'name' => $task->name,
                            'project_uid' => optional($task->project)->uid,
                            'project_name' => optional($task->project)->name ?? '-',
                            'board' => optional($task->board)->name,
                            'deadline' => $effective?->toDateString(),
                            'deadline_source' => $source,
                            'days_to_deadline' => $daysLeft,
                            'is_overdue' => (bool) $overdue,
                            'revise_count' => (int) $task->revises_count,
                            'is_heavy_revise' => (int) $task->revises_count
                                >= self::HEAVY_REVISE_THRESHOLD,
                        ];
                    })
                        ->sortBy(fn ($t) => $t['days_to_deadline'] ?? PHP_INT_MAX)
                        ->values()
                        ->all();

                    $overdue = collect($taskRows)->where('is_overdue', true)->count();
                    $heavy = collect($taskRows)
                        ->where('is_heavy_revise', true)
                        ->count();

                    return [
                        'employee_id' => (int) $emp->id,
                        'name' => $emp->nickname ?: $emp->name,
                        'open_tasks' => count($taskRows),
                        'overdue_tasks' => (int) $overdue,
                        'heavy_revise_tasks' => (int) $heavy,
                        // Frontend uses this to render an expandable sub-row
                        // showing what each team member is actually working on.
                        'tasks' => $taskRows,
                    ];
                })
                ->sortByDesc('open_tasks')
                ->values();

            return generalResponse(
                message: 'Success',
                data: [
                    'items' => $rows->take($limit)->all(),
                    'total' => $rows->count(),
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Active projects whose event date is either past or within the risk
     * window. Sorted by nearest event first (past events first).
     *
     * @return array{error:bool, message:string, data:array{items:array<int,array<string,mixed>>, total:int}, code:int}
     */
    public function getAtRiskProjects(int $limit = 10): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 100));
            $projectIds = $this->scopedProjectIds($user);
            $today = Carbon::today();
            $until = $today->copy()->addDays(self::AT_RISK_WINDOW_DAYS);

            $query = Project::query()
                ->with([
                    'personInCharges.employee:id,name,nickname',
                    'projectDeal:id,name,identifier_number',
                ])
                ->whereIn('id', $projectIds)
                ->whereIn(
                    'status',
                    array_map(fn (ProjectStatus $s) => $s->value, self::ACTIVE_STATUSES),
                )
                ->where(function (Builder $q) use ($today, $until) {
                    $q->whereDate('project_date', '<', $today)
                        ->orWhereBetween('project_date', [$today, $until]);
                })
                ->orderBy('project_date');

            $total = (clone $query)->count();

            $items = $query
                ->limit($limit)
                ->get()
                ->map(function (Project $project) {
                    $daysToEvent = $project->project_date
                        ? (int) Carbon::today()
                            ->startOfDay()
                            ->diffInDays(
                                Carbon::parse($project->project_date)->startOfDay(),
                                false,
                            )
                        : null;

                    return [
                        'id' => (int) $project->id,
                        'uid' => (string) ($project->uid ?? $project->id),
                        'name' => $project->name,
                        'event_date' => $project->project_date
                            ? Carbon::parse($project->project_date)->toDateString()
                            : null,
                        'days_to_event' => $daysToEvent,
                        'identifier_number' => optional($project->projectDeal)->identifier_number,
                        'pic_names' => $project->personInCharges
                            ->map(fn ($p) => optional($p->employee)->nickname
                                ?: optional($p->employee)->name)
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                })
                ->all();

            return generalResponse(
                message: 'Success',
                data: ['items' => $items, 'total' => $total],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    private function authorizedUser(): ?User
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $allowed = [
            BaseRole::ProjectManager->value,
            BaseRole::ProjectManagerAdmin->value,
            BaseRole::Director->value,
            BaseRole::Root->value,
        ];

        return $user->hasRole($allowed) ? $user : null;
    }

    /**
     * PM Admin + Director + Root see every project. Regular PM sees only
     * projects where their employee_id is on ProjectPersonInCharge.
     *
     * @return array<int,int>
     */
    private function scopedProjectIds(User $user): array
    {
        if ($user->hasRole([
            BaseRole::ProjectManagerAdmin->value,
            BaseRole::Director->value,
            BaseRole::Root->value,
        ])) {
            return Project::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $employeeId = (int) ($user->employee_id ?? 0);
        if ($employeeId === 0) {
            return [];
        }

        return ProjectPersonInCharge::query()
            ->where('pic_id', $employeeId)
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
