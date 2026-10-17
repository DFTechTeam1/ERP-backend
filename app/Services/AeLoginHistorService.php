<?php

namespace App\Services;

use App\Data\Auth\AfterEffect\LoginData;
use App\Data\User\AfterEffect\LoginHistoryListData;
use App\Data\User\AfterEffect\UserFormatData;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Hrd\Models\AfterEffectLoginHistory;
use Modules\Hrd\Repository\AfterEffectLoginHistoryRepository;

/**
 * Records and reports After Effects (AE) plugin login attempts.
 *
 * Every AE login — success or failure — is written to `after_effect_login_histories` so the team
 * can audit who signed in from the plugin, from where, on which build, and whether their refresh
 * token is still alive. The browser/OS are parsed once per request from the user agent.
 */
class AeLoginHistorService
{
    /** @var array{browser: string, os: string} */
    private array $browser;

    public function __construct(
        private readonly AfterEffectLoginHistoryRepository $repo
    ) {
        $this->browser = parseUserAgent(getUserAgentInfo());
    }

    /**
     * Browser parsed from the current request's user agent (e.g. "Chrome").
     */
    protected function getBrowser(): string
    {
        return $this->browser['browser'];
    }

    /**
     * Operating system parsed from the current request's user agent (e.g. "Windows").
     */
    protected function getOs(): string
    {
        return $this->browser['os'];
    }

    /**
     * Record a failed AE login attempt (wrong password, etc.).
     *
     * @param  UserFormatData  $user  The resolved account the attempt was made against.
     * @param  string  $reason  Human-readable failure reason, stored verbatim.
     */
    public function failedLog(UserFormatData $user, string $reason): void
    {
        $this->repo->store([
            'user_id' => $user->userId,
            'failure_reason' => $reason,
            'ip_address' => getClientIp(),
            'os' => $this->getOs().' - '.$this->getBrowser(),
            'is_success' => false,
        ]);
    }

    /**
     * Record a successful AE login, including the plugin version and the refresh
     * token's expiry so the UI can show whether the session is still active.
     *
     * @param  UserFormatData  $user  The authenticated account.
     * @param  LoginData  $payload  The login request (its `version` is the AE build).
     * @param  string  $refreshTokenExpires  The refresh token expiry as `Y-m-d H:i:s`.
     */
    public function success(UserFormatData $user, LoginData $payload, string $refreshTokenExpires): void
    {
        $this->repo->store([
            'user_id' => $user->userId,
            'os' => $this->getOs(),
            'ae_version' => $payload->version ?? '-',
            'is_success' => true,
            'ip_address' => getClientIp(),
            'login_at' => now(),
            'refresh_token_expires_at' => $refreshTokenExpires,
        ]);
    }

    /**
     * Build the repository query constraints from the current request's filters:
     * `status` (failed|success), `search` (employee name / email), and a
     * `date_from`/`date_to` window on `login_at` (BETWEEN when both are given, an
     * exact-date match when only one is). An optional uid narrows to a single row.
     *
     * @return array{0: array<string, mixed>, 1: array<string, \Closure>, 2: array<string, array{0: mixed, 1: mixed}>, 3: array<string, string>}
     *                                                                                                                                           [where, whereHas, whereBetween, whereDate]
     */
    protected function buildCondition(?string $uid = null): array
    {
        $status = request('status'); // failed | success
        $search = request('search');
        $startDate = request('date_from');
        $endDate = request('date_to');

        $where = [];
        $whereHas = [];
        $whereBetween = [];
        $whereDate = [];
        if ($status) {
            $where['is_success'] = $status == 'failed' ? false : true;
        }
        if ($uid) {
            $where['uid'] = $uid;
        }
        if ($search) {
            $whereHas['user'] = function ($query) use ($search) {
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->whereLike('name', "%{$search}%");
                })->orWhereLike('email', "%{$search}%");
            };
        }
        if ($startDate && $endDate) {
            $whereBetween['login_at'] = [$startDate, $endDate];
        } elseif (($startDate && ! $endDate) || (! $startDate && $endDate)) {
            $whereDate['login_at'] = $startDate ?? $endDate;
        }

        return [
            $where,
            $whereHas,
            $whereBetween,
            $whereDate,
        ];
    }

    /**
     * Map a history row (with its user + employee loaded) to the API DTO. The
     * token is considered active only when a future expiry is recorded.
     */
    protected function formatLogLine(AfterEffectLoginHistory $item): LoginHistoryListData
    {
        return new LoginHistoryListData(
            id: (int) $item->id,
            uid: $item->uid,
            user_name: $item->user->employee ? $item->user->employee->name : $item->user->email,
            user_email: $item->user->email,
            login_successful: $item->is_success,
            failure_reason: $item->failure_reason,
            ip_address: $item->ip_address,
            ae_version: $item->ae_version ?? '-',
            os: $item->os,
            login_at: $item->login_at ?? null,
            token_active: $item->refresh_token_expires_at
                ? ! Carbon::parse($item->refresh_token_expires_at)->isPast()
                : false,
            token_expires_at: $item->refresh_token_expires_at ?? null,
        );
    }

    /**
     * Paginated, filtered list of AE login attempts (newest page per the request's
     * `page`/`limit`), each row formatted as a {@see LoginHistoryListData}.
     *
     * @return array{paginated: Collection<int, LoginHistoryListData>, totalData: int}
     */
    public function listHistories(): array
    {
        [$where, $whereHas, $whereBetween, $whereDate] = $this->buildCondition();

        $relations = [
            'user:id,email,employee_id',
            'user.employee:id,name',
        ];

        $logs = $this->repo->paginate(
            params: [
                'with' => $relations,
                'where' => $where,
                'whereDate' => $whereDate,
                'whereBetween' => $whereBetween,
                'whereHas' => $whereHas,
            ],
            perPage: (int) (request('limit') ?? config('app.pagination_length', 15))
        );

        $output = $logs->getCollection()->transform(function ($item) {
            return $this->formatLogLine($item);
        });
        $totalData = $this->repo->get([
            'select' => ['id'],
            'where' => $where,
            'whereDate' => $whereDate,
            'whereBetween' => $whereBetween,
            'whereHas' => $whereHas,
        ])->count();

        return [
            'paginated' => $output,
            'totalData' => $totalData,
        ];
    }

    /**
     * A single AE login attempt by its uid, formatted as a {@see LoginHistoryListData}.
     *
     * @return array<string, mixed>
     */
    public function detail(string $uid): array
    {
        [$where, $whereHas, $whereBetween, $whereDate] = $this->buildCondition($uid);

        $log = $this->repo->show([
            'with' => [
                'user:id,email,employee_id',
                'user.employee:id,name',
            ],
            'where' => $where,
            'whereHas' => $whereHas,
            'whereBetween' => $whereBetween,
            'whereDate' => $whereDate,
        ]);

        return $this->formatLogLine($log)->toArray();
    }

    /**
     * Aggregate counters over the filtered set: total attempts, successes,
     * failures, distinct users, still-active tokens, and attempts made today.
     *
     * @return array{total: int, successful: int, failed: int, unique_users: int, active_tokens: int, today: int}
     */
    public function summary(): array
    {
        [$where, $whereHas, $whereBetween, $whereDate] = $this->buildCondition();

        $logs = $this->repo->get([
            'with' => [
                'user:id,email,employee_id',
                'user.employee:id,name',
            ],
            'where' => $where,
            'whereHas' => $whereHas,
            'whereBetween' => $whereBetween,
            'whereDate' => $whereDate,
        ])->map(function ($item) {
            return $this->formatLogLine($item);
        });

        $start = date('Y-m-d').' 00:00:01';
        $end = date('Y-m-d').' 23:59:59';

        return [
            'total' => $logs->count(),
            'successful' => $logs->where('login_successful', true)->count(),
            'failed' => $logs->where('login_successful', false)->count(),
            'unique_users' => $logs->unique('user_email')->count(),
            'active_tokens' => $logs->where('token_active', true)->count(),
            'today' => $logs->whereBetween('login_at', [$start, $end])->count(),
        ];
    }
}
