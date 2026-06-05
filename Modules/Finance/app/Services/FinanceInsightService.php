<?php

namespace Modules\Finance\Services;

use App\Enums\Finance\RefundStatus;
use App\Enums\Production\ProjectDealStatus;
use App\Enums\System\BaseRole;
use App\Enums\Transaction\InvoiceStatus;
use App\Enums\Transaction\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ProjectDealRefund;
use Modules\Finance\Models\Transaction;
use Modules\Production\Models\ProjectDeal;
use Modules\Production\Models\ProjectDealMarketing;
use Modules\Production\Models\ProjectQuotation;

class FinanceInsightService
{
    /**
     * Access tiers ordered from the most privileged to the least.
     */
    private const TIER_EXECUTIVE = 'executive';

    private const TIER_FINANCE = 'finance';

    private const TIER_MARKETING = 'marketing';

    /**
     * Sections each tier is allowed to read.
     *
     * @var array<string, list<string>>
     */
    private const TIER_SECTIONS = [
        self::TIER_EXECUTIVE => [
            'overview',
            'profitability',
            'monthly_trend',
            'receivables',
            'refunds',
            'marketing_performance',
            'top_deals',
            'payment_status',
        ],
        self::TIER_FINANCE => [
            'overview',
            'monthly_trend',
            'receivables',
            'refunds',
            'top_deals',
            'payment_status',
        ],
        self::TIER_MARKETING => [
            'overview',
            'monthly_trend',
            'top_deals',
            'payment_status',
        ],
    ];

    /**
     * Resolve the finance access of the authenticated user based on their ROLE
     * (not the MCP permission). Returns null when the user has no finance access.
     *
     * @return array{tier: string, role: string, scope_deal_ids: ?list<int>, scope_label: string, sections: list<string>}|null
     */
    public function resolveAccess(?User $user = null): ?array
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->hasRole([BaseRole::Director->value, BaseRole::Root->value])) {
            return [
                'tier' => self::TIER_EXECUTIVE,
                'role' => $user->getRoleNames()->first() ?? BaseRole::Director->value,
                'scope_deal_ids' => null,
                'scope_label' => 'company_wide',
                'sections' => self::TIER_SECTIONS[self::TIER_EXECUTIVE],
            ];
        }

        if ($user->hasRole(BaseRole::Finance->value)) {
            return [
                'tier' => self::TIER_FINANCE,
                'role' => BaseRole::Finance->value,
                'scope_deal_ids' => null,
                'scope_label' => 'company_wide',
                'sections' => self::TIER_SECTIONS[self::TIER_FINANCE],
            ];
        }

        if ($user->hasRole(BaseRole::Marketing->value)) {
            return [
                'tier' => self::TIER_MARKETING,
                'role' => BaseRole::Marketing->value,
                'scope_deal_ids' => $this->marketingDealIds((int) $user->employee_id),
                'scope_label' => 'own_deals',
                'sections' => self::TIER_SECTIONS[self::TIER_MARKETING],
            ];
        }

        return null;
    }

    /**
     * Comprehensive, role-aware finance insight bundle.
     *
     * @return array<string, mixed>
     */
    public function getInsight(): array
    {
        try {
            $access = $this->resolveAccess();
            if (! $access) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            $dealIds = $access['scope_deal_ids'];
            $isExecutive = $access['tier'] === self::TIER_EXECUTIVE;
            $canSeeExpense = $access['tier'] !== self::TIER_MARKETING;
            $period = $this->resolvePeriod();

            $data = [
                'access' => [
                    'tier' => $access['tier'],
                    'role' => $access['role'],
                    'scope' => $access['scope_label'],
                    'generated_at' => Carbon::now()->toDateTimeString(),
                ],
                'period' => $this->periodMeta($period),
            ];

            $sections = $access['sections'];

            if (in_array('overview', $sections, true)) {
                $data['overview'] = $this->buildOverview($dealIds, $canSeeExpense, $period);
            }

            if (in_array('profitability', $sections, true)) {
                $data['profitability'] = $this->buildProfitability($dealIds, $period);
            }

            if (in_array('monthly_trend', $sections, true)) {
                $data['monthly_trend'] = $this->buildMonthlyTrend($dealIds, $canSeeExpense, $period);
            }

            if (in_array('receivables', $sections, true)) {
                $data['receivables'] = $this->buildReceivables($dealIds);
            }

            if (in_array('refunds', $sections, true)) {
                $data['refunds'] = $this->buildRefunds($dealIds, $period);
            }

            if ($isExecutive && in_array('marketing_performance', $sections, true)) {
                $data['marketing_performance'] = $this->buildMarketingPerformance($period);
            }

            if (in_array('top_deals', $sections, true)) {
                $data['top_deals'] = $this->buildTopDeals($dealIds, 10, $period);
            }

            if (in_array('payment_status', $sections, true)) {
                $data['payment_status'] = $this->buildPaymentStatus($dealIds);
            }

            return generalResponse(message: 'Success', data: $data);
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Outstanding receivables drill-down (executive & finance only).
     *
     * @return array<string, mixed>
     */
    public function getReceivables(): array
    {
        return $this->guardedSection('receivables', fn (?array $dealIds) => $this->buildReceivables($dealIds));
    }

    /**
     * Marketing performance leaderboard drill-down (executive only).
     *
     * @return array<string, mixed>
     */
    public function getMarketingPerformance(): array
    {
        return $this->guardedSection('marketing_performance', fn () => $this->buildMarketingPerformance($this->resolvePeriod()));
    }

    /**
     * Top revenue deals drill-down (scope follows the role).
     *
     * @return array<string, mixed>
     */
    public function getTopDeals(): array
    {
        return $this->guardedSection('top_deals', fn (?array $dealIds) => $this->buildTopDeals($dealIds, (int) (request('limit') ?? 10), $this->resolvePeriod()));
    }

    /**
     * Resolve access, ensure the section is permitted, then run the builder.
     *
     * @param  callable(?list<int>): array<string, mixed>  $builder
     * @return array<string, mixed>
     */
    private function guardedSection(string $section, callable $builder): array
    {
        try {
            $access = $this->resolveAccess();
            if (! $access) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            if (! in_array($section, $access['sections'], true)) {
                return errorResponse(__('global.forbidden'), code: 403);
            }

            return generalResponse(message: 'Success', data: $builder($access['scope_deal_ids']));
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Project deal ids where the given employee is an assigned marketing.
     *
     * @return list<int>
     */
    private function marketingDealIds(int $employeeId): array
    {
        if ($employeeId === 0) {
            return [];
        }

        return ProjectDealMarketing::query()
            ->where('employee_id', $employeeId)
            ->pluck('project_deal_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve a reporting period from the request query parameters.
     *
     * Supported (in priority order):
     *  - start_date + end_date                                  -> custom day range
     *  - start_year + start_month + end_year + end_month        -> month-year range
     *  - year + month                                           -> a single month
     *  - year                                                   -> a whole year
     *  - (none)                                                 -> null (callers fall back to their defaults)
     *
     * @return array{start: Carbon, end: Carbon, label: string, mode: string}|null
     */
    private function resolvePeriod(): ?array
    {
        $startDate = request('start_date');
        $endDate = request('end_date');
        $year = request('year');
        $month = request('month');
        $startYear = request('start_year');
        $startMonth = request('start_month');
        $endYear = request('end_year');
        $endMonth = request('end_month');

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $label = $start->format('d M Y').' - '.$end->format('d M Y');

            return $this->orderedPeriod($start, $end, $label, 'custom_range');
        }

        if ($startYear && $startMonth && $endYear && $endMonth) {
            $start = Carbon::createFromDate((int) $startYear, (int) $startMonth, 1)->startOfMonth();
            $end = Carbon::createFromDate((int) $endYear, (int) $endMonth, 1)->endOfMonth();

            return $this->orderedPeriod($start, $end, $start->format('M Y').' - '.$end->format('M Y'), 'month_range');
        }

        if ($year && $month) {
            $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            return $this->orderedPeriod($start, $end, $start->format('F Y'), 'month');
        }

        if ($year) {
            $start = Carbon::createFromDate((int) $year, 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();

            return $this->orderedPeriod($start, $end, $start->format('Y'), 'year');
        }

        return null;
    }

    /**
     * Build a period array, swapping the bounds if they were supplied reversed.
     *
     * @return array{start: Carbon, end: Carbon, label: string, mode: string}
     */
    private function orderedPeriod(Carbon $start, Carbon $end, string $label, string $mode): array
    {
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return ['start' => $start, 'end' => $end, 'label' => $label, 'mode' => $mode];
    }

    /**
     * Period descriptor returned in the payload.
     *
     * @param  array{start: Carbon, end: Carbon, label: string, mode: string}|null  $period
     * @return array<string, mixed>
     */
    private function periodMeta(?array $period): array
    {
        if (! $period) {
            return [
                'mode' => 'default',
                'label' => 'All-time (current month for monthly KPIs)',
                'start' => null,
                'end' => null,
                'is_filtered' => false,
            ];
        }

        return [
            'mode' => $period['mode'],
            'label' => $period['label'],
            'start' => $period['start']->toDateString(),
            'end' => $period['end']->toDateString(),
            'is_filtered' => true,
        ];
    }

    /**
     * Convert a period descriptor into a [start, end] range, or null.
     *
     * @param  array{start: Carbon, end: Carbon, label: string, mode: string}|null  $period
     * @return ?array{0: Carbon, 1: Carbon}
     */
    private function periodRange(?array $period): ?array
    {
        return $period ? [$period['start'], $period['end']] : null;
    }

    /**
     * Headline KPIs for the selected period (defaults to the current month) with
     * growth vs the equal-length preceding window.
     *
     * @param  ?list<int>  $dealIds
     * @param  array{start: Carbon, end: Carbon, label: string, mode: string}|null  $period
     * @return array<string, mixed>
     */
    private function buildOverview(?array $dealIds, bool $canSeeExpense, ?array $period): array
    {
        if ($period) {
            [$currentStart, $currentEnd] = [$period['start'], $period['end']];
            $lengthSeconds = $currentStart->diffInSeconds($currentEnd);
            $prevEnd = $currentStart->copy()->subSecond();
            $prevStart = $prevEnd->copy()->subSeconds($lengthSeconds);
            $label = $period['label'];
        } else {
            $now = Carbon::now();
            [$currentStart, $currentEnd] = [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            $prev = $now->copy()->subMonth();
            [$prevStart, $prevEnd] = [$prev->copy()->startOfMonth(), $prev->copy()->endOfMonth()];
            $label = $now->format('F Y');
        }

        $currentIncome = $this->incomeSum($dealIds, [$currentStart, $currentEnd]);
        $prevIncome = $this->incomeSum($dealIds, [$prevStart, $prevEnd]);

        $overview = [
            'period' => $label,
            'income' => $currentIncome,
            'income_previous' => $prevIncome,
            'income_growth_percent' => $this->growth($currentIncome, $prevIncome),
            'transaction_count' => $this->incomeCount($dealIds, [$currentStart, $currentEnd]),
            'collected_all_time' => $this->incomeSum($dealIds, null),
            'booked' => $this->bookedRevenue($dealIds, $period),
            'total_outstanding' => $this->outstandingSum($dealIds),
        ];

        if ($canSeeExpense) {
            $currentRefund = $this->refundSum($dealIds, [$currentStart, $currentEnd]);
            $prevRefund = $this->refundSum($dealIds, [$prevStart, $prevEnd]);
            $overview['refunds'] = $currentRefund;
            $overview['refund_growth_percent'] = $this->growth($currentRefund, $prevRefund);
            $overview['net'] = $currentIncome - $currentRefund;
        }

        return $overview;
    }

    /**
     * Profitability / efficiency ratios (executive only).
     *
     * @param  ?list<int>  $dealIds
     * @return array<string, mixed>
     */
    private function buildProfitability(?array $dealIds, ?array $period = null): array
    {
        $range = $this->periodRange($period);
        $booked = $this->bookedRevenue($dealIds, $period);
        $collected = $this->incomeSum($dealIds, $range);
        $outstanding = $this->outstandingSum($dealIds);
        $refunded = $this->refundSum($dealIds, $range);
        $finalDeals = $this->finalDealCount($dealIds, $period);

        return [
            'total_booked' => $booked,
            'total_collected' => $collected,
            'total_outstanding' => $outstanding,
            'total_refunded' => $refunded,
            'net_collected' => $collected - $refunded,
            'collection_rate_percent' => $this->ratio($collected, $booked),
            'refund_rate_percent' => $this->ratio($refunded, $collected),
            'final_deal_count' => $finalDeals,
            'average_deal_value' => $finalDeals > 0 ? round($booked / $finalDeals, 2) : 0.0,
        ];
    }

    /**
     * Income (and optionally outcome) per month for the last 12 months.
     *
     * @param  ?list<int>  $dealIds
     * @return list<array<string, mixed>>
     */
    private function buildMonthlyTrend(?array $dealIds, bool $canSeeExpense, ?array $period = null): array
    {
        $start = $period ? $period['start']->copy()->startOfMonth() : Carbon::now()->subMonths(11)->startOfMonth();
        $end = $period ? $period['end']->copy()->endOfMonth() : Carbon::now()->endOfMonth();

        $incomeByMonth = $this->scopeDeals(
            Transaction::query()
                ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month_key, SUM(payment_amount) as total")
                ->where('debit_credit', 'debit')
                ->whereBetween('transaction_date', [$start, $end])
                ->groupByRaw("DATE_FORMAT(transaction_date, '%Y-%m')"),
            $dealIds
        )->pluck('total', 'month_key');

        $outcomeByMonth = collect();
        if ($canSeeExpense) {
            $outcomeByMonth = $this->scopeDeals(
                Transaction::query()
                    ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as month_key, SUM(payment_amount) as total")
                    ->where('transaction_type', TransactionType::Refund->value)
                    ->whereBetween('transaction_date', [$start, $end])
                    ->groupByRaw("DATE_FORMAT(transaction_date, '%Y-%m')"),
                $dealIds
            )->pluck('total', 'month_key');
        }

        $rows = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');

            $row = [
                'month' => $cursor->format('M Y'),
                'income' => (float) ($incomeByMonth[$key] ?? 0),
            ];

            if ($canSeeExpense) {
                $row['outcome'] = (float) ($outcomeByMonth[$key] ?? 0);
            }

            $rows[] = $row;
            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * Outstanding invoice aging buckets.
     *
     * @param  ?list<int>  $dealIds
     * @return array<string, mixed>
     */
    private function buildReceivables(?array $dealIds): array
    {
        $today = Carbon::today()->toDateString();
        $in7 = Carbon::today()->addDays(7)->toDateString();
        $in30 = Carbon::today()->addDays(30)->toDateString();

        $base = fn () => $this->scopeDeals(
            Invoice::query()->where('status', InvoiceStatus::Unpaid->value),
            $dealIds
        );

        return [
            'total_outstanding' => (float) $base()->sum('amount'),
            'outstanding_count' => (int) $base()->count(),
            'overdue_amount' => (float) $base()->whereDate('payment_due', '<', $today)->sum('amount'),
            'overdue_count' => (int) $base()->whereDate('payment_due', '<', $today)->count(),
            'due_within_7_days' => (float) $base()->whereBetween('payment_due', [$today, $in7])->sum('amount'),
            'due_within_30_days' => (float) $base()->whereBetween('payment_due', [$today, $in30])->sum('amount'),
            'aging' => [
                'overdue_1_30' => (float) $base()
                    ->whereDate('payment_due', '<', $today)
                    ->whereDate('payment_due', '>=', Carbon::today()->subDays(30)->toDateString())
                    ->sum('amount'),
                'overdue_31_60' => (float) $base()
                    ->whereDate('payment_due', '<', Carbon::today()->subDays(30)->toDateString())
                    ->whereDate('payment_due', '>=', Carbon::today()->subDays(60)->toDateString())
                    ->sum('amount'),
                'overdue_61_90' => (float) $base()
                    ->whereDate('payment_due', '<', Carbon::today()->subDays(60)->toDateString())
                    ->whereDate('payment_due', '>=', Carbon::today()->subDays(90)->toDateString())
                    ->sum('amount'),
                'overdue_90_plus' => (float) $base()
                    ->whereDate('payment_due', '<', Carbon::today()->subDays(90)->toDateString())
                    ->sum('amount'),
            ],
        ];
    }

    /**
     * Refund summary (executive & finance only).
     *
     * @param  ?list<int>  $dealIds
     * @return array<string, mixed>
     */
    private function buildRefunds(?array $dealIds, ?array $period = null): array
    {
        $range = $this->periodRange($period);

        // Paid refunds within the selected period (by created_at), or all-time when unfiltered.
        $paidBase = fn () => $this->applyDateRange(
            $this->scopeDeals(
                ProjectDealRefund::query()->where('status', RefundStatus::Paid->value),
                $dealIds
            ),
            $range,
            'created_at'
        );

        // Pending refunds are current-state and not period-bound.
        $pendingBase = fn () => $this->scopeDeals(
            ProjectDealRefund::query()->where('status', RefundStatus::Pending->value),
            $dealIds
        );

        $periodRefund = $range
            ? $this->refundSum($dealIds, $range)
            : $this->refundSum($dealIds, [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);

        return [
            'total_refunded' => (float) $paidBase()->sum('refund_amount'),
            'refunded_count' => (int) $paidBase()->count(),
            'pending_amount' => (float) $pendingBase()->sum('refund_amount'),
            'pending_count' => (int) $pendingBase()->count(),
            'refunded_in_period' => $periodRefund,
        ];
    }

    /**
     * Per-marketing leaderboard (executive only). Full deal value is attributed
     * to every marketing assigned to the deal.
     *
     * @return list<array<string, mixed>>
     */
    private function buildMarketingPerformance(?array $period = null): array
    {
        $range = $this->periodRange($period);

        $bookedQuery = ProjectQuotation::query()
            ->where('is_final', 1)
            ->selectRaw('project_deal_id, SUM(fix_price) as total')
            ->groupBy('project_deal_id');
        if ($period) {
            $bookedQuery->whereHas('deal', fn ($d) => $d->whereBetween('project_date', [$period['start']->toDateString(), $period['end']->toDateString()]));
        }
        $bookedPerDeal = $bookedQuery->pluck('total', 'project_deal_id');

        $collectedPerDeal = $this->applyDateRange(
            Transaction::query()
                ->where('debit_credit', 'debit')
                ->selectRaw('project_deal_id, SUM(payment_amount) as total')
                ->groupBy('project_deal_id'),
            $range,
            'transaction_date'
        )->pluck('total', 'project_deal_id');

        $outstandingPerDeal = Invoice::query()
            ->where('status', InvoiceStatus::Unpaid->value)
            ->selectRaw('project_deal_id, SUM(amount) as total')
            ->groupBy('project_deal_id')
            ->pluck('total', 'project_deal_id');

        $assignments = ProjectDealMarketing::query()
            ->with('employee:id,name,nickname')
            ->get()
            ->groupBy('employee_id');

        return $assignments->map(function ($rows) use ($bookedPerDeal, $collectedPerDeal, $outstandingPerDeal) {
            $employee = $rows->first()->employee;
            $dealIds = $rows->pluck('project_deal_id')->unique();

            $booked = (float) $dealIds->sum(fn ($id) => (float) ($bookedPerDeal[$id] ?? 0));
            $collected = (float) $dealIds->sum(fn ($id) => (float) ($collectedPerDeal[$id] ?? 0));
            $outstanding = (float) $dealIds->sum(fn ($id) => (float) ($outstandingPerDeal[$id] ?? 0));

            return [
                'employee_id' => $employee?->id,
                'name' => $employee?->nickname ?? $employee?->name ?? '-',
                'deal_count' => $dealIds->count(),
                'total_booked' => $booked,
                'total_collected' => $collected,
                'total_outstanding' => $outstanding,
                'collection_rate_percent' => $this->ratio($collected, $booked),
            ];
        })
            ->sortByDesc('total_collected')
            ->values()
            ->all();
    }

    /**
     * Highest revenue deals by amount collected.
     *
     * @param  ?list<int>  $dealIds
     * @return list<array<string, mixed>>
     */
    private function buildTopDeals(?array $dealIds, int $limit = 10, ?array $period = null): array
    {
        $collectedPerDeal = $this->applyDateRange(
            $this->scopeDeals(
                Transaction::query()
                    ->where('debit_credit', 'debit')
                    ->selectRaw('project_deal_id, SUM(payment_amount) as total')
                    ->groupBy('project_deal_id')
                    ->orderByDesc('total')
                    ->limit($limit),
                $dealIds
            ),
            $this->periodRange($period),
            'transaction_date'
        )->pluck('total', 'project_deal_id');

        if ($collectedPerDeal->isEmpty()) {
            return [];
        }

        $deals = ProjectDeal::query()
            ->whereIn('id', $collectedPerDeal->keys())
            ->with([
                'customer:id,name',
                'finalQuotation:id,project_deal_id,fix_price',
            ])
            ->get()
            ->keyBy('id');

        return $collectedPerDeal->map(function ($collected, $dealId) use ($deals) {
            $deal = $deals[$dealId] ?? null;
            $booked = (float) ($deal?->finalQuotation?->fix_price ?? 0);
            $collected = (float) $collected;

            return [
                'project_deal_id' => (int) $dealId,
                'name' => $deal?->name ?? '-',
                'customer' => $deal?->customer?->name ?? '-',
                'event_type' => $deal?->event_type?->label() ?? '-',
                'status' => $deal?->status?->label() ?? '-',
                'total_booked' => $booked,
                'total_collected' => $collected,
                'outstanding' => max($booked - $collected, 0),
            ];
        })->values()->all();
    }

    /**
     * Count and value of final deals grouped by payment status.
     *
     * @param  ?list<int>  $dealIds
     * @return array<string, mixed>
     */
    private function buildPaymentStatus(?array $dealIds): array
    {
        $deals = $this->scopeDeals(
            ProjectDeal::query()
                ->where('status', ProjectDealStatus::Final->value)
                ->withCount('transactions')
                ->with('finalQuotation:id,project_deal_id,fix_price'),
            $dealIds,
            'id'
        )->get();

        $buckets = [
            'paid' => ['count' => 0, 'amount' => 0.0],
            'partial' => ['count' => 0, 'amount' => 0.0],
            'unpaid' => ['count' => 0, 'amount' => 0.0],
        ];

        foreach ($deals as $deal) {
            $value = (float) ($deal->finalQuotation?->fix_price ?? 0);

            if ($deal->is_fully_paid) {
                $key = 'paid';
            } elseif ($deal->transactions_count > 0) {
                $key = 'partial';
            } else {
                $key = 'unpaid';
            }

            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += $value;
        }

        return $buckets;
    }

    /**
     * Apply the deal-id scope to a query when the role is restricted.
     *
     * @param  ?list<int>  $dealIds
     */
    private function scopeDeals(Builder $query, ?array $dealIds, string $column = 'project_deal_id'): Builder
    {
        if ($dealIds !== null) {
            $query->whereIn($column, $dealIds);
        }

        return $query;
    }

    /**
     * Sum of income (debit) transactions, optionally within a date range.
     *
     * @param  ?list<int>  $dealIds
     * @param  ?array{0: Carbon, 1: Carbon}  $range
     */
    private function incomeSum(?array $dealIds, ?array $range): float
    {
        $query = $this->scopeDeals(
            Transaction::query()->where('debit_credit', 'debit'),
            $dealIds
        );

        if ($range) {
            $query->whereBetween('transaction_date', [$range[0], $range[1]]);
        }

        return (float) $query->sum('payment_amount');
    }

    /**
     * @param  ?list<int>  $dealIds
     * @param  ?array{0: Carbon, 1: Carbon}  $range
     */
    private function incomeCount(?array $dealIds, ?array $range): int
    {
        $query = $this->scopeDeals(
            Transaction::query()->where('debit_credit', 'debit'),
            $dealIds
        );

        if ($range) {
            $query->whereBetween('transaction_date', [$range[0], $range[1]]);
        }

        return (int) $query->count();
    }

    /**
     * Sum of refund transactions, optionally within a date range.
     *
     * @param  ?list<int>  $dealIds
     * @param  ?array{0: Carbon, 1: Carbon}  $range
     */
    private function refundSum(?array $dealIds, ?array $range): float
    {
        $query = $this->scopeDeals(
            Transaction::query()->where('transaction_type', TransactionType::Refund->value),
            $dealIds
        );

        if ($range) {
            $query->whereBetween('transaction_date', [$range[0], $range[1]]);
        }

        return (float) $query->sum('payment_amount');
    }

    /**
     * Total booked value (final quotations of final deals).
     *
     * When a period is given, only deals whose project_date falls in the period count.
     *
     * @param  ?list<int>  $dealIds
     * @param  array{start: Carbon, end: Carbon, label: string, mode: string}|null  $period
     */
    private function bookedRevenue(?array $dealIds, ?array $period = null): float
    {
        return (float) $this->scopeDeals(
            ProjectQuotation::query()
                ->where('is_final', 1)
                ->whereHas('deal', function ($q) use ($period) {
                    $q->where('status', ProjectDealStatus::Final->value);
                    if ($period) {
                        $q->whereBetween('project_date', [$period['start']->toDateString(), $period['end']->toDateString()]);
                    }
                }),
            $dealIds
        )->sum('fix_price');
    }

    /**
     * Total outstanding (unpaid invoice amounts).
     *
     * @param  ?list<int>  $dealIds
     */
    private function outstandingSum(?array $dealIds): float
    {
        return (float) $this->scopeDeals(
            Invoice::query()->where('status', InvoiceStatus::Unpaid->value),
            $dealIds
        )->sum('amount');
    }

    /**
     * @param  ?list<int>  $dealIds
     * @param  array{start: Carbon, end: Carbon, label: string, mode: string}|null  $period
     */
    private function finalDealCount(?array $dealIds, ?array $period = null): int
    {
        $query = $this->scopeDeals(
            ProjectDeal::query()->where('status', ProjectDealStatus::Final->value),
            $dealIds,
            'id'
        );

        if ($period) {
            $query->whereBetween('project_date', [$period['start']->toDateString(), $period['end']->toDateString()]);
        }

        return (int) $query->count();
    }

    /**
     * Apply a [start, end] date range to a query on the given column, when present.
     *
     * @param  ?array{0: Carbon, 1: Carbon}  $range
     */
    private function applyDateRange(Builder $query, ?array $range, string $column): Builder
    {
        if ($range) {
            $query->whereBetween($column, [$range[0], $range[1]]);
        }

        return $query;
    }

    /**
     * Month-over-month growth percentage.
     */
    private function growth(float $current, float $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 2);
        }

        return $current > 0 ? 100.0 : 0.0;
    }

    /**
     * Ratio of part to whole as a percentage.
     */
    private function ratio(float $part, float $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
    }
}
