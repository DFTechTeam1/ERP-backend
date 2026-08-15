<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Data\Resign\ResignData;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\GreatdayResignReason;
use Modules\Hrd\Services\EmployeeService;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Cache::forget('greatday_token');

    config([
        'app.greatday.base_url' => 'https://greatday.test/api',
        'app.greatday.access_key' => 'key',
        'app.greatday.access_secret' => 'secret',
    ]);
});

/**
 * The command reads Greatday over HTTP, so every test fakes the auth handshake
 * plus the /employees payload it reacts to.
 *
 * @param  array<int, array<string, mixed>>  $employees
 */
function fakeGreatdayResignFeed(array $employees): void
{
    Http::fake([
        'greatday.test/api/auth/login' => Http::response([
            'access_token' => 'token',
            'refresh_token' => 'refresh',
        ], 200),
        'greatday.test/api/employees' => Http::response(['data' => $employees], 200),
    ]);
}

/**
 * @return array<string, mixed>
 */
function greatdayEmployee(string $empNo, ?string $endDate): array
{
    return [
        'empNo' => $empNo,
        'endDate' => $endDate,
        'name' => 'Greatday '.$empNo,
    ];
}

function makeEmploymentStatus(string $name, bool $isTerminal): EmploymentStatus
{
    return EmploymentStatus::factory()->create([
        'name' => $name,
        'is_terminal' => $isTerminal ? 1 : 0,
    ]);
}

function makeEmployee(string $employeeId, ?EmploymentStatus $status): Employee
{
    return Employee::factory()->create([
        'employee_id' => $employeeId,
        'employment_status_id' => $status?->id,
    ]);
}

/**
 * Runs the report and returns the rendered table. PendingCommand's
 * expectsOutputToContain() can only clear one substring per write, and the
 * whole table lands in a single write, so the output is asserted directly.
 */
function runReport(): string
{
    Artisan::call('app:sync-resign-employee');

    return Artisan::output();
}

function makeResignReason(string $code = 'RSG'): GreatdayResignReason
{
    return GreatdayResignReason::query()->forceCreate([
        'code' => $code,
        'name' => 'resign',
        'resign_type' => null,
    ]);
}

describe('reporting', function () {
    it('lists an employee Greatday marked resigned while the ERP still has them active', function () {
        makeResignReason('RSG-01');
        $active = makeEmploymentStatus('Permanent', isTerminal: false);
        $employee = makeEmployee('DF901', $active);

        fakeGreatdayResignFeed([greatdayEmployee('DF901', '2026-07-25T00:00:00Z')]);

        expect(runReport())
            ->toContain('DF901')
            ->toContain($employee->uid)
            ->toContain($employee->nickname)
            ->toContain($employee->email)
            ->toContain('Permanent')
            ->toContain('RSG-01');
    });

    it('formats the Greatday end date as the target resign date', function () {
        makeResignReason();
        makeEmployee('DF902', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([greatdayEmployee('DF902', '2026-07-25T13:45:00Z')]);

        expect(runReport())->toContain('2026-07-25');
    });

    it('ignores Greatday employees that have no end date', function () {
        makeResignReason();
        makeEmployee('DF903', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([greatdayEmployee('DF903', null)]);

        expect(runReport())->not->toContain('DF903');
    });

    it('ignores employees already sitting on a terminal employment status', function () {
        makeResignReason();
        makeEmployee('DF904', makeEmploymentStatus('Resigned', isTerminal: true));

        fakeGreatdayResignFeed([greatdayEmployee('DF904', '2026-07-25T00:00:00Z')]);

        expect(runReport())->not->toContain('DF904');
    });

    it('ignores an employee carrying no employment status at all', function () {
        makeResignReason();
        makeEmployee('DF912', null);

        fakeGreatdayResignFeed([greatdayEmployee('DF912', '2026-07-25T00:00:00Z')]);

        expect(runReport())->not->toContain('DF912');
    });

    it('ignores Greatday employees with no matching ERP record', function () {
        makeResignReason();
        makeEmployee('DF905', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([
            greatdayEmployee('DF905', '2026-07-25T00:00:00Z'),
            greatdayEmployee('DF-UNKNOWN', '2026-07-25T00:00:00Z'),
        ]);

        expect(runReport())
            ->toContain('DF905')
            ->not->toContain('DF-UNKNOWN');
    });

    it('fails when no resign reason is configured', function () {
        makeEmployee('DF906', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([greatdayEmployee('DF906', '2026-07-25T00:00:00Z')]);

        runReport();
    })->throws(ModelNotFoundException::class);
});

describe('dry run', function () {
    it('resigns nobody without --force', function () {
        makeResignReason();
        makeEmployee('DF907', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([greatdayEmployee('DF907', '2026-07-25T00:00:00Z')]);

        $this->mock(EmployeeService::class)->shouldNotReceive('resign');

        expect(runReport())->toContain('DF907');
    });
});

describe('sync', function () {
    it('resigns each listed employee with the Greatday end date and reason code', function () {
        makeResignReason('RSG-01');
        $employee = makeEmployee('DF908', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([greatdayEmployee('DF908', '2026-07-25T00:00:00Z')]);

        $this->mock(EmployeeService::class)
            ->shouldReceive('resign')
            ->once()
            ->withArgs(function (ResignData $data, string $uid) use ($employee) {
                return $data->resign_reason_code === 'RSG-01'
                    && $data->resign_date === '2026-07-25'
                    && $data->remark === 'Resign'
                    && $data->sync_greatday === false
                    && $uid === $employee->uid;
            })
            ->andReturn(['error' => false, 'message' => 'ok', 'data' => [], 'code' => 200]);

        artisan('app:sync-resign-employee --force')
            ->expectsConfirmation('Update these employee data?', 'yes')
            ->expectsOutputToContain('1 employee data has been updated')
            ->assertSuccessful();
    });

    it('resigns every matching employee in one run', function () {
        makeResignReason();
        $active = makeEmploymentStatus('Permanent', isTerminal: false);
        makeEmployee('DF909', $active);
        makeEmployee('DF910', $active);

        fakeGreatdayResignFeed([
            greatdayEmployee('DF909', '2026-07-25T00:00:00Z'),
            greatdayEmployee('DF910', '2026-08-01T00:00:00Z'),
        ]);

        $this->mock(EmployeeService::class)
            ->shouldReceive('resign')
            ->twice()
            ->andReturn(['error' => false, 'message' => 'ok', 'data' => [], 'code' => 200]);

        artisan('app:sync-resign-employee --force')
            ->expectsConfirmation('Update these employee data?', 'yes')
            ->expectsOutputToContain('2 employee data has been updated')
            ->assertSuccessful();
    });

    it('resigns nobody when the confirmation is declined', function () {
        makeResignReason();
        makeEmployee('DF911', makeEmploymentStatus('Permanent', isTerminal: false));

        fakeGreatdayResignFeed([greatdayEmployee('DF911', '2026-07-25T00:00:00Z')]);

        $this->mock(EmployeeService::class)->shouldNotReceive('resign');

        artisan('app:sync-resign-employee --force')
            ->expectsConfirmation('Update these employee data?', 'no')
            ->expectsOutputToContain('Aborted. Nothing was updated.')
            ->assertSuccessful();
    });
});
