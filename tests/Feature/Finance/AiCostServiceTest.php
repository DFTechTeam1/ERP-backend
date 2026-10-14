<?php

use App\Data\Finance\AiCost\TopupTokenData;
use App\Enums\Finance\ExchangeRate\SourceRate;
use App\Models\User;
use App\Repository\UserRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\DfEngineFeature;
use Modules\Finance\Models\DfEngineGeneration;
use Modules\Finance\Models\DfEngineMenu;
use Modules\Finance\Models\DfEngineTopup;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Repository\DfEngineFeatureRepository;
use Modules\Finance\Repository\DfEngineGenerationRepository;
use Modules\Finance\Repository\DfEngineMenuRepository;
use Modules\Finance\Repository\DfEngineTopupRepository;
use Modules\Finance\Services\AiCostService;
use Modules\Hrd\Models\Employee;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

/**
 * AiCostService + the /api/spending/* endpoints.
 *
 * The df_engine_* tables are owned by the separate DFEngine service (no Laravel migration) and do not
 * exist in the test database, so the repositories are MOCKED here: the generation repo returns
 * in-memory DfEngineGeneration models (exercising the aggregation logic), and the feature/menu repos
 * resolve a uid to a fake id. Filter coverage asserts the exact query params the service builds, so
 * every request param is exercised.
 *
 * total_cost (IDR) = round(cost / exchange_rate); using exchange_rate 0.0001 makes it cost * 10000.
 */
function aiService(): AiCostService
{
    return app(AiCostService::class);
}

/** Build an in-memory generation (not persisted). */
function aiGen(float $cost, int $tokens, string $kind, string $createdAt, float $exchangeRate = 0.0001, ?int $createdBy = null): DfEngineGeneration
{
    return (new DfEngineGeneration)->forceFill([
        'cost' => $cost,
        'exchange_rate' => $exchangeRate,
        'token_usage' => $tokens,
        'kind' => $kind,
        'created_at' => $createdAt,
        'created_by' => $createdBy,
    ]);
}

/** Build an in-memory actor (user) with an employee name and a role, as the user repo would return. */
function aiActor(int $id, string $name, string $role): User
{
    $user = (new User)->forceFill(['id' => $id, 'username' => $name]);
    $user->setRelation('employee', (new Employee)->forceFill(['name' => $name]));
    $user->setRelation('roles', new Collection([(new Role)->forceFill(['name' => $role])]));

    return $user;
}

/**
 * Mock the user repository's list() to return the given actors.
 *
 * @param  array<int, User>  $users
 */
function mockUsers(array $users): void
{
    mock(UserRepository::class, function ($m) use ($users) {
        $m->shouldReceive('list')->andReturn(new Collection($users));
    });
}

/**
 * Mock the generation repository to return $gens, optionally capturing the query params it receives.
 *
 * @param  array<int, DfEngineGeneration>  $gens
 */
function mockGenerations(array $gens, ?Closure $onParams = null): void
{
    mock(DfEngineGenerationRepository::class, function ($m) use ($gens, $onParams) {
        $m->shouldReceive('get')->andReturnUsing(function ($params) use ($gens, $onParams) {
            if ($onParams) {
                $onParams($params);
            }

            return new Collection($gens);
        });
    });
}

/** Build an in-memory top-up, optionally with a creator (user + employee). */
function aiTopup(string $uid, float $amountUsd, float $exchangeRate, string $topupDate, ?string $note = null, ?string $proof = null, ?int $createdBy = null, ?string $email = null, ?string $nickname = null): DfEngineTopup
{
    $topup = (new DfEngineTopup)->forceFill([
        'uid' => $uid,
        'amount_usd' => $amountUsd,
        'exchange_rate' => $exchangeRate,
        'topup_date' => $topupDate,
        'note' => $note,
        'reference' => null,
        'proof_of_payment' => $proof,
        'created_by' => $createdBy,
        'created_at' => $topupDate,
    ]);

    if ($email !== null) {
        $creator = (new User)->forceFill(['id' => $createdBy ?? 1, 'email' => $email]);
        $creator->setRelation('employee', $nickname !== null ? (new Employee)->forceFill(['nickname' => $nickname, 'avatar' => 'a.png']) : null);
        $topup->setRelation('creator', $creator);
    } else {
        $topup->setRelation('creator', null);
    }

    return $topup;
}

/** Seed a USD->IDR rate so the get_currency_rate stored procedure returns it in-session. */
function seedUsdRate(float $rate): void
{
    $idr = Currency::create(['name' => 'Rupiah', 'code' => 'IDR', 'is_base' => true, 'is_active' => true]);
    $usd = Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'is_active' => true]);
    ExchangeRate::create([
        'currency_id' => $usd->id,
        'parent_currency_id' => $idr->id,
        'rate_date' => now()->toDateString(),
        'rate' => $rate,
        'source' => SourceRate::System,
    ]);
}

// ---- Aggregation (service) ------------------------------------------------

it('summary aggregates total cost (USD and IDR), tokens and action count', function () {
    request()->merge(['period' => 'this_year']);
    mockGenerations([
        aiGen(5.0, 1000, 'image', '2026-01-10'),
        aiGen(3.0, 500, 'image', '2026-01-20'),
        aiGen(2.0, 200, 'video', '2026-02-05'),
    ]);

    $res = aiService()->summary();

    expect($res['error'])->toBeFalse();
    $d = $res['data'];
    expect((float) $d['costUsd'])->toBe(10.0)          // 5 + 3 + 2
        ->and((float) $d['costIdr'])->toBe(100000.0)   // (5+3+2) / 0.0001
        ->and((float) $d['tokens'])->toBe(1700.0)      // 1000 + 500 + 200
        ->and($d['actions'])->toBe(3);
});

it('summary keeps large USD costs intact (round, not number_format)', function () {
    request()->merge(['period' => 'this_year']);
    mockGenerations([aiGen(1500.5, 10, 'image', '2026-01-10')]);

    // number_format(1500.5, 2) coerces to 1.0; round keeps the real value
    expect((float) aiService()->summary()['data']['costUsd'])->toBe(1500.5);
});

it('summary handles a zero/missing exchange rate without dividing by zero', function () {
    request()->merge(['period' => 'this_year']);
    mockGenerations([
        aiGen(5.0, 1000, 'image', '2026-01-10', exchangeRate: 0.0), // no rate -> IDR cost 0
        aiGen(3.0, 500, 'image', '2026-01-20'),                     // normal rate -> 30000
    ]);

    $res = aiService()->summary();

    expect($res['error'])->toBeFalse()
        ->and((float) $res['data']['costUsd'])->toBe(8.0)       // USD still summed (5 + 3)
        ->and((float) $res['data']['costIdr'])->toBe(30000.0);  // only the rated row contributes
});

it('summaryByActionType groups totals by generation kind', function () {
    request()->merge(['period' => 'this_year']);
    mockGenerations([
        aiGen(5.0, 1000, 'image', '2026-01-10'),
        aiGen(3.0, 500, 'image', '2026-01-20'),
        aiGen(2.0, 200, 'video', '2026-02-05'),
    ]);

    $res = aiService()->summaryByActionType();

    expect($res['error'])->toBeFalse();
    $rows = collect($res['data']);
    $image = $rows->firstWhere('type', 'image');
    $video = $rows->firstWhere('type', 'video');

    expect($image->count)->toBe(2)
        ->and($image->tokens)->toBe(1500)
        ->and((float) $image->costUsd)->toBe(8.0)
        ->and((float) $image->costIdr)->toBe(80000.0)
        ->and($video->count)->toBe(1)
        ->and((float) $video->costUsd)->toBe(2.0)
        ->and((float) $video->costIdr)->toBe(20000.0);
});

it('trend returns per-month totals padded to all twelve months', function () {
    request()->merge(['period' => 'this_year']);
    mockGenerations([
        aiGen(5.0, 1000, 'image', '2026-01-10'),
        aiGen(3.0, 500, 'video', '2026-01-20'),
        aiGen(2.0, 200, 'image', '2026-02-05'),
    ]);

    $res = aiService()->trend();

    expect($res['error'])->toBeFalse();
    $rows = collect($res['data']);
    expect($rows)->toHaveCount(12);

    $jan = $rows->firstWhere('month', 'Jan');
    $feb = $rows->firstWhere('month', 'Feb');
    $mar = $rows->firstWhere('month', 'Mar');

    expect((float) $jan['costUsd'])->toBe(8.0)         // 5 + 3
        ->and((float) $jan['costIdr'])->toBe(80000.0)
        ->and((float) $feb['costUsd'])->toBe(2.0)
        ->and((float) $feb['costIdr'])->toBe(20000.0)
        ->and((float) $mar['costUsd'])->toBe(0.0)      // padded, no data
        ->and((float) $mar['costIdr'])->toBe(0.0);
});

// ---- Filters -> query params (all request params) -------------------------

it('translates every filter into the repository query (period, feature, menu, actor)', function () {
    mock(DfEngineFeatureRepository::class, fn ($m) => $m->shouldReceive('show')->andReturn((new DfEngineFeature)->forceFill(['id' => 11])));
    mock(DfEngineMenuRepository::class, fn ($m) => $m->shouldReceive('show')->andReturn((new DfEngineMenu)->forceFill(['id' => 22])));

    $captured = null;
    mockGenerations([], function ($params) use (&$captured) {
        $captured = $params;
    });

    request()->merge([
        'period' => 'this_month',
        'feature_uid' => 'FEAT-UID',
        'menu_uid' => 'MENU-UID',
        'actor_id' => 7,
        'api_key_uid' => 'KEY-UID', // accepted, but there is no column to filter generations on
    ]);

    aiService()->summary();

    expect($captured['whereBetween']['created_at'])->toBe([
        Carbon::now()->firstOfMonth()->format('Y-m-d'),
        Carbon::now()->lastOfMonth()->format('Y-m-d'),
    ])
        ->and($captured['where'])->toBe([
            'feature_id' => 11,
            'menu_id' => 22,
            'created_by' => 7,
        ]);
});

it('uses explicit start/end dates over the period', function () {
    $captured = null;
    mockGenerations([], function ($params) use (&$captured) {
        $captured = $params;
    });

    request()->merge(['period' => 'this_year', 'start' => '2026-03-01', 'end' => '2026-03-31']);

    aiService()->summary();

    expect($captured['whereBetween']['created_at'])->toBe(['2026-03-01', '2026-03-31']);
});

it('resolves the last_month period', function () {
    $captured = null;
    mockGenerations([], function ($params) use (&$captured) {
        $captured = $params;
    });

    request()->merge(['period' => 'last_month']);

    aiService()->summary();

    expect($captured['whereBetween']['created_at'])->toBe([
        Carbon::now()->subMonth()->firstOfMonth()->format('Y-m-d'),
        Carbon::now()->subMonth()->lastOfMonth()->format('Y-m-d'),
    ]);
});

it('passes no equality filters when none are requested', function () {
    $captured = null;
    mockGenerations([], function ($params) use (&$captured) {
        $captured = $params;
    });

    request()->merge(['period' => 'this_year']);

    aiService()->summary();

    expect($captured['where'])->toBe([]);
});

// ---- Spending by actor ----------------------------------------------------

it('summaryByActor groups spending per actor with resolved name and role', function () {
    request()->merge(['period' => 'this_year']);
    mockGenerations([
        aiGen(5.0, 1000, 'image', '2026-01-10', createdBy: 41),
        aiGen(3.0, 500, 'video', '2026-01-20', createdBy: 41),
        aiGen(2.0, 200, 'image', '2026-02-05', createdBy: 31),
    ]);
    mockUsers([
        aiActor(41, 'Grace', 'Marketing'),
        aiActor(31, 'Rudhi', 'Project Manager'),
    ]);

    $res = aiService()->summaryByActor();

    expect($res['error'])->toBeFalse();
    $rows = collect($res['data']);
    $grace = $rows->firstWhere('actorId', 41);
    $rudhi = $rows->firstWhere('actorId', 31);

    expect($grace->name)->toBe('Grace')
        ->and($grace->role)->toBe('Marketing')
        ->and($grace->count)->toBe(2)
        ->and($grace->tokens)->toBe(1500)
        ->and((float) $grace->costUsd)->toBe(8.0)
        ->and((float) $grace->costIdr)->toBe(80000.0)
        ->and($rudhi->name)->toBe('Rudhi')
        ->and($rudhi->role)->toBe('Project Manager')
        ->and($rudhi->count)->toBe(1)
        ->and((float) $rudhi->costUsd)->toBe(2.0);
});

// ---- Token top-ups (service) ----------------------------------------------

it('listTopup returns paginated top-ups with IDR amount and creator', function () {
    request()->merge(['itemsPerPage' => 10, 'page' => 1]);

    $topup = aiTopup('TU-1', amountUsd: 100.0, exchangeRate: 0.0001, topupDate: '2026-01-10', note: 'first', proof: 'dfengine/topup/p.jpg', createdBy: 7, email: 'grace@x.com', nickname: 'Grace');

    mock(DfEngineTopupRepository::class, function ($m) use ($topup) {
        $m->shouldReceive('paginate')->andReturn(new LengthAwarePaginator(new Collection([$topup]), 1, 10, 1));
        $m->shouldReceive('get')->andReturn(new Collection([$topup]));
    });

    $res = aiService()->listTopup();

    expect($res['error'])->toBeFalse()
        ->and($res['data']['totalData'])->toBe(1);

    $first = $res['data']['paginated']->first();
    expect($first->uid)->toBe('TU-1')
        ->and((float) $first->amount_usd)->toBe(100.0)
        ->and((float) $first->amount_idr)->toBe(1000000.0) // 100 / 0.0001
        ->and($first->note)->toBe('first')
        ->and($first->creator->nickname)->toBe('Grace')    // employee nickname (precedence fix)
        ->and($first->creator->user_id)->toBe(7);
});

it('getTopupKpi sums top-up amounts and reports the last top-up date', function () {
    mock(DfEngineTopupRepository::class, fn ($m) => $m->shouldReceive('get')->andReturn(new Collection([
        aiTopup('TU-1', amountUsd: 100.0, exchangeRate: 0.0001, topupDate: '2026-01-10'),
        aiTopup('TU-2', amountUsd: 50.0, exchangeRate: 0.0001, topupDate: '2026-02-15'),
    ])));

    $res = aiService()->getTopupKpi();

    expect($res['error'])->toBeFalse();
    $d = $res['data'];
    expect($d['total_count'])->toBe(2)
        ->and((float) $d['total_amount_usd'])->toBe(150.0)
        ->and((float) $d['total_amount_idr'])->toBe(1500000.0) // 1,000,000 + 500,000
        ->and($d['last_topup_date'])->toBe('15 February 2026');
});

it('getTopupKpi returns a dash for the last date when there are no top-ups', function () {
    mock(DfEngineTopupRepository::class, fn ($m) => $m->shouldReceive('get')->andReturn(new Collection([])));

    $res = aiService()->getTopupKpi();

    expect($res['error'])->toBeFalse()
        ->and($res['data']['total_count'])->toBe(0)
        ->and($res['data']['last_topup_date'])->toBe('-');
});

it('topup stores the amount with the USD rate from the proc and the uploaded proof', function () {
    Storage::fake();
    seedUsdRate(0.0001); // the get_currency_rate proc reads this within the test transaction

    $captured = null;
    mock(DfEngineTopupRepository::class, function ($m) use (&$captured) {
        $m->shouldReceive('store')->andReturnUsing(function ($payload) use (&$captured) {
            $captured = $payload;

            return new DfEngineTopup;
        });
    });

    $res = aiService()->topup(new TopupTokenData(
        amount_usd: 100.0,
        topup_date: '2026-01-10',
        reference: null,
        note: 'top up',
        proof_of_payment: UploadedFile::fake()->create('proof.pdf', 10),
    ));

    expect($res['error'])->toBeFalse()
        ->and((float) $captured['amount_usd'])->toBe(100.0)
        ->and((float) $captured['exchange_rate'])->toBe(0.0001) // resolved by the stored procedure
        ->and($captured['note'])->toBe('top up')
        ->and($captured['proof_of_payment'])->not->toBeEmpty();
    Storage::assertExists($captured['proof_of_payment']);
});

// ---- E2E over HTTP --------------------------------------------------------

describe('endpoints (e2e)', function () {
    beforeEach(function () {
        actingAs(User::factory()->create());
    });

    it('GET /api/spending/summary returns the summary payload', function () {
        mockGenerations([aiGen(5.0, 1000, 'image', '2026-01-10')]);

        $this->getJson('/api/spending/summary?period=this_year')
            ->assertStatus(201)
            ->assertJsonPath('message', 'Success')
            ->assertJsonPath('data.actions', 1);
    });

    it('GET /api/spending/by-action-type returns one row per kind', function () {
        mockGenerations([
            aiGen(5.0, 1000, 'image', '2026-01-10'),
            aiGen(2.0, 200, 'video', '2026-02-05'),
        ]);

        $this->getJson('/api/spending/by-action-type?period=this_year')
            ->assertStatus(201)
            ->assertJsonCount(2, 'data');
    });

    it('GET /api/spending/trend returns twelve month rows', function () {
        mockGenerations([aiGen(5.0, 1000, 'image', '2026-01-10')]);

        $this->getJson('/api/spending/trend?period=this_year')
            ->assertStatus(201)
            ->assertJsonCount(12, 'data');
    });

    it('GET /api/spending/by-actor returns spending per actor', function () {
        mockGenerations([aiGen(5.0, 1000, 'image', '2026-01-10', createdBy: 41)]);
        mockUsers([aiActor(41, 'Grace', 'Marketing')]);

        $this->getJson('/api/spending/by-actor?period=this_year')
            ->assertStatus(201)
            ->assertJsonPath('data.0.actor_id', 41) // MapOutputName: actorId -> actor_id
            ->assertJsonPath('data.0.name', 'Grace')
            ->assertJsonPath('data.0.role', 'Marketing')
            ->assertJsonPath('data.0.count', 1);
    });

    it('POST /api/token-topups records a top-up', function () {
        Storage::fake();
        seedUsdRate(0.0001);
        mock(DfEngineTopupRepository::class, fn ($m) => $m->shouldReceive('store')->andReturn(new DfEngineTopup));

        $this->post('/api/token-topups', [
            'amount_usd' => 100,
            'topup_date' => '2026-01-10',
            'note' => 'via api',
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10),
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Success topup token');
    });

    it('GET /api/token-topups lists top-ups', function () {
        $topup = aiTopup('TU-1', amountUsd: 100.0, exchangeRate: 0.0001, topupDate: '2026-01-10', createdBy: 7, email: 'g@x.com', nickname: 'Grace');
        mock(DfEngineTopupRepository::class, function ($m) use ($topup) {
            $m->shouldReceive('paginate')->andReturn(new LengthAwarePaginator(new Collection([$topup]), 1, 10, 1));
            $m->shouldReceive('get')->andReturn(new Collection([$topup]));
        });

        $this->getJson('/api/token-topups')
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1)
            ->assertJsonPath('data.paginated.0.uid', 'TU-1');
    });

    it('GET /api/token-topups/summary returns the KPI', function () {
        mock(DfEngineTopupRepository::class, fn ($m) => $m->shouldReceive('get')->andReturn(new Collection([
            aiTopup('TU-1', amountUsd: 100.0, exchangeRate: 0.0001, topupDate: '2026-01-10'),
        ])));

        $this->getJson('/api/token-topups/summary')
            ->assertStatus(201)
            ->assertJsonPath('data.total_count', 1);
    });
});
