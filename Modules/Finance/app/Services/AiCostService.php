<?php

namespace Modules\Finance\Services;

use App\Data\Finance\AiCost\SummaryByActionTypeData;
use App\Data\Finance\AiCost\SummaryByActorData;
use App\Data\Finance\AiCost\SummaryData;
use App\Data\Finance\AiCost\TopupCreatorData;
use App\Data\Finance\AiCost\TopupListData;
use App\Data\Finance\AiCost\TopupSummaryData;
use App\Data\Finance\AiCost\TopupTokenData;
use App\Data\Finance\AiCost\TrendData;
use App\Repository\UserRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\DfEngineGeneration;
use Modules\Finance\Repository\DfEngineApiKeyRepository;
use Modules\Finance\Repository\DfEngineFeatureRepository;
use Modules\Finance\Repository\DfEngineGenerationRepository;
use Modules\Finance\Repository\DfEngineMenuRepository;
use Modules\Finance\Repository\DfEngineTopupRepository;

class AiCostService
{
    /**
     * @param  DfEngineGenerationRepository  $generationRepo  Repository for DFEngine generations (the cost records).
     * @param  DfEngineFeatureRepository  $featureRepo  Repository used to resolve a feature uid to its id.
     * @param  DfEngineMenuRepository  $menuRepo  Repository used to resolve a menu uid to its id.
     * @param  DfEngineApiKeyRepository  $apiKeyRepo  Repository for DFEngine API keys.
     * @param  UserRepository  $userRepo  Repository used to resolve actors (name + role) by id.
     */
    public function __construct(
        private readonly DfEngineGenerationRepository $generationRepo,
        private readonly DfEngineFeatureRepository $featureRepo,
        private readonly DfEngineMenuRepository $menuRepo,
        private readonly DfEngineApiKeyRepository $apiKeyRepo,
        private readonly UserRepository $userRepo,
        private readonly DfEngineTopupRepository $topupRepo
    ) {}

    /**
     * Resolve the [start, end] date range from the `period` request parameter.
     *
     * @return array{0: string, 1: string} The inclusive start and end dates (Y-m-d). Defaults to the
     *                                     current year; `this_month` and `last_month` are also supported.
     */
    protected function parsePeriod(): array
    {
        $period = request('period');
        if ($period === 'this_month') {
            return [Carbon::now()->firstOfMonth()->format('Y-m-d'), Carbon::now()->lastOfMonth()->format('Y-m-d')];
        } elseif ($period === 'last_month') {
            return [Carbon::now()->subMonth()->firstOfMonth()->format('Y-m-d'), Carbon::now()->subMonth()->lastOfMonth()->format('Y-m-d')];
        } else { // This year
            return [Carbon::now()->firstOfYear()->format('Y-m-d'), Carbon::now()->lastOfYear()->format('Y-m-d')];
        }
    }

    /**
     * Resolve the effective [start, end] date range: an explicit `start`/`end` pair when supplied,
     * otherwise the range derived from the `period` parameter.
     *
     * @return array{0: string, 1: string} The inclusive start and end dates.
     */
    protected function getFinalPeriod(): array
    {
        $start = request('start');
        $end = request('end');

        if (! $start && ! $end) {
            return $this->parsePeriod();
        } else {
            return [$start, $end];
        }
    }

    /**
     * Collect the active filters from the request.
     *
     * @return array{period: array{0: string, 1: string}, featureUid: string|null, menuUid: string|null, apiKeyUid: string|null, actorId: string|null}
     */
    protected function getFilter(): array
    {
        return [
            'period' => $this->getFinalPeriod(), // this_month | last_month | this_year
            'featureUid' => request('feature_uid'),
            'menuUid' => request('menu_uid'),
            'apiKeyUid' => request('api_key_uid'),
            'actorId' => request('actor_id'),
        ];
    }

    /**
     * Build the equality where-map for the generation query from the request filters, resolving the
     * feature/menu uids to their ids. The map is keyed by column so it can be passed straight to the
     * repository's `where` param.
     *
     * @param  array<string, mixed>  $where  Mutated in place with the resolved column => value pairs.
     */
    protected function formatWhereBuilder(array &$where): void
    {
        if (request('feature_uid')) {
            $feature = $this->featureRepo->show([
                'where' => [
                    'uid' => request('feature_uid'),
                ],
                'select' => ['id'],
            ]);

            if ($feature) {
                $where['feature_id'] = $feature->id;
            }
        }

        if (request('menu_uid')) {
            $menu = $this->menuRepo->show([
                'where' => [
                    'uid' => request('menu_uid'),
                ],
                'select' => ['id'],
            ]);

            if ($menu) {
                $where['menu_id'] = $menu->id;
            }
        }

        if (request('actor_id')) {
            $where['created_by'] = request('actor_id');
        }
    }

    /**
     * Fetch the generations within the resolved period (and any feature/menu/actor filters), enriched
     * with `total_cost` (cost converted to IDR) and `month` (short month name) for the aggregations.
     *
     * @return Collection<int, DfEngineGeneration>
     */
    protected function getGenerations(): Collection
    {
        $filter = $this->getFilter();

        $where = [];
        $this->formatWhereBuilder($where);

        $generations = $this->generationRepo->get([
            'whereBetween' => [
                'created_at' => $filter['period'],
            ],
            'where' => $where,
        ])->map(function ($item) {
            // Guard against a missing/zero exchange rate: without a rate the IDR cost is unknown, so 0.
            $exchangeRate = (float) $item->exchange_rate;
            $calculation = $exchangeRate != 0.0 ? round($item->cost / $exchangeRate) : 0;
            $item['total_cost'] = floatval($calculation);
            $item['month'] = date('M', strtotime($item->created_at));

            return $item;
        });

        return $generations;
    }

    /**
     * Build the headline spending summary: total cost (USD and IDR), total tokens and action count.
     *
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope carrying a SummaryData payload.
     */
    public function summary(): array
    {
        try {
            $generations = $this->getGenerations();

            $output = new SummaryData(
                costUsd: round($generations->sum('cost'), 2),
                costIdr: $generations->sum('total_cost'),
                tokens: $generations->sum('token_usage'),
                actions: $generations->count()
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
     * Build the spending breakdown grouped by generation kind (image, video, …).
     *
     * @return array{error: bool, message: string, data?: array<int, SummaryByActionTypeData>, code?: int}
     *                                                                                                     The standard API response envelope carrying one row per kind.
     */
    public function summaryByActionType(): array
    {
        try {
            $generations = $this->getGenerations();

            $byTypes = $generations->groupBy('kind');

            $output = [];
            foreach ($byTypes as $kind => $item) {
                $output[] = new SummaryByActionTypeData(
                    type: $kind,
                    count: $item->count(),
                    tokens: $item->sum('token_usage'),
                    costUsd: round($item->sum('cost'), 2),
                    costIdr: (float) number_format($item->sum('total_cost'), 0, '.', '')
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
     * Build the monthly spending trend (cost in USD and IDR), padded to all twelve months of the year
     * so every month is represented even when it has no generations.
     *
     * @return array{error: bool, message: string, data?: array<int, array{costIdr: float, costUsd: float, month: string}>, code?: int}
     *                                                                                                                                  The standard API response envelope carrying twelve month rows.
     */
    public function trend(): array
    {
        try {
            $generations = $this->getGenerations();
            $byMonth = $generations->groupBy('month');

            $output = [];
            foreach ($byMonth as $month => $item) {
                $output[] = new TrendData(
                    costIdr: (float) number_format($item->sum('total_cost'), 0, '.', ''),
                    costUsd: round($item->sum('cost'), 2),
                    month: $month
                );
            }

            // Fill month
            $allMonths = collect(range(1, 12))->map(function ($item) use ($output) {
                $targetMonth = date('M', strtotime(date('Y').'-'.$item));

                $costIdr = collect($output)->firstWhere('month', $targetMonth)?->costIdr ?? 0;
                $costUsd = collect($output)->firstWhere('month', $targetMonth)?->costUsd ?? 0;

                return [
                    'costIdr' => $costIdr,
                    'costUsd' => $costUsd,
                    'month' => $targetMonth,
                ];
            })->all();

            return generalResponse(
                message: 'Success',
                data: $allMonths
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the spending breakdown grouped by actor (the user who created each generation), resolving
     * each actor's name (from their employee, falling back to their username) and their role.
     *
     * @return array{error: bool, message: string, data?: array<int, SummaryByActorData>, code?: int}
     *                                                                                                The standard API response envelope carrying one row per actor.
     */
    public function summaryByActor(): array
    {
        try {
            $generations = $this->getGenerations();

            $byActor = $generations->groupBy('created_by');

            // Resolve the actors (name + role) in a single query to avoid an N+1 per group.
            $actorIds = $byActor->keys()->filter()->values();
            $actors = collect();
            if ($actorIds->isNotEmpty()) {
                $actors = $this->userRepo->list(
                    select: 'id,employee_id,username',
                    where: 'id IN ('.$actorIds->map(fn ($id) => (int) $id)->implode(',').')',
                    relation: ['employee:id,name', 'roles:id,name']
                )->keyBy('id');
            }

            $output = [];
            foreach ($byActor as $actorId => $item) {
                $actor = $actors->get($actorId);

                $output[] = new SummaryByActorData(
                    actorId: (int) $actorId,
                    name: $actor?->employee?->name ?? ($actor?->username ?? '-'),
                    role: $actor?->roles->first()?->name ?? '-',
                    count: $item->count(),
                    tokens: $item->sum('token_usage'),
                    costUsd: round($item->sum('cost'), 2),
                    costIdr: (float) number_format($item->sum('total_cost'), 0, '.', '')
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
     * List token top-ups (paginated), each with its IDR amount, proof-of-payment URL and creator.
     * Honours the `itemsPerPage`, `page` and `search` (matched against note) request parameters.
     *
     * @return array{error: bool, message: string, data?: array{paginated: \Illuminate\Support\Collection<int, TopupListData>, totalData: int}, code?: int}
     *                                                                                                                                                      The standard API response envelope.
     */
    public function listTopup(): array
    {
        try {
            $itemsPerPage = request('itemsPerPage') ?? config('app.pagination_length', 15);
            $search = request('search');

            $params = [
                'with' => [
                    'creator:id,employee_id,email',
                    'creator.employee:id,nickname,avatar',
                ],
            ];
            if ($search) {
                $params['where'] = [
                    'note' => $search,
                ];
            }

            $topups = $this->topupRepo->paginate(
                params: $params,
                perPage: $itemsPerPage
            );

            /** @var array<int, TopupListData> */
            $output = [];

            $output = $topups->getCollection()->transform(function ($item) {

                return new TopupListData(
                    uid: $item->uid,
                    amount_usd: $item->amount_usd,
                    amount_idr: $item->exchange_rate ? round($item->amount_usd / $item->exchange_rate) : 0,
                    topup_date: $item->topup_date,
                    reference: $item->reference ?? null,
                    note: $item->note ?? null,
                    proof_of_payment_url: asset('storage/'.$item->proof_of_payment),
                    creator: new TopupCreatorData(
                        nickname: $item?->creator?->employee?->nickname ?? ($item->creator ? $item->creator->email : '-'),
                        image: $item?->creator?->employee?->avatar ?? '',
                        user_id: $item->created_by ?? '',
                    ),
                    created_at: $item->created_at,
                );
            });
            $totalData = $this->topupRepo->get(params: $params)->count();

            return generalResponse(
                message: 'Success',
                data: [
                    'paginated' => $output,
                    'totalData' => $totalData,
                ]
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Record a token top-up: resolve the current USD→IDR rate via the `get_currency_rate` stored
     * procedure, store the amount with that exchange rate, and persist the uploaded proof of payment.
     *
     * @param  TopupTokenData  $payload  The validated payload (amount_usd, topup_date, reference, note, proof_of_payment).
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope.
     */
    public function topup(TopupTokenData $payload): array
    {
        try {
            $rates = DB::select("CALL get_currency_rate('USD')");

            if (gettype($rates) == 'array' && count($rates) == 1) {
                $rate = $rates[0]->output_rate;
            }

            $payloadTopup = [
                'amount_usd' => $payload->amount_usd,
                'exchange_rate' => $rate ?? 0,
                'topup_date' => $payload->topup_date,
                'reference' => '',
                'note' => $payload->note ?? null,
                'proof_of_payment' => '',
            ];

            if ($payload->proof_of_payment) {
                $file = Storage::putFile('dfengine/topup', $payload->proof_of_payment);
                $payloadTopup['proof_of_payment'] = $file;
            }

            $this->topupRepo->store($payloadTopup);

            return generalResponse(
                message: 'Success topup token'
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }

    /**
     * Build the top-up KPI summary: total count, total amount (USD and IDR) and the last top-up date.
     *
     * @return array{error: bool, message: string, data?: array<string, mixed>, code?: int} The
     *                                                                                      standard API response envelope carrying a TopupSummaryData payload.
     */
    public function getTopupKpi(): array
    {
        try {
            $topups = $this->topupRepo->get()->map(function ($item) {
                $item['total_idr'] = $item->exchange_rate ? round($item->amount_usd / $item->exchange_rate) : 0;

                return $item;
            });
            $totalUsd = $topups->sum('amount_usd');
            $totalIdr = $topups->sum('total_idr');

            $output = new TopupSummaryData(
                total_count: $topups->count(),
                total_amount_usd: $totalUsd,
                total_amount_idr: $totalIdr,
                last_topup_date: $topups->isNotEmpty() ? Carbon::parse($topups->last()->created_at)->format('d F Y') : '-',
            );

            return generalResponse(
                message: 'Success',
                data: $output->toArray()
            );
        } catch (\Throwable $th) {
            return errorResponse($th);
        }
    }
}
