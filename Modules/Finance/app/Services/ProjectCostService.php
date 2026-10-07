<?php

namespace Modules\Finance\Services;

use App\Data\Finance\ProjectCost\DashboardCostByClassData;
use App\Data\Finance\ProjectCost\DashboardCostCompositionData;
use App\Data\Finance\ProjectCost\DashboardCostTrendData;
use App\Data\Finance\ProjectCost\DashboardListSummaryData;
use App\Data\Finance\ProjectCost\DetailProjectCostData;
use App\Data\Finance\ProjectCost\EmployeeRewardData;
use App\Data\Finance\ProjectCost\EmployeeRewardListData;
use App\Data\Finance\ProjectCost\ProjectItemData;
use App\Data\Finance\ProjectCost\ProjectListData;
use App\Exports\ProjectRewardReportExport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Production\Models\Project;
use Modules\Production\Repository\ProjectRepository;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectCostService
{
    /**
     * Excel number-format code for rupiah amounts: Indonesian locale ([$-421]) with '.' thousands
     * grouping and no decimals, so 200000 renders as "200.000". Applied as a column format so the
     * cells stay real numbers (sum-able, and 0 shows as "0" rather than blank) instead of being
     * pre-formatted strings, which the value binder would coerce back into the wrong number.
     */
    private const IDR_NUMBER_FORMAT = '[$-421]#,##0';

    /**
     * @param  ProjectRepository  $repo  Repository used to query projects and their cost relations.
     */
    public function __construct(
        private readonly ProjectRepository $repo
    ) {}

    /**
     * Resolve the requested `year` filter from the current request query string.
     *
     * @return string The `year` query parameter.
     */
    protected function getYear(): string
    {
        return request('year');
    }

    /**
     * Resolve the requested `month` filter from the current request query string.
     *
     * @return string|null The `month` query parameter, or null when it is not supplied.
     */
    protected function getMonth(): ?string
    {
        return request('month');
    }

    /**
     * Resolve the requested `event_class` (project class id) filter from the current request.
     *
     * @return string|null The `event_class` query parameter, or null when it is not supplied.
     */
    protected function getEventClass(): ?string
    {
        return request('event_class');
    }

    /**
     * Resolve the requested `finalization` (project status) filter from the current request.
     *
     * @return string|null The `finalization` query parameter, or null when it is not supplied.
     */
    protected function getProjectStatus(): ?string
    {
        return request('finalization');
    }

    /**
     * The project columns selected for every cost query.
     *
     * @return string A comma separated column list for a raw select.
     */
    protected function projectColumns(): string
    {
        return 'id,project_deal_id,uid,name,project_date,venue,project_class_id,status';
    }

    /**
     * The eager loaded relations required to compute project cost, price and PIC data.
     *
     * @return array<int, string> Relation constraints for the repository `with()` call.
     */
    protected function projectRelations(): array
    {
        return [
            'projectClass:id,name,color',
            'projectDeal:id,is_fully_paid',
            'projectDeal.finalQuotation',
            'projectDeal.latestQuotation',
            'projectDeal.transactions:id,payment_amount,project_deal_id',
            'rewards:id,project_id,employee_id,total_reward,total_point',
            'rewards.employee:id,name,avatar,position_id,uid,user_id',
            'rewards.employee.user:id',
            'rewards.employee.position:id,name',
            'personInCharges:id,project_id,pic_id',
            'personInCharges.employee:id,name',
        ];
    }

    /**
     * Build the SQL where clause and the matching cache key from the current request filters
     * (year, month and event class).
     *
     * @return array{0: string, 1: string} A tuple of [whereClause, cacheKey]. The where clause is
     *                                     empty when no filter is supplied.
     */
    protected function projectConditions(): array
    {
        $where = '';
        $cacheKey = 'PROJECTS:COSTS';

        if ($this->getyear() && ! $this->getMonth()) {
            // all year
            $startYear = Carbon::now()->startOfYear()->format('Y-m-d');
            $endYear = Carbon::now()->endOfYear()->format('Y-m-d');
            $where = "project_date BETWEEN '".$startYear."' AND '".$endYear."'";
            $cacheKey .= ":Y::{$startYear}-{$endYear}";
        } elseif ($this->getYear() && $this->getMonth()) {
            $where = "MONTH(project_date) = '{$this->getMonth()}' AND YEAR(project_date) = '{$this->getYear()}'";
            $cacheKey .= ":M::{$this->getMonth()}:Y::{$this->getYear()}";
        }

        if ($this->getEventClass()) {
            if (empty($where)) {
                $where = "project_class_id = {$this->getEventClass()}";
            } else {
                $where .= " AND project_class_id = {$this->getEventClass()}";
            }
            $cacheKey .= ":C::{$this->getEventClass()}";
        }

        if (request('search')) {
            $search = request('search');
            if (empty($where)) {
                $where = "name LIKE '%{$search}%'";
            } else {
                $where .= " AND name LIKE '%{$search}%'";
            }

            $trim = str_replace(' ', '', strtolower($search));
            $cacheKey .= ":S::{$trim}";
        }

        return [$where, $cacheKey];
    }

    /**
     * Fetch the filtered projects with their computed cost fields, cached for two hours under the
     * `project_costs` cache tag so the whole group can be flushed at once.
     *
     * Each project is enriched with total_reward, total_fix_price, total_cost, is_fully_paid and
     * class_name so the dashboard aggregations can read them directly.
     *
     * @return Collection<int, Project> The projects matching the current request filters.
     */
    protected function getProjects(): Collection
    {
        [$where, $cacheKey] = $this->projectConditions();

        $cache = Cache::get($cacheKey);
        if (! $cache) {
            $cache = Cache::tags(['project_costs'])->remember($cacheKey, 7200, function () use ($where) {
                $projects = $this->repo->list(
                    select: $this->projectColumns(),
                    relation: $this->projectRelations(),
                    where: $where,
                    orderBy: 'project_date ASC'
                )->map(function ($item) {
                    $totalRewards = $item->rewards->sum('total_reward');
                    $totalFixPrice = 0;
                    if ($item->projectDeal && $item->projectDeal->finalQuotation) {
                        $totalFixPrice = $item->projectDeal->finalQuotation->fix_price;
                    } elseif ($item->projectDeal && ! $item->projectDeal->finalQuotation && $item->projectDeal->latestQuotation) {
                        $totalFixPrice = $item->projectDeal->latestQuotation->fix_price;
                    }

                    $item['total_reward'] = $totalRewards;
                    $item['total_fix_price'] = $totalFixPrice;
                    $item['total_cost'] = $totalRewards;
                    $item['is_fully_paid'] = $item->projectDeal && $item->projectDeal->is_fully_paid ? true : false;
                    $item['class_name'] = $item->projectClass->name;

                    return $item;
                })->values();

                return $projects;
            });
        }

        return $cache;
    }

    /**
     * Build the dashboard summary card: totals for cost, reward, fix price, gross profit and the
     * average cost per project.
     *
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope carrying a DashboardListSummaryData payload.
     */
    public function getDashboardSummary(): array
    {
        try {
            $projects = $this->getProjects();

            $totalFixPrice = $projects->sum('total_fix_price');
            $totalCost = $projects->sum('total_cost');
            $averageCostPerProject = round($totalCost / $projects->count(), 2);

            $output = new DashboardListSummaryData(
                total_projects: $projects->count(),
                total_cost: $totalCost,
                total_employee_reward: $projects->sum('total_reward'),
                average_cost: $averageCostPerProject,
                total_fix_price: $totalFixPrice,
                total_gross_profit: $totalFixPrice - $totalCost,
                provisional_count: $projects->where('is_fully_paid', false)->count(),
                currency: 'IDR' // Static IDR for now
            );

            return generalResponse(
                message: 'Success',
                data: $output->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the monthly cost trend: total cost and employee reward grouped by project month.
     *
     * @return array{error: bool, message: string, data?: array<int, DashboardCostTrendData>, code?: int}
     *                                                                                                    The standard API response envelope carrying a list of per month trend points.
     */
    public function getCostTrend(): array
    {
        try {
            $projects = $this->getProjects()->map(function ($item) {
                $item['month'] = date('M', strtotime($item->project_date));

                return $item;
            })->groupBy('month');

            $output = [];
            foreach ($projects as $month => $project) {
                $output[] = new DashboardCostTrendData(
                    month: "{$month} 2026",
                    total_cost: $project->sum('total_cost'),
                    employee_reward: $project->sum('total_reward')
                );
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the cost breakdown grouped by event (project) class.
     *
     * @return array{error: bool, message: string, data?: array<int, DashboardCostByClassData>, code?: int}
     *                                                                                                      The standard API response envelope carrying a total cost per class.
     */
    public function getCostByClass(): array
    {
        try {
            $projects = $this->getProjects()->groupBy('class_name');

            $output = [];
            foreach ($projects as $className => $project) {
                $output[] = new DashboardCostByClassData(
                    event_class: $className,
                    total_cost: $project->sum('total_cost')
                );
            }

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the cost composition rows. Currently only the employee reward component is tracked.
     *
     * @param  Collection<int, Project>  $projects  The projects to compose the totals from.
     * @return array<int, array<string, mixed>> A list of composition components as arrays.
     */
    protected function mainProjectCompositions(Collection $projects)
    {
        $output = [];
        $employeeReward = new DashboardCostCompositionData(
            key: 'employee_reward',
            label: 'Employee Reward',
            total: $projects->sum('total_reward')
        );

        array_push($output, $employeeReward->toArray());

        return $output;
    }

    /**
     * Build the cost composition card (cost split by component).
     *
     * @return array{error: bool, message: string, data?: array<int, array<string, mixed>>, code?: int}
     *                                                                                                  The standard API response envelope carrying the composition components.
     */
    public function getCostComposition(): array
    {
        try {
            $projects = $this->getProjects();
            $output = $this->mainProjectCompositions($projects);

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Fetch a page of the filtered projects (not cached, used by the paginated listings).
     *
     * @param  int|string  $itemsPerPage  Number of rows per page.
     * @param  int|string  $page  Zero based offset already computed by the caller.
     * @return Collection<int, Project> The projects on the requested page.
     */
    protected function getProjectPagination(int|string $itemsPerPage, int|string $page): Collection
    {
        [$where, $cacheKey] = $this->projectConditions();

        $projects = $this->repo->pagination(
            select: $this->projectColumns(),
            relation: $this->projectRelations(),
            where: $where,
            itemsPerPage: $itemsPerPage,
            page: $page
        );

        return $projects;
    }

    /**
     * Build the "latest projects" widget: the five most recent projects for the current filters.
     *
     * @return array{error: bool, message: string, data?: array<int, ProjectItemData>, code?: int}
     *                                                                                             The standard API response envelope carrying the formatted project rows.
     */
    public function getDashboardLatest(): array
    {
        try {
            $projects = $this->getProjectPagination(5, 1);
            $output = $this->formatProjectPagination($projects);

            return generalResponse(
                message: 'Success',
                data: $output
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Map a project collection into ProjectItemData rows for the listing widgets.
     *
     * @param  Collection<int, Project>  $projects  The projects to format.
     * @return array<int, ProjectItemData> The formatted rows.
     */
    protected function formatProjectPagination(Collection $projects): array
    {
        $output = [];
        foreach ($projects as $project) {
            $output[] = new ProjectItemData(
                uid: $project->uid,
                name: $project->name,
                project_date: date('Y-m-d', strtotime($project->project_date)),
                event_class: $project->projectClass->name,
                event_class_color: $project->projectClass->color,
                venue: $project->venue,
                pic: $project->personInCharges->count() > 0 ? $project->personInCharges->pluck('employee.name')->join(',') : '-',
                status: $project->status_text,
                status_color: $project->status_color,
                total_cost: $project->rewards->sum('total_reward'),
                total_employee_reward: $project->rewards->sum('total_reward'),
                fix_price: $project->projectDeal && $project->projectDeal->latestQuotation ? $project->projectDeal->latestQuotation->fix_price : 0,
                amount_paid: 0,
                is_fully_paid: $project?->projectDeal?->latestQuotation?->is_fully_paid ?? false,
                fix_price_updated_at: null,
                currency: 'IDR'
            );
        }

        return $output;
    }

    /**
     * Build the paginated project cost listing for the dashboard table.
     *
     * Reads `itemsPerPage` and `page` from the request (itemsPerPage of -1 returns everything).
     *
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope carrying a ProjectListData payload.
     */
    public function getDashboard()
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $itemsPerPage = $itemsPerPage == -1 ? 999999 : $itemsPerPage;
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            [$where, $cacheKey] = $this->projectConditions();

            $projects = $this->getProjectPagination($itemsPerPage, $page);

            $output = $this->formatProjectPagination($projects);

            $totalData = $this->repo->list(select: 'id', where: $where);
            $paginatedOutput = new ProjectListData(
                totalData: $totalData->count(),
                paginated: $output,
            );

            return generalResponse(
                message: 'Success',
                data: $paginatedOutput->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Join a project's person in charge names into a single comma separated string.
     *
     * @param  Project|Collection<int, Project>  $project  The project whose PICs are formatted.
     * @return string Comma separated PIC names, or an empty string when there are none.
     */
    protected function formatProjectPics(Project|Collection $project): string
    {
        return $project->personInCharges->count() > 0 ? $project->personInCharges->pluck('employee.name')->join(',') : '';
    }

    /**
     * Build the full cost detail for a single project: price, payment status, cost breakdown and
     * the per employee rewards.
     *
     * @param  string  $projectUid  The project uid to load.
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope carrying a DetailProjectCostData payload.
     */
    public function detailProjectCost(string $projectUid): array
    {
        try {
            $project = $this->repo->show(
                uid: $projectUid,
                select: $this->projectColumns(),
                relation: $this->projectRelations()
            );

            $paidAmount = $project?->projectDeal?->transactions?->sum('payment_amount') ?? 0;
            $fixPrice = $project?->projectDeal?->latestQuotation?->fix_price ?? 0;
            $outstanding = $fixPrice - $paidAmount;
            $isFullyPaid = $project?->projectDeal?->latestQuotation?->is_fully_paid ?? false;
            $paymentStatus = $isFullyPaid ? 'paid' : ($outstanding > 0 ? 'partial' : 'unpaid');

            $employeeRewards = [];
            foreach ($project->rewards as $reward) {
                $employeeRewards[] = new EmployeeRewardListData(
                    id: $reward->id,
                    uid: $reward->employee->uid,
                    name: $reward->employee->name,
                    avatar: $reward->employee?->avatar ?? null,
                    position: $reward->employee->position->name,
                    role: $reward->employee->user->roles->first()?->name ?? '',
                    total_point: $reward->total_point ?? 0,
                    total_reward: $reward->total_reward ?? 0
                );
            }

            $output = new DetailProjectCostData(
                uid: $project->uid,
                name: $project->name,
                project_date: $project->project_date,
                event_class: $project->projectClass->name,
                event_class_color: $project->projectClass->color,
                venue: $project->venue,
                pic: $this->formatProjectPics($project),
                currency: 'IDR',
                total_cost: $project->rewards->sum('total_reward'),
                fix_price: $fixPrice,
                amount_paid: $paidAmount,
                outstanding: $outstanding,
                is_fully_paid: $project?->projectDeal?->latestQuotation?->is_fully_paid ?? false,
                payment_status: $paymentStatus,
                fix_price_updated_at: '',
                gross_profit: 0,
                gross_margin: 0,
                cost_breakdown: $project->getCosts(),
                employee_rewards: new EmployeeRewardData(
                    total: collect($employeeRewards)->sum('total_reward'),
                    items: $employeeRewards
                )
            );

            return generalResponse(
                message: 'Success',
                data: $output->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * The eager loaded relations required to build the reward disbursement report. Unlike
     * {@see projectRelations()} this also loads each reward's `role` and the employee's HR code so the
     * detail and disbursement sheets can be rendered.
     *
     * @return array<int, string> Relation constraints for the repository `with()` call.
     */
    protected function exportRelations(): array
    {
        return [
            'projectClass:id,name',
            'rewards:id,project_id,employee_id,total_point,total_reward,role',
            'rewards.employee:id,name,employee_id,position_id,uid',
            'rewards.employee.position:id,name',
            'personInCharges:id,project_id,pic_id',
            'personInCharges.employee:id,name',
        ];
    }

    /**
     * Build the SQL where clause for the reward export from the current request filters.
     *
     * Unlike {@see projectConditions()} this filters by the requested year directly (not the current
     * year) and also honours the `finalization` (project status) filter, because the export is a
     * point-in-time disbursement document that must match exactly what was asked for.
     *
     * @return string The where clause, empty when no filter is supplied.
     */
    protected function exportConditions(): string
    {
        $conditions = [];

        if ($this->getYear() && ! $this->getMonth()) {
            $conditions[] = "YEAR(project_date) = '{$this->getYear()}'";
        } elseif ($this->getYear() && $this->getMonth()) {
            $conditions[] = "YEAR(project_date) = '{$this->getYear()}' AND MONTH(project_date) = '{$this->getMonth()}'";
        }

        if ($this->getEventClass()) {
            $conditions[] = "project_class_id = {$this->getEventClass()}";
        }

        if ($this->getProjectStatus()) {
            $conditions[] = "status = {$this->getProjectStatus()}";
        }

        if (request('search')) {
            $search = request('search');
            $conditions[] = "name LIKE '%{$search}%'";
        }

        return implode(' AND ', $conditions);
    }

    /**
     * The download file name for the reward report, scoped by the requested year and month.
     *
     * @return string e.g. `reward_report_2026_03.xlsx`.
     */
    protected function exportFileName(): string
    {
        $parts = ['reward_report'];

        if ($this->getYear()) {
            $parts[] = $this->getYear();
        }

        if ($this->getMonth()) {
            $parts[] = str_pad((string) $this->getMonth(), 2, '0', STR_PAD_LEFT);
        }

        return implode('_', $parts).'.xlsx';
    }

    /**
     * Human readable label for a reward's `role` column (production / pm / vj).
     *
     * @param  string|null  $role  The stored reward role.
     * @return string The label shown in the report, or '-' when the role is empty.
     */
    protected function rewardRoleLabel(?string $role): string
    {
        return match ($role) {
            'production' => 'Production',
            'pm' => 'Project Manager',
            'vj' => 'VJ',
            default => $role ? ucfirst($role) : '-',
        };
    }

    /**
     * Build the three worksheet definitions for the reward disbursement report:
     *   - Summary: one row per project with its total reward and total cost (plus a grand total row),
     *     with a filterable (auto-filter) header over the project rows.
     *   - Reward Detail: one row per reward line (employee x project x role) supporting each total.
     *   - Disbursement: one row per employee with the total amount HR must pay out (plus a total row).
     *
     * All money values are left as real numbers; the Indonesian "1.000.000" grouping is applied via
     * each sheet's `formats` (a column => {@see IDR_NUMBER_FORMAT} map), not by pre-formatting strings.
     *
     * @param  Collection<int, Project>  $projects  The filtered projects with their reward relations.
     * @return array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>
     *                                                                                                             The ordered sheet definitions consumed by {@see ProjectRewardReportExport}.
     */
    protected function buildRewardSheets(Collection $projects): array
    {
        $summaryRows = [];
        $detailRows = [];
        $perEmployee = [];

        $summaryRewardTotal = 0.0;
        $summaryCostTotal = 0.0;

        $no = 1;
        foreach ($projects as $project) {
            $projectDate = date('Y-m-d', strtotime($project->project_date));
            $className = $project->projectClass?->name ?? '-';
            // (float) guarantees a numeric 0 (never a blank cell) for a project with no reward records.
            $projectReward = (float) $project->rewards->sum('total_reward');
            // Total cost currently equals the employee reward total, because employee_reward is the
            // only cost component tracked; it will diverge once ai_cost is added to cost_items.
            $projectCost = $projectReward;

            $summaryRewardTotal += $projectReward;
            $summaryCostTotal += $projectCost;

            $summaryRows[] = [
                $no++,
                $project->name,
                $projectDate,
                $className,
                $this->formatProjectPics($project) ?: '-',
                $project->rewards->count(),
                $projectReward,
                $projectCost,
            ];

            foreach ($project->rewards as $reward) {
                $employee = $reward->employee;
                $employeeCode = $employee?->employee_id ?? '-';
                $employeeName = $employee?->name ?? '-';
                $position = $employee?->position?->name ?? '-';
                $amount = (float) ($reward->total_reward ?? 0);

                $detailRows[] = [
                    $employeeCode,
                    $employeeName,
                    $position,
                    $this->rewardRoleLabel($reward->role),
                    $project->name,
                    $className,
                    $projectDate,
                    (int) ($reward->total_point ?? 0),
                    $amount,
                ];

                $key = $employee?->id ?? $employeeCode;
                if (! isset($perEmployee[$key])) {
                    $perEmployee[$key] = [
                        'code' => $employeeCode,
                        'name' => $employeeName,
                        'position' => $position,
                        'projects' => 0,
                        'total_reward' => 0.0,
                    ];
                }
                $perEmployee[$key]['projects']++;
                $perEmployee[$key]['total_reward'] += $amount;
            }
        }

        $summaryRows[] = ['', '', '', '', '', 'TOTAL', $summaryRewardTotal, $summaryCostTotal];

        $disbursementRows = [];
        $disbursementTotal = 0.0;
        $no = 1;
        foreach ($perEmployee as $employee) {
            $disbursementTotal += $employee['total_reward'];
            $disbursementRows[] = [
                $no++,
                $employee['code'],
                $employee['name'],
                $employee['position'],
                $employee['projects'],
                (float) $employee['total_reward'],
            ];
        }
        $disbursementRows[] = ['', '', '', '', 'TOTAL', $disbursementTotal];

        $summaryHeadings = ['No', 'Project', 'Event Date', 'Event Class', 'PIC', 'Rewarded Employees', 'Total Reward (IDR)', 'Total Cost (IDR)'];
        // Filterable header covering the heading row and every project row, but not the grand total
        // row (the last entry in $summaryRows), so filtering never hides the total. count($summaryRows)
        // = project rows + 1 total, which equals the last data row (heading row 1 + project rows).
        $summaryLastColumn = Coordinate::stringFromColumnIndex(count($summaryHeadings));
        $summaryAutoFilter = 'A1:'.$summaryLastColumn.count($summaryRows);

        return [
            [
                'title' => 'Summary',
                'headings' => $summaryHeadings,
                'rows' => $summaryRows,
                // Total Reward (G) and Total Cost (H) grouped "1.000.000" style.
                'formats' => ['G' => self::IDR_NUMBER_FORMAT, 'H' => self::IDR_NUMBER_FORMAT],
                'autoFilter' => $summaryAutoFilter,
            ],
            [
                'title' => 'Reward Detail',
                'headings' => ['Employee ID', 'Employee', 'Position', 'Reward Role', 'Project', 'Event Class', 'Event Date', 'Total Point', 'Reward (IDR)'],
                'rows' => $detailRows,
                // Reward (I) grouped "1.000.000" style.
                'formats' => ['I' => self::IDR_NUMBER_FORMAT],
            ],
            [
                'title' => 'Disbursement',
                'headings' => ['No', 'Employee ID', 'Employee', 'Position', 'Projects', 'Total Reward (IDR)'],
                'rows' => $disbursementRows,
                // Total Reward (F) grouped "1.000.000" style.
                'formats' => ['F' => self::IDR_NUMBER_FORMAT],
            ],
        ];
    }

    /**
     * Export the employee reward report as a multi-sheet Excel workbook for management and HR.
     *
     * The report is built fresh (not from the dashboard cache) from the projects matching the current
     * request filters (year, month, event_class, finalization, search) and is returned as a direct
     * download. `cost_items` currently only supports `employee_reward`.
     *
     * @return BinaryFileResponse The streamed `.xlsx` download.
     */
    public function export(): BinaryFileResponse
    {
        $projects = $this->repo->list(
            select: $this->projectColumns(),
            relation: $this->exportRelations(),
            where: $this->exportConditions(),
            orderBy: 'project_date ASC'
        );

        $export = new ProjectRewardReportExport($this->buildRewardSheets($projects));

        return Excel::download($export, $this->exportFileName());
    }
}
