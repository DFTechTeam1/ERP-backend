<?php

namespace Modules\Finance\Services;

use App\Enums\Production\ProjectDealStatus;
use App\Enums\Production\ProjectLeadStatus;
use App\Enums\System\BaseRole;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\ProjectLead;

/**
 * Presentation-shape service for the Sales role dashboard.
 *
 * FinanceInsightService already delivers KPI aggregates scoped to the
 * sales user's own deals. This service fills the gaps that dashboard
 * needs but insight does not compute:
 *   - Pipeline funnel (Leads → Deals → Finalized → Fully paid)
 *   - My deals table (active/upcoming deals owned by the sales user)
 *   - List of sales people (for the executive filter dropdown)
 *
 * Scoping rules:
 *   - Sales user            → always scoped to their own employee_id
 *   - Director/Root         → unscoped by default (company-wide)
 *   - Director/Root + filter → scoped to the picked sales person's employee_id
 */
class MarketingDashboardService
{
    /**
     * Roles allowed to hit the sales dashboard endpoints.
     * Director/Root are included so the exec tier can preview and filter.
     */
    private const ALLOWED_ROLES = [
        BaseRole::Sales,
        BaseRole::Director,
        BaseRole::Root,
    ];

    /**
     * Pipeline funnel counts scoped per the resolveScope rules above.
     *
     * @return array{
     *   error: bool,
     *   message: string,
     *   data: array{
     *     period: array{month:int, year:int, label:string},
     *     stages: array<int, array{key:string, label:string, count:int}>,
     *     scope: array{is_filtered:bool, employee_id:?int}
     *   },
     *   code: int
     * }
     */
    public function getPipeline(?int $month = null, ?int $year = null, ?int $salesEmployeeId = null): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $period = $this->resolvePeriod($month, $year);
            $scope = $this->resolveScope($user, $salesEmployeeId);

            $stages = [
                [
                    'key' => 'leads',
                    'label' => __('global.leadsCreated'),
                    'count' => $this->leadCount($scope, $period),
                ],
                [
                    'key' => 'deals',
                    'label' => __('global.dealsCreated'),
                    'count' => $this->dealCount($scope, $period, onlyFinal: false),
                ],
                [
                    'key' => 'finalized',
                    'label' => __('global.dealsFinalized'),
                    'count' => $this->dealCount($scope, $period, onlyFinal: true),
                ],
                [
                    'key' => 'fullyPaid',
                    'label' => __('global.dealsFullyPaid'),
                    'count' => $this->dealCount(
                        $scope,
                        $period,
                        onlyFinal: true,
                        onlyFullyPaid: true,
                    ),
                ],
            ];

            return generalResponse(
                message: 'Success',
                data: [
                    'period' => [
                        'month' => $period['month'],
                        'year' => $period['year'],
                        'label' => $period['start']->format('F Y'),
                    ],
                    'stages' => $stages,
                    'scope' => [
                        'is_filtered' => $scope['scopeToEmployee'],
                        'employee_id' => $scope['scopeToEmployee'] ? $scope['employeeId'] : null,
                    ],
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Active deals table for the given period, respecting resolveScope rules.
     *
     * @return array{error:bool, message:string, data:array{items:array<int, array<string,mixed>>, total:int, period:array{month:int, year:int, label:string}, scope:array{is_filtered:bool, employee_id:?int}}, code:int}
     */
    public function getMyDeals(?int $month = null, ?int $year = null, int $limit = 10, ?int $salesEmployeeId = null): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $limit = max(1, min($limit, 50));

            $period = $this->resolvePeriod($month, $year);
            $scope = $this->resolveScope($user, $salesEmployeeId);

            $query = ProjectDeal::query()
                ->with([
                    'finalQuotation:id,project_deal_id,fix_price',
                    'class:id,name',
                    'city:id,name',
                    'marketings.employee:id,name',
                ])
                ->whereBetween('project_date', [
                    $period['start']->copy()->toDateString(),
                    $period['end']->copy()->toDateString(),
                ])
                ->whereNot('status', ProjectDealStatus::Canceled)
                ->orderBy('project_date');

            if ($scope['scopeToEmployee']) {
                $this->scopeToMarketing($query, $scope['employeeId']);
            }

            $total = (clone $query)->count();

            $rows = $query
                ->limit($limit)
                ->get()
                ->map(function (ProjectDeal $deal) {
                    return [
                        'uid' => $deal->uid ?? (string) $deal->id,
                        'identifier_number' => $deal->identifier_number,
                        'name' => $deal->name,
                        'project_date' => optional($deal->project_date)->toDateString(),
                        'venue' => $deal->venue,
                        'city' => optional($deal->city)->name,
                        'event_class' => optional($deal->class)->name,
                        'fix_price' => (float) (optional($deal->finalQuotation)->fix_price ?? 0),
                        'status' => $this->statusLabel($deal->status),
                        'is_fully_paid' => (bool) $deal->is_fully_paid,
                        'marketings' => $deal->marketings
                            ->map(fn (ProjectDealMarketing $m) => optional($m->employee)->name)
                            ->filter()
                            ->values()
                            ->all(),
                    ];
                })
                ->all();

            return generalResponse(
                message: 'Success',
                data: [
                    'items' => $rows,
                    'total' => $total,
                    'period' => [
                        'month' => $period['month'],
                        'year' => $period['year'],
                        'label' => $period['start']->format('F Y'),
                    ],
                    'scope' => [
                        'is_filtered' => $scope['scopeToEmployee'],
                        'employee_id' => $scope['scopeToEmployee'] ? $scope['employeeId'] : null,
                    ],
                ],
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Distinct sales people that have at least one row on project_deal_marketings.
     * Executive-only - Sales users get a 403.
     *
     * Excluded from the list:
     *   - The authenticated user's own employee record (execs viewing the
     *     dashboard shouldn't see themselves in a "filter by salesperson" list).
     *   - Any employee whose linked user carries the Root role - Root is a
     *     platform-admin marker, not a real salesperson.
     *
     * @return array{error:bool, message:string, data:array<int, array{id:int, name:string}>, code:int}
     */
    public function getSalesPeople(): array
    {
        try {
            $user = $this->authorizedUser();
            if (! $user) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            if (! $this->isExecutive($user)) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $employeeIds = ProjectDealMarketing::query()
                ->select('employee_id')
                ->distinct()
                ->pluck('employee_id')
                ->filter()
                ->values();

            $selfEmployeeId = (int) ($user->employee_id ?? 0);

            $people = Employee::query()
                ->whereIn('id', $employeeIds)
                ->when($selfEmployeeId > 0, fn ($q) => $q->where('id', '!=', $selfEmployeeId))
                ->whereDoesntHave(
                    'user.roles',
                    fn ($q) => $q->where('name', BaseRole::Root->value),
                )
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Employee $e) => ['id' => (int) $e->id, 'name' => (string) $e->name])
                ->all();

            return generalResponse(message: 'Success', data: $people);
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

        $allowed = array_map(fn (BaseRole $r) => $r->value, self::ALLOWED_ROLES);

        return $user->hasRole($allowed) ? $user : null;
    }

    private function isExecutive(User $user): bool
    {
        return $user->hasRole([BaseRole::Director->value, BaseRole::Root->value]);
    }

    private function employeeId(User $user): int
    {
        return (int) ($user->employee_id ?? 0);
    }

    /**
     * Resolve the effective scope for a request. Sales users are locked to
     * their own employee_id - the sales_employee_id param is IGNORED for
     * them so they can never escape their own scope. Executives are
     * unscoped by default and may narrow to one salesperson.
     *
     * @return array{employeeId:int, scopeToEmployee:bool}
     */
    private function resolveScope(User $user, ?int $salesEmployeeId): array
    {
        if ($this->isExecutive($user)) {
            $filterId = $salesEmployeeId && $salesEmployeeId > 0 ? (int) $salesEmployeeId : 0;

            return [
                'employeeId' => $filterId,
                'scopeToEmployee' => $filterId > 0,
            ];
        }

        return [
            'employeeId' => $this->employeeId($user),
            'scopeToEmployee' => true,
        ];
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
     * @param  array{employeeId:int, scopeToEmployee:bool}  $scope
     */
    private function leadCount(array $scope, array $period): int
    {
        $query = ProjectLead::query()
            ->where('status', ProjectLeadStatus::ACTIVE)
            ->whereBetween('project_date', [
                $period['start']->copy()->toDateString(),
                $period['end']->copy()->toDateString(),
            ]);

        if ($scope['scopeToEmployee']) {
            $eid = $scope['employeeId'];
            if ($eid <= 0) {
                return 0;
            }
            $query->where(function (Builder $q) use ($eid) {
                $q->whereJsonContains('pic_id', $eid)
                    ->orWhereJsonContains('pic_id', (string) $eid)
                    ->orWhere('created_by', $eid);
            });
        }

        return (int) $query->count();
    }

    /**
     * @param  array{employeeId:int, scopeToEmployee:bool}  $scope
     */
    private function dealCount(
        array $scope,
        array $period,
        bool $onlyFinal = false,
        bool $onlyFullyPaid = false,
    ): int {
        $query = ProjectDeal::query()
            ->whereBetween('project_date', [
                $period['start']->copy()->toDateString(),
                $period['end']->copy()->toDateString(),
            ])
            ->whereNot('status', ProjectDealStatus::Canceled);

        if ($onlyFinal) {
            $query->where('status', ProjectDealStatus::Final);
        }

        if ($onlyFullyPaid) {
            $query->where('is_fully_paid', true);
        }

        if ($scope['scopeToEmployee']) {
            $this->scopeToMarketing($query, $scope['employeeId']);
        }

        return (int) $query->count();
    }

    private function scopeToMarketing(Builder $query, int $employeeId): void
    {
        if ($employeeId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas(
            'marketings',
            fn (Builder $q) => $q->where('employee_id', $employeeId),
        );
    }

    private function statusLabel(?ProjectDealStatus $status): string
    {
        return match ($status) {
            ProjectDealStatus::Draft => 'draft',
            ProjectDealStatus::Final => 'final',
            ProjectDealStatus::Temporary => 'temporary',
            ProjectDealStatus::Canceled => 'canceled',
            default => 'unknown',
        };
    }
}
