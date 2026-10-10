<?php

use App\Models\User;
use App\Repository\UserRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Finance\Models\DfEngineFeature;
use Modules\Finance\Models\DfEngineGeneration;
use Modules\Finance\Models\DfEngineMenu;
use Modules\Finance\Repository\DfEngineFeatureRepository;
use Modules\Finance\Repository\DfEngineGenerationRepository;
use Modules\Finance\Repository\DfEngineMenuRepository;
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
});
