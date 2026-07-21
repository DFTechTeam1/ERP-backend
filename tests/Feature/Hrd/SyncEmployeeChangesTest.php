<?php

use App\Enums\Production\TaskPicStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\GreatdayCompany;
use Modules\Hrd\Models\GreatdayCostCenter;
use Modules\Hrd\Models\GreatdayJobGrade;
use Modules\Hrd\Models\GreatdayJobStatus;
use Modules\Hrd\Models\GreatdayWorkLocation;
use Modules\Production\Models\ProjectTask;
use Modules\Production\Models\ProjectTaskPic;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function greatdayEmpRecord(array $override = []): array
{
    return array_merge([
        'empId' => 'DO260077',
        'firstName' => 'Excris', 'middleName' => 'Endy', 'lastName' => 'Pratito',
        'nickName' => 'Excris', 'identityNo' => '3504180704960002', 'phone' => '628579333333',
        'address' => 'New Address', 'birthPlace' => 'Tulungagung', 'empNo' => 'DF080',
        'gradeCode' => 'STF', 'costCode' => 'DFV', 'worklocationCode' => 'KCP', 'jobStatus' => '5WD',
        'companyId' => 35532, 'birthDate' => '1997-04-07T00:00:00.000Z',
        'startDate' => '2026-05-12T00:00:00.000Z', 'endDate' => null, 'gender' => '1',
        'posCode' => 'POSNEW', 'posNameEn' => 'Visual Jockey', 'spvParent' => null,
        'employmentStatusCode' => 'PROBATION', 'employmentStatus' => 'Percobaan',
        'bankCode' => 'CIMB', 'bankAccount' => '764264518900', 'bankAccountName' => 'Excris Endy Pratito',
    ], $override);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function fakeGreatdayEmployeeList(array $records): void
{
    Http::fake(function ($request) use ($records) {
        if (str_contains($request->url(), '/employees')) {
            $page = $request['page'] ?? 1;

            return Http::response(['data' => $page == 1 ? $records : []], 200);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });
}

beforeEach(function () {
    actingAs(User::factory()->create());
    config(['app.greatday.base_url' => 'https://greatday.test/api']);

    $division = DivisionBackup::create(['name' => 'Entertainment', 'greatday_position_id' => 10]);
    $this->oldPosition = PositionBackup::create(['name' => 'Visual Operator', 'division_id' => $division->id, 'greatday_code' => 'POSOLD', 'greatday_position_id' => 100]);
    $this->newPosition = PositionBackup::create(['name' => 'Visual Jockey', 'division_id' => $division->id, 'greatday_code' => 'POSNEW', 'greatday_position_id' => 101]);
    $this->status = EmploymentStatus::factory()->create(['code' => 'PROBATION', 'name' => 'Percobaan', 'is_terminal' => 0]);

    $this->employee = Employee::factory()->create([
        'greatday_emp_id' => 'DO260077',
        'phone' => '8579000000',
        'position_id' => $this->oldPosition->id,
        'employment_status_id' => null,
    ]);
});

it('previews only the fields that differ, as display strings', function () {
    fakeGreatdayEmployeeList([greatdayEmpRecord()]);

    $response = $this->getJson(route('api.greatday.employeeChangesPreview'));

    $response->assertStatus(201);
    $row = collect($response->json('data.changed'))->firstWhere('greatday_emp_id', 'DO260077');

    expect($row)->not->toBeNull();
    expect($row['employee_uid'])->toBe($this->employee->uid);

    $fields = collect($row['changes'])->pluck('field');
    expect($fields)->toContain('phone')->toContain('position')->toContain('employment_status');

    expect(collect($row['changes'])->firstWhere('field', 'phone')['to'])->toBe('8579333333');
    expect(collect($row['changes'])->firstWhere('field', 'position')['to'])->toBe('Visual Jockey');
    expect(collect($row['changes'])->firstWhere('field', 'position')['from'])->toBe('Visual Operator');
    expect(collect($row['changes'])->firstWhere('field', 'employment_status')['to'])->toBe('Percobaan');
});

it('does not return employees with no differences', function () {
    // Greatday reports the same phone/position/status the employee already has
    Employee::where('id', $this->employee->id)->update([
        'phone' => '8579333333',
        'position_id' => $this->newPosition->id,
        'employment_status_id' => $this->status->id,
    ]);

    fakeGreatdayEmployeeList([greatdayEmpRecord([
        // align the remaining fields the spec compares so nothing else flags
        'firstName' => $this->employee->name, 'middleName' => '', 'lastName' => '',
        'nickName' => $this->employee->nickname, 'identityNo' => $this->employee->id_number,
        'address' => $this->employee->address, 'birthPlace' => $this->employee->place_of_birth,
        'empNo' => $this->employee->employee_id, 'gradeCode' => $this->employee->greatday_job_grade,
        'costCode' => $this->employee->greatday_cost_center, 'worklocationCode' => $this->employee->greatday_work_location,
        'jobStatus' => $this->employee->greatday_job_status, 'companyId' => $this->employee->greatday_company,
        'birthDate' => Carbon::parse($this->employee->date_of_birth)->toIso8601String(),
        'startDate' => Carbon::parse($this->employee->join_date)->toIso8601String(),
        'gender' => $this->employee->gender->value === 'male' ? '1' : '0',
        'bankCode' => ($this->employee->bank_detail[0]['bank_name'] ?? null),
        'bankAccount' => ($this->employee->bank_detail[0]['account_number'] ?? null),
    ])]);

    $response = $this->getJson(route('api.greatday.employeeChangesPreview'));

    $response->assertStatus(201);
    expect(collect($response->json('data.changed'))->firstWhere('greatday_emp_id', 'DO260077'))->toBeNull();
});

it('applies confirmed changes to the employee', function () {
    fakeGreatdayEmployeeList([greatdayEmpRecord()]);

    $response = $this->postJson(route('api.greatday.applyEmployeeChanges'), ['employees' => ['DO260077']]);

    $response->assertStatus(201);
    expect($response->json('data.total_updated'))->toBe(1);

    $this->assertDatabaseHas('employees', [
        'id' => $this->employee->id,
        'phone' => '8579333333',
        'position_id' => $this->newPosition->id,
        'employment_status_id' => $this->status->id,
        'greatday_employment_status' => 'PROBATION',
    ]);
});

it('rolls back the whole batch when one employee id is unknown', function () {
    fakeGreatdayEmployeeList([greatdayEmpRecord()]);

    $response = $this->postJson(route('api.greatday.applyEmployeeChanges'), [
        'employees' => ['DO260077', 'DO_UNKNOWN'],
    ]);

    $response->assertStatus(400);

    // the good employee's update was rolled back
    $this->assertDatabaseHas('employees', [
        'id' => $this->employee->id,
        'phone' => '8579000000',
        'position_id' => $this->oldPosition->id,
    ]);
});

it('validates that employees is a non-empty array', function () {
    $response = $this->postJson(route('api.greatday.applyEmployeeChanges'), ['employees' => []]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['employees']);
});

it('treats phones with duplicated country codes as equal (no phantom phone change)', function () {
    // both malformed with duplicated 62 prefixes, but the same real number 85795327357
    Employee::where('id', $this->employee->id)->update(['phone' => '6285795327357']);

    fakeGreatdayEmployeeList([greatdayEmpRecord(['phone' => '+626285795327357'])]);

    $row = collect($this->getJson(route('api.greatday.employeeChangesPreview'))->json('data.changed'))
        ->firstWhere('greatday_emp_id', 'DO260077');

    // other fields still differ, but phone must NOT be reported as a change
    expect($row === null ? null : collect($row['changes'])->firstWhere('field', 'phone'))->toBeNull();
});

it('flags sensitive changes and marks the row unblocked when there are no active tasks', function () {
    fakeGreatdayEmployeeList([greatdayEmpRecord()]);

    $row = collect($this->getJson(route('api.greatday.employeeChangesPreview'))->json('data.changed'))
        ->firstWhere('greatday_emp_id', 'DO260077');

    $byField = collect($row['changes'])->keyBy('field');

    expect($byField['phone']['sensitive'])->toBeTrue();
    expect($byField['position']['sensitive'])->toBeTrue();
    expect($byField['name']['sensitive'])->toBeFalse();
    expect($row['blocked'])->toBeFalse();
});

it('detects and blocks a division change when the employee has active tasks', function () {
    $otherDivision = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 20]);
    PositionBackup::create(['name' => '3D Artist', 'division_id' => $otherDivision->id, 'greatday_code' => 'POS3D', 'greatday_position_id' => 200]);

    // employee currently holds an active (approved / in-progress) task
    $task = ProjectTask::factory()->create();
    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $this->employee->id,
        'status' => TaskPicStatus::Approved->value,
        'assigned_at' => now(),
        'assigned_by' => null,
    ]);

    // Greatday now puts them in a position under a different division
    fakeGreatdayEmployeeList([greatdayEmpRecord(['posCode' => 'POS3D', 'posNameEn' => '3D Artist'])]);

    $row = collect($this->getJson(route('api.greatday.employeeChangesPreview'))->json('data.changed'))
        ->firstWhere('greatday_emp_id', 'DO260077');

    $division = collect($row['changes'])->firstWhere('field', 'division');
    expect($division)->not->toBeNull();
    expect($division['sensitive'])->toBeTrue();
    expect($division['to'])->toBe('Production');
    expect($row['blocked'])->toBeTrue();
    expect($row['blocked_reason'])->not->toBeNull();

    // apply must reject the whole batch and leave the employee untouched
    $this->postJson(route('api.greatday.applyEmployeeChanges'), ['employees' => ['DO260077']])
        ->assertStatus(400);

    $this->assertDatabaseHas('employees', [
        'id' => $this->employee->id,
        'position_id' => $this->oldPosition->id,
    ]);
});

it('blocks a supervisor change when the employee has active tasks', function () {
    Employee::factory()->create(['greatday_emp_id' => 'DO250099', 'name' => 'New Boss']);

    $task = ProjectTask::factory()->create();
    ProjectTaskPic::create([
        'project_task_id' => $task->id,
        'employee_id' => $this->employee->id,
        'status' => TaskPicStatus::WaitingApproval->value,
        'assigned_at' => now(),
        'assigned_by' => null,
    ]);

    // same division (POSNEW is in Entertainment), so only the supervisor changes
    fakeGreatdayEmployeeList([greatdayEmpRecord(['spvParent' => 'DO250099'])]);

    $row = collect($this->getJson(route('api.greatday.employeeChangesPreview'))->json('data.changed'))
        ->firstWhere('greatday_emp_id', 'DO260077');

    $bossChange = collect($row['changes'])->firstWhere('field', 'boss');
    expect($bossChange)->not->toBeNull();
    expect($bossChange['sensitive'])->toBeTrue();
    expect($bossChange['to'])->toBe('New Boss');
    expect($row['blocked'])->toBeTrue();

    $this->postJson(route('api.greatday.applyEmployeeChanges'), ['employees' => ['DO260077']])
        ->assertStatus(400);

    $this->assertDatabaseHas('employees', [
        'id' => $this->employee->id,
        'boss_id' => null,
    ]);
});

it('applies a division change when the employee has no active tasks', function () {
    $otherDivision = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 20]);
    $otherPos = PositionBackup::create(['name' => '3D Artist', 'division_id' => $otherDivision->id, 'greatday_code' => 'POS3D', 'greatday_position_id' => 200]);

    fakeGreatdayEmployeeList([greatdayEmpRecord(['posCode' => 'POS3D', 'posNameEn' => '3D Artist'])]);

    $this->postJson(route('api.greatday.applyEmployeeChanges'), ['employees' => ['DO260077']])
        ->assertStatus(201);

    $this->assertDatabaseHas('employees', [
        'id' => $this->employee->id,
        'position_id' => $otherPos->id,
    ]);
});

it('resolves greatday master-data codes to human-readable names in the diff', function () {
    GreatdayJobGrade::create(['code' => 'DIRUT', 'name' => 'Direktur Utama']);
    GreatdayCostCenter::create(['code' => 'CBI', 'name_en' => 'Creative Business', 'name_id' => 'Bisnis Kreatif']);
    GreatdayWorkLocation::create(['code' => 'KCP', 'name' => 'Kantor KCP', 'address' => 'Surabaya', 'max_radius' => 100]);
    GreatdayJobStatus::create(['code' => '5WD', 'name' => '5 Working Days']);
    GreatdayCompany::create(['code' => 'C1', 'name' => 'DFactory Visual', 'company_id' => '35532', 'nickname' => 'DFV', 'is_base_office' => 1, 'address' => 'Surabaya']);

    // employee has no greatday_* metadata yet, so each is an incoming change
    fakeGreatdayEmployeeList([greatdayEmpRecord([
        'gradeCode' => 'DIRUT', 'costCode' => 'CBI', 'worklocationCode' => 'KCP',
        'jobStatus' => '5WD', 'companyId' => 35532,
    ])]);

    $row = collect($this->getJson(route('api.greatday.employeeChangesPreview'))->json('data.changed'))
        ->firstWhere('greatday_emp_id', 'DO260077');

    $to = fn (string $field) => collect($row['changes'])->firstWhere('field', $field)['to'];

    expect($to('job_grade'))->toBe('Direktur Utama');
    expect($to('cost_center'))->toBe('Creative Business');
    expect($to('work_location'))->toBe('Kantor KCP');
    expect($to('job_status'))->toBe('5 Working Days');
    expect($to('company'))->toBe('DFactory Visual');
});
