<?php

namespace App\Services;

use App\Enums\ErrorCode\Code;
use App\Enums\System\BaseRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

class AuthenticationLogService
{
    /**
     * Roles permitted to view authentication logs.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROLES = [
        BaseRole::Root->value,
        BaseRole::Director->value,
        BaseRole::ItSupport->value,
    ];

    /**
     * Whether the current user may read authentication logs.
     */
    public function isAllowed(): bool
    {
        return auth()->user()?->hasRole(self::ALLOWED_ROLES) ?? false;
    }

    /**
     * Paginated, filterable list of authentication logs.
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
                ->orderByDesc('login_at')
                ->skip($page)
                ->take($itemsPerPage)
                ->get();

            $users = $this->resolveUsers($logs->pluck('authenticatable_id')->all());

            $paginated = $logs->map(fn (AuthenticationLog $log) => $this->transform($log, $users->get($log->authenticatable_id)))->toArray();

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
     * Single authentication log, shaped for the frontend.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        $log = AuthenticationLog::query()->find($id);

        if (! $log) {
            return null;
        }

        $user = $this->resolveUsers([$log->authenticatable_id])->get($log->authenticatable_id);

        return $this->transform($log, $user, true);
    }

    /**
     * High level totals used for the dashboard cards.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $base = $this->baseQuery($filters);

        return [
            'total' => (clone $base)->count(),
            'successful' => (clone $base)->where('login_successful', true)->count(),
            'failed' => (clone $base)->where('login_successful', false)->count(),
            'suspicious' => (clone $base)->where('is_suspicious', true)->count(),
            'unique_users' => (clone $base)->distinct('authenticatable_id')->count('authenticatable_id'),
            'today' => (clone $base)->whereDate('login_at', now()->toDateString())->count(),
            'this_month' => (clone $base)
                ->whereYear('login_at', now()->year)
                ->whereMonth('login_at', now()->month)
                ->count(),
        ];
    }

    /**
     * Map a log record (and its resolved user) into the API response shape.
     *
     * @return array<string, mixed>
     */
    private function transform(AuthenticationLog $log, ?User $user, bool $withDetail = false): array
    {
        $data = [
            'id' => $log->id,
            'user_id' => $log->authenticatable_id,
            'user_email' => $user?->email,
            'user_name' => $user?->employee?->name,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'device_name' => $log->device_name,
            'is_trusted' => $log->is_trusted,
            'login_successful' => $log->login_successful,
            'is_suspicious' => $log->is_suspicious,
            'suspicious_reason' => $log->suspicious_reason,
            'login_at' => $log->login_at?->format('Y-m-d H:i:s'),
            'logout_at' => $log->logout_at?->format('Y-m-d H:i:s'),
            'last_activity_at' => $log->last_activity_at?->format('Y-m-d H:i:s'),
        ];

        if ($withDetail) {
            $data['device_id'] = $log->device_id;
            $data['location'] = $log->location;
            $data['cleared_by_user'] = $log->cleared_by_user;
        }

        return $data;
    }

    /**
     * Batch-resolve the users referenced by a set of logs to avoid N+1 lookups.
     *
     * @param  array<int, int|null>  $ids
     * @return Collection<int, User>
     */
    private function resolveUsers(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return collect();
        }

        return User::query()
            ->with('employee:id,user_id,name')
            ->whereIn('id', $ids)
            ->get(['id', 'email'])
            ->keyBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuthenticationLog>
     */
    private function baseQuery(array $filters): Builder
    {
        return AuthenticationLog::query()
            ->when(! empty($filters['user_id']), fn (Builder $q) => $q->where('authenticatable_id', $filters['user_id']))
            ->when(! empty($filters['ip_address']), fn (Builder $q) => $q->where('ip_address', 'like', '%'.$filters['ip_address'].'%'))
            ->when(isset($filters['login_successful']) && $filters['login_successful'] !== null && $filters['login_successful'] !== '', function (Builder $q) use ($filters) {
                $q->where('login_successful', filter_var($filters['login_successful'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(isset($filters['is_suspicious']) && $filters['is_suspicious'] !== null && $filters['is_suspicious'] !== '', function (Builder $q) use ($filters) {
                $q->where('is_suspicious', filter_var($filters['is_suspicious'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(! empty($filters['start_date']), fn (Builder $q) => $q->whereDate('login_at', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn (Builder $q) => $q->whereDate('login_at', '<=', $filters['end_date']));
    }
}
