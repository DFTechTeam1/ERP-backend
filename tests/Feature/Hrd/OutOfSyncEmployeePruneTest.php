<?php

use App\Enums\Employee\OutOfSyncStatus;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\OutOfSyncEmployee;
use Modules\Hrd\Services\EmployeeService;

/**
 * @param  array<int, string>  $empIds  empIds Greatday should return on page 1
 */
function fakeGreatdayEmployees(array $empIds): void
{
    Http::fake(function ($request) use ($empIds) {
        if (str_contains($request->url(), '/employees')) {
            $page = $request['page'] ?? 1;
            $data = $page == 1
                ? collect($empIds)->map(fn ($id) => ['empId' => $id])->all()
                : [];

            return Http::response(['data' => $data], 200);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });
}

function makeOutOfSync(string $greatdayEmpId): OutOfSyncEmployee
{
    return OutOfSyncEmployee::create([
        'greatday_employee_id' => $greatdayEmpId,
        'employee_id' => 'EMP-'.$greatdayEmpId,
        'first_name' => 'First',
        'middle_name' => '',
        'last_name' => 'Last',
        'email' => strtolower($greatdayEmpId).'@x.test',
        'position_code' => 'PC',
        'position_name' => 'Position',
        'employment_status' => 'Permanent',
        'employment_status_code' => 'PERM',
        'company_id' => '1',
        'address' => 'Somewhere',
        'phone' => '0',
        'job_status' => 'active',
        'work_location_code' => 'WL',
        'cost_center_code' => 'CC',
        'org_unit' => 'OU',
        'status' => OutOfSyncStatus::OutOfSync,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    config(['app.greatday.base_url' => 'https://greatday.test/api']);
});

it('prunes out-of-sync rows whose Greatday employee is gone, keeps the rest', function () {
    $terminal = EmploymentStatus::factory()->create(['is_terminal' => 1]);
    $active = EmploymentStatus::factory()->create(['is_terminal' => 0]);

    // real employees already linked to Greatday G1/G2 (so they are not re-added as out of sync)
    Employee::factory()->create(['greatday_emp_id' => 'G1', 'employment_status_id' => $active->id]);
    Employee::factory()->create(['greatday_emp_id' => 'G2', 'employment_status_id' => $active->id]);

    // Greatday still returns G1 and G2 — but NOT "ZOLO"
    fakeGreatdayEmployees(['G1', 'G2']);

    $keep = makeOutOfSync('G1');
    $zolo = makeOutOfSync('ZOLO');

    $result = app(EmployeeService::class)->getOutOfSyncEmployees();

    expect($result['error'])->toBeFalse();
    expect($result['data']['total_removed'])->toBe(1);

    $this->assertDatabaseHas('out_of_sync_employees', ['id' => $keep->id]);
    $this->assertDatabaseMissing('out_of_sync_employees', ['id' => $zolo->id]);
});

it('never prunes when Greatday returns an empty set (failed/empty fetch guard)', function () {
    EmploymentStatus::factory()->create(['is_terminal' => 1]);

    fakeGreatdayEmployees([]); // page 1 empty

    $zolo = makeOutOfSync('ZOLO');

    $result = app(EmployeeService::class)->getOutOfSyncEmployees();

    expect($result['error'])->toBeFalse();
    expect($result['data']['total_removed'])->toBe(0);
    $this->assertDatabaseHas('out_of_sync_employees', ['id' => $zolo->id]);
});

it('refreshes end_working_date on an existing row when Greatday later adds an endDate', function () {
    EmploymentStatus::factory()->create(['is_terminal' => 1]);

    // Greatday now returns DO1 WITH an endDate
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/employees')) {
            $page = $request['page'] ?? 1;
            $data = $page == 1 ? [[
                'empId' => 'DO1', 'empNo' => 'DF900',
                'firstName' => 'Zolo', 'middleName' => '', 'lastName' => 'Testing',
                'email' => 'zolo@mail.com', 'posCode' => 'PC', 'posNameEn' => 'IT',
                'employmentStatus' => 'Permanent', 'employmentStatusCode' => 'PERM',
                'startDate' => '2025-01-01', 'endDate' => '2026-06-30',
                'companyId' => '1', 'address' => 'x', 'phone' => '0',
                'jobStatus' => 'active', 'worklocationCode' => 'WL', 'costCode' => 'CC',
                'orgUnit' => 'OU', 'employmentStartDate' => '2025-01-01',
            ]] : [];

            return Http::response(['data' => $data], 200);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });

    // pre-existing row created earlier with no end_working_date
    $row = makeOutOfSync('DO1');
    expect($row->end_working_date)->toBeNull();

    app(EmployeeService::class)->getOutOfSyncEmployees();

    $row->refresh();
    expect($row->end_working_date)->not->toBeNull();
    expect((string) $row->end_working_date)->toContain('2026-06-30');
});
