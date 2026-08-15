<?php

namespace Modules\Production\Services;

use App\Enums\Production\TaskPicStatus;
use App\Enums\Production\TaskStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Hrd\Models\EmployeePointProject;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;
use Modules\Production\Models\ProjectTaskReviseHistory;

/**
 * Presentation-shape service for the Production Employee dashboard.
 *
 * Scoped to the currently authenticated employee. Delivers:
 *   - Personal KPIs (open, overdue, completed this week, points this month)
 *   - My task inbox (active tasks I'm assigned to, sorted by deadline)
 *   - Pool tasks I can claim
 *
 * "3+ revisions" flag is derived from ProjectTaskReviseHistory counts -
 * matches the CLAUDE.md rule about escalating heavy-revise tasks to PM.
 */
class ProductionEmployeeDashboardService
{
    private const HEAVY_REVISE_THRESHOLD = 3;

    /**
     * Personal KPI cards for the dashboard header.
     *
     * @return array{error:bool, message:string, data:array<string,mixed>, code:int}
     */
    public function getKpi(?int $month = null, ?int $year = null): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }
            $employeeId = $this->employeeId($user);
            if ($employeeId === 0) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $period = $this->resolvePeriod($month, $year);
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            // My active PIC assignments (a task can have multiple pics; we
            // only care about the row that belongs to me).
            $activePicIds = ProjectTaskPic::query()
                ->where('employee_id', $employeeId)
                ->whereIn('status', [
                    TaskPicStatus::Approved->value,
                    TaskPicStatus::Revise->value,
                ])
                ->pluck('project_task_id');

            // Load open tasks with the two things we need for the overdue
            // calc: MY deadline row (if any) and the parent project's event
            // date (used as fallback when no personal deadline was set).
            $openTasks = ProjectTask::query()
                ->with([
                    'project:id,project_date',
                    'deadlines' => fn ($q) => $q
                        ->where('employee_id', $employeeId)
                        ->orderBy('deadline'),
                ])
                ->whereIn('id', $activePicIds)
                ->whereNotIn('status', [
                    TaskStatus::Completed->value,
                    TaskStatus::OnHold->value,
                ])
                ->get();

            $openCount = $openTasks->count();
            $overdueCount = $openTasks
                ->filter(fn (ProjectTask $t) => $this->isTaskOverdue($t))
                ->count();

            $completedThisWeek = (int) ProjectTaskPic::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('updated_at', [$weekStart, $weekEnd])
                ->whereHas('task', fn (Builder $q) => $q->where('status', TaskStatus::Completed->value))
                ->count();

            // Mirror the Node endpoint (/api/v2/hrd/employees/{uid}/point/{y}/{m}):
            // sum EmployeePointProject.total_point where the LINKED Project's
            // event date falls in the requested month. Filtering by the
            // point row's own created_at (as an earlier version did) misses
            // points recorded after the event month closes.
            $pointsThisMonth = (float) EmployeePointProject::query()
                ->whereHas(
                    'employeePoint',
                    fn (Builder $q) => $q->where('employee_id', $employeeId),
                )
                ->whereHas(
                    'project',
                    fn (Builder $q) => $q->whereBetween('project_date', [
                        $period['start']->copy()->toDateString(),
                        $period['end']->copy()->toDateString(),
                    ]),
                )
                ->sum(DB::raw('COALESCE(total_point, 0)'));

            return generalResponse(
                message: 'Success',
                data: [
                    'my_open_tasks' => $openCount,
                    'my_overdue_tasks' => $overdueCount,
                    'completed_this_week' => $completedThisWeek,
                    'points_this_month' => $pointsThisMonth,
                    'period' => [
                        'month' => $period['month'],
                        'year' => $period['year'],
                        'label' => $period['start']->format('F Y'),
                    ],
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * My active task inbox - OnProgress + Revise, deadline-sorted.
     * Each row carries a revise_count + is_heavy_revise (>= 3) flag.
     *
     * @return array{error:bool, message:string, data:array{items:array<int,array<string,mixed>>, total:int}, code:int}
     */
    public function getMyTasks(int $limit = 20): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }
            $employeeId = $this->employeeId($user);
            if ($employeeId === 0) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 100));

            $activeTaskIds = ProjectTaskPic::query()
                ->where('employee_id', $employeeId)
                ->whereIn('status', [
                    TaskPicStatus::Approved->value,
                    TaskPicStatus::Revise->value,
                ])
                ->pluck('project_task_id');

            $query = ProjectTask::query()
                ->with([
                    'project:id,uid,name,project_deal_id,project_date',
                    'project.projectDeal:id,name,identifier_number',
                    'board:id,name',
                    'deadlines' => fn ($q) => $q
                        ->where('employee_id', $employeeId)
                        ->orderBy('deadline'),
                ])
                ->withCount('revises')
                ->whereIn('id', $activeTaskIds)
                ->whereNotIn('status', [
                    TaskStatus::Completed->value,
                    TaskStatus::OnHold->value,
                ]);

            $total = (clone $query)->count();

            $rows = $query
                ->limit($limit)
                ->get()
                ->map(function (ProjectTask $task) {
                    $eff = $this->effectiveDeadline($task);
                    $daysLeft = $eff['date']
                        ? (int) Carbon::today()
                            ->startOfDay()
                            ->diffInDays($eff['date']->copy()->startOfDay(), false)
                        : null;

                    return [
                        'id' => (int) $task->id,
                        'uid' => (string) ($task->uid ?? $task->id),
                        'name' => $task->name,
                        'project_uid' => optional($task->project)->uid,
                        'project_name' => optional($task->project)->name ?? '-',
                        'project_deal_identifier' => optional(optional($task->project)->projectDeal)->identifier_number,
                        'board' => optional($task->board)->name,
                        'status' => $this->statusKey((int) $task->status),
                        'deadline' => $eff['date']?->toDateString(),
                        // Which one supplied the date: 'personal' (my deadline
                        // row) or 'event' (fallback to project.project_date).
                        // Frontend can label the overdue chip appropriately.
                        'deadline_source' => $eff['source'],
                        'days_to_deadline' => $daysLeft,
                        'is_overdue' => $this->isTaskOverdue($task),
                        'revise_count' => (int) $task->revises_count,
                        'is_heavy_revise' => (int) $task->revises_count >= self::HEAVY_REVISE_THRESHOLD,
                    ];
                })
                ->sortBy(function (array $row) {
                    return $row['days_to_deadline'] ?? PHP_INT_MAX;
                })
                ->values()
                ->all();

            return generalResponse(
                message: 'Success',
                data: ['items' => $rows, 'total' => $total],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Pool tasks currently awaiting a picker. Ordered by nearest deadline.
     *
     * @return array{error:bool, message:string, data:array{items:array<int,array<string,mixed>>, total:int}, code:int}
     */
    public function getPoolTasks(int $limit = 20): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 100));

            $query = ProjectTask::query()
                ->with([
                    'project:id,uid,name,project_deal_id,project_date',
                    'project.projectDeal:id,name,identifier_number',
                    'board:id,name',
                ])
                ->where('is_pool_task', true)
                ->whereDoesntHave('pics'); // pool means not yet picked

            $total = (clone $query)->count();

            $rows = $query
                ->orderBy('created_at')
                ->limit($limit)
                ->get()
                ->map(function (ProjectTask $task) {
                    // Pool tasks have no PIC, so per-employee deadlines don't
                    // exist. The only meaningful timing is the event date on
                    // the parent project. Nobody is "overdue" on a pool item
                    // - the pool is just stale if the event already passed.
                    $eventDate = optional($task->project)->project_date;
                    $daysToEvent = null;
                    if ($eventDate) {
                        $daysToEvent = (int) Carbon::today()
                            ->startOfDay()
                            ->diffInDays(
                                Carbon::parse($eventDate)->startOfDay(),
                                false,
                            );
                    }

                    return [
                        'id' => (int) $task->id,
                        'uid' => (string) ($task->uid ?? $task->id),
                        'name' => $task->name,
                        'project_uid' => optional($task->project)->uid,
                        'project_name' => optional($task->project)->name ?? '-',
                        'project_deal_identifier' => optional(optional($task->project)->projectDeal)->identifier_number,
                        'board' => optional($task->board)->name,
                        'event_date' => $eventDate
                            ? Carbon::parse($eventDate)->toDateString()
                            : null,
                        'days_to_event' => $daysToEvent,
                        'created_at' => optional($task->created_at)?->toDateTimeString(),
                    ];
                })
                ->all();

            return generalResponse(
                message: 'Success',
                data: ['items' => $rows, 'total' => $total],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    private function authorizedUser(): ?User
    {
        return Auth::user();
    }

    /**
     * The ProjectTask model doesn't cast `status` to an enum, so we look up
     * the enum by its scalar value and return a kebab-friendly key the
     * frontend can map to labels + colors.
     */
    private function statusKey(int $value): ?string
    {
        $enum = TaskStatus::tryFrom($value);

        return $enum ? strtolower($enum->name) : null;
    }

    private function employeeId(User $user): int
    {
        return (int) ($user->employee_id ?? 0);
    }

    /**
     * @return array{month:int, year:int, start:Carbon, end:Carbon}
     */
    private function resolvePeriod(?int $month, ?int $year): array
    {
        $now = Carbon::now();
        $resolvedMonth = $month && $month >= 1 && $month <= 12 ? $month : (int) $now->month;
        $resolvedYear = $year && $year >= 2000 && $year <= 2100 ? $year : (int) $now->year;

        $start = Carbon::create($resolvedYear, $resolvedMonth, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'month' => $resolvedMonth,
            'year' => $resolvedYear,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Resolve the effective due date for a task. Prefer the assignee's own
     * ProjectTaskDeadline row, falling back to the parent project's
     * project_date when no personal deadline is set.
     *
     * The `deadlines` relation MUST already be eager-loaded scoped to the
     * caller's employee_id (see getKpi/getMyTasks call sites).
     *
     * @return array{date: ?Carbon, source: ?string}  source ∈ {personal, event, null}
     */
    private function effectiveDeadline(ProjectTask $task): array
    {
        $personal = $task->relationLoaded('deadlines')
            ? $task->deadlines->firstWhere(fn ($d) => (bool) $d->deadline)
            : null;
        if ($personal?->deadline) {
            return [
                'date' => Carbon::parse($personal->deadline),
                'source' => 'personal',
            ];
        }

        $eventDate = optional($task->project)->project_date;
        if ($eventDate) {
            return [
                'date' => Carbon::parse($eventDate),
                'source' => 'event',
            ];
        }

        return ['date' => null, 'source' => null];
    }

    /**
     * A task counts as overdue when its effective deadline is past today.
     * When the personal deadline exists, actual_finish_time must be null -
     * a task finished-but-not-yet-marked-Completed is not overdue.
     *
     * Caller is responsible for restricting to tasks whose status is still
     * open (excluded Completed / OnHold) and that belong to the current
     * employee via the PIC join.
     */
    private function isTaskOverdue(ProjectTask $task): bool
    {
        $today = Carbon::today()->startOfDay();

        $personal = $task->relationLoaded('deadlines')
            ? $task->deadlines->firstWhere(fn ($d) => (bool) $d->deadline)
            : null;
        if ($personal?->deadline) {
            if ($personal->actual_finish_time !== null) {
                return false;
            }

            return Carbon::parse($personal->deadline)->startOfDay()->lt($today);
        }

        $eventDate = optional($task->project)->project_date;
        if (! $eventDate) {
            return false;
        }

        return Carbon::parse($eventDate)->startOfDay()->lt($today);
    }
}
