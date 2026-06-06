<?php

namespace App\Services;

use App\Enums\ErrorCode\Code;
use App\Models\Mcp\McpAccessLog;
use Illuminate\Database\Eloquent\Builder;

class McpAccessLogService
{
    /**
     * Paginated, filterable list of MCP access logs.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length');
            $page = request('page') ?? 1;
            $page = $page == 1 ? 0 : $page;
            $page = $page > 0 ? $page * $itemsPerPage - $itemsPerPage : 0;

            $logs = $this->baseQuery($filters)
                ->orderByDesc('accessed_at')
                ->skip($page)
                ->take($itemsPerPage)
                ->get();

            $paginated = $logs->map(fn (McpAccessLog $log) => [
                'id' => $log->id,
                'source' => $log->source,
                'user_id' => $log->user_id,
                'user_email' => $log->user_email,
                'user_name' => $log->user_name,
                'method' => $log->method,
                'route_uri' => $log->route_uri,
                'route_name' => $log->route_name,
                'status_code' => $log->status_code,
                'is_success' => $log->is_success,
                'parameters' => $log->parameters,
                'response_message' => $log->response_message,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'duration_ms' => $log->duration_ms,
                'accessed_at' => $log->accessed_at?->format('Y-m-d H:i:s'),
            ])->toArray();

            $totalData = $this->baseQuery($filters)->count();

            return generalResponse(
                'Success',
                false,
                [
                    'paginated' => $paginated,
                    'totalData' => $totalData,
                ],
            );
        } catch (\Throwable $th) {
            return generalResponse(
                errorMessage($th),
                true,
                [],
                Code::BadRequest->value,
            );
        }
    }

    /**
     * Single log detail.
     */
    public function detail(int $id): ?McpAccessLog
    {
        return McpAccessLog::query()->find($id);
    }

    /**
     * High level totals used for dashboard cards.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $base = $this->baseQuery($filters);

        return [
            'total' => (clone $base)->count(),
            'success' => (clone $base)->where('is_success', true)->count(),
            'failed' => (clone $base)->where('is_success', false)->count(),
            'unique_users' => (clone $base)->distinct('user_id')->count('user_id'),
            'today' => (clone $base)->whereDate('accessed_at', now()->toDateString())->count(),
            'this_month' => (clone $base)
                ->whereYear('accessed_at', now()->year)
                ->whereMonth('accessed_at', now()->month)
                ->count(),
            'this_year' => (clone $base)->whereYear('accessed_at', now()->year)->count(),
        ];
    }

    /**
     * Usage count grouped by day, month, or year.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function usage(array $filters): array
    {
        $period = $filters['period'] ?? 'day';

        return $this->baseQuery($filters)
            ->selectRaw($this->periodExpression($period).' as period')
            ->selectRaw('count(*) as total')
            ->selectRaw($this->successCountExpression().' as success')
            ->selectRaw($this->failedCountExpression().' as failed')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'total' => (int) $row->total,
                'success' => (int) $row->success,
                'failed' => (int) $row->failed,
            ])
            ->toArray();
    }

    /**
     * Usage broken down per route (most used endpoints).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function usageByRoute(array $filters): array
    {
        return $this->baseQuery($filters)
            ->selectRaw('route_uri')
            ->selectRaw('method')
            ->selectRaw('count(*) as total')
            ->selectRaw($this->successCountExpression().' as success')
            ->selectRaw($this->failedCountExpression().' as failed')
            ->groupBy('route_uri', 'method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'route_uri' => $row->route_uri,
                'method' => $row->method,
                'total' => (int) $row->total,
                'success' => (int) $row->success,
                'failed' => (int) $row->failed,
            ])
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<McpAccessLog>
     */
    private function baseQuery(array $filters): Builder
    {
        return McpAccessLog::query()
            ->when(! empty($filters['source']), fn (Builder $q) => $q->where('source', $filters['source']))
            ->when(! empty($filters['user_id']), fn (Builder $q) => $q->where('user_id', $filters['user_id']))
            ->when(! empty($filters['route']), fn (Builder $q) => $q->where('route_uri', 'like', '%'.$filters['route'].'%'))
            ->when(! empty($filters['method']), fn (Builder $q) => $q->where('method', strtoupper($filters['method'])))
            ->when(isset($filters['is_success']) && $filters['is_success'] !== null && $filters['is_success'] !== '', function (Builder $q) use ($filters) {
                $q->where('is_success', filter_var($filters['is_success'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->whereDate('accessed_at', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->whereDate('accessed_at', '<=', $filters['end_date']))
            ->when(! empty($filters['search']), function (Builder $q) use ($filters) {
                $search = $filters['search'];
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('user_email', 'like', '%'.$search.'%')
                        ->orWhere('user_name', 'like', '%'.$search.'%')
                        ->orWhere('route_uri', 'like', '%'.$search.'%');
                });
            });
    }

    /**
     * Database-agnostic expression that formats accessed_at into a groupable bucket.
     */
    private function periodExpression(string $period): string
    {
        $driver = McpAccessLog::query()->getConnection()->getDriverName();

        $patterns = match ($period) {
            'year' => ['mysql' => '%Y', 'sqlite' => '%Y', 'pgsql' => 'YYYY'],
            'month' => ['mysql' => '%Y-%m', 'sqlite' => '%Y-%m', 'pgsql' => 'YYYY-MM'],
            default => ['mysql' => '%Y-%m-%d', 'sqlite' => '%Y-%m-%d', 'pgsql' => 'YYYY-MM-DD'],
        };

        return match ($driver) {
            'sqlite' => "strftime('".$patterns['sqlite']."', accessed_at)",
            'pgsql' => "to_char(accessed_at, '".$patterns['pgsql']."')",
            default => "DATE_FORMAT(accessed_at, '".$patterns['mysql']."')",
        };
    }

    private function successCountExpression(): string
    {
        return 'SUM(CASE WHEN is_success THEN 1 ELSE 0 END)';
    }

    private function failedCountExpression(): string
    {
        return 'SUM(CASE WHEN is_success THEN 0 ELSE 1 END)';
    }
}
