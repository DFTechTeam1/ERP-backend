<?php

use App\Enums\Employee\Gender;
use App\Enums\Employee\MartialStatus;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Http\Requests\Employee\UpdateEmployeeV2;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\GreatdayCompany;
use Modules\Hrd\Services\EmployeeService;

use function Pest\Laravel\actingAs;

/**
 * The payload the V2 HR form posts (see employee-update.json), with overrides applied.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeV2Payload(array $overrides = []): array
{
    return array_merge([
        'nickname' => 'Fahrul',
        'id_number' => '3578031707980002',
        'email' => 'fahrulhidayat@dfactory.pro',
        'first_name' => 'Fahrul',
        'middle_name' => '',
        'last_name' => 'Hidayat',
        'gender' => '1',
        'birth_day' => '1998-07-16',
        'birth_place' => 'Surabaya',
        'address' => 'Kedung Baruk gg 14 No.1A, Rungkut, Surabaya',
        'mobile_phone' => '89504856969',
        'username' => 'Fahrul',
        'register_on_greatday' => 0,
        'employee_no' => 'DF079',
        'join_date' => '2026-02-23',
        'company_id' => 'sfgo11677',
        'job_grade' => 'STF',
        'cost_center' => 'DFV',
        'employment_status' => 'PROBATION',
        'work_location' => 'KCP',
        'shift_pattern' => 'SPOPERATIONS(FLX10)',
        'job_status' => '5WD',
        'end_date' => null,
        'nationality' => 'ID',
        'religion' => 'ISLAM',
        'marital_status' => '0',
        'timezone_id' => '30',
        'bank_name' => 'CIMB',
        'bank_account_number' => '764226807500',
        'bank_account_holder_name' => 'Fahrul Hidayat',
        'invite_on_erp' => 0,
        'update_on_greatday' => 0,
    ], $overrides);
}

/**
 * Fake the Greatday PUT /Employee response.
 */
function fakeGreatdayUpdate(bool $success = true, int $status = 200, string $message = 'ok'): void
{
    Http::fake(function ($request) use ($success, $status, $message) {
        if (str_contains($request->url(), '/Employee')) {
            return Http::response([['empNo' => 'DF079', 'success' => $success, 'message' => $message]], $status);
        }

        return Http::response(['access_token' => 'x', 'refresh_token' => 'y'], 200);
    });
}

beforeEach(function () {
    actingAs(User::factory()->create());
    config(['app.greatday.base_url' => 'https://greatday.test/api']);

    $division = DivisionBackup::create(['name' => 'Entertainment', 'greatday_position_id' => 10]);
    $this->position = PositionBackup::create([
        'name' => 'Visual Jockey',
        'division_id' => $division->id,
        'greatday_code' => 'POSX',
        'greatday_position_id' => 100,
    ]);

    $this->service = app(EmployeeService::class);
});

it('updates the employee columns from the v2 payload', function () {
    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'name' => 'Old Name',
        'nickname' => 'Old',
        'gender' => Gender::Female->value,
    ]);

    $result = $this->service->updateEmployeeV2(employeeV2Payload(), $employee->uid);

    expect($result['error'])->toBeFalse();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'name' => 'Fahrul Hidayat',
        'nickname' => 'Fahrul',
        'email' => 'fahrulhidayat@dfactory.pro',
        'id_number' => '3578031707980002',
        'employee_id' => 'DF079',
        'place_of_birth' => 'Surabaya',
        'phone' => '89504856969',
        'gender' => Gender::Male->value,
        'martial_status' => MartialStatus::Single->value,
        'greatday_company' => 'sfgo11677',
        'greatday_job_grade' => 'STF',
        'greatday_cost_center' => 'DFV',
        'greatday_employment_status' => 'PROBATION',
        'greatday_work_location' => 'KCP',
        'greatday_shift_pattern' => 'SPOPERATIONS(FLX10)',
        'greatday_job_status' => '5WD',
        'greatday_nationality' => 'ID',
        'greatday_religion' => 'ISLAM',
        'greatday_timezone' => '30',
        'greatday_marital_status' => '0',
    ]);

    $bank = json_decode($employee->fresh()->bank_detail, true);
    expect($bank[0]['bank_name'])->toBe('CIMB');
    expect($bank[0]['account_number'])->toBe('764226807500');
    expect($bank[0]['account_holder_name'])->toBe('Fahrul Hidayat');
});

it('resolves position and supervisor uids to local ids', function () {
    $boss = Employee::factory()->create(['employee_id' => 'DF500']);
    $newPosition = PositionBackup::create([
        'name' => '3D Animator',
        'division_id' => $this->position->division_id,
        'greatday_code' => 'POSY',
        'greatday_position_id' => 101,
    ]);

    $employee = Employee::factory()->create(['position_id' => $this->position->id, 'boss_id' => null]);

    $this->service->updateEmployeeV2(employeeV2Payload([
        'position' => $newPosition->uid,
        'supervisor' => $boss->uid,
        'manager' => $boss->uid,
    ]), $employee->uid);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'position_id' => $newPosition->id,
        'boss_id' => $boss->id,
    ]);
});

it('does not touch columns the payload omits', function () {
    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'nickname' => 'Keep Me',
        'address' => 'Keep This Address',
    ]);

    $this->service->updateEmployeeV2([
        'email' => 'only-email@dfactory.pro',
        'update_on_greatday' => 0,
    ], $employee->uid);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'email' => 'only-email@dfactory.pro',
        'nickname' => 'Keep Me',
        'address' => 'Keep This Address',
    ]);
});

it('does not call greatday when update_on_greatday is 0', function () {
    Http::fake();

    $employee = Employee::factory()->create(['position_id' => $this->position->id]);

    $this->service->updateEmployeeV2(employeeV2Payload(['update_on_greatday' => 0]), $employee->uid);

    Http::assertNothingSent();
});

it('pushes a PERSONALUPDATE transaction to greatday when update_on_greatday is 1', function () {
    GreatdayCompany::create(['code' => 'sfgo11677', 'company_id' => '35532', 'name' => 'DFactory Visual']);

    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'employee_id' => 'DF079',
    ]);

    fakeGreatdayUpdate();

    $result = $this->service->updateEmployeeV2(employeeV2Payload(['update_on_greatday' => 1]), $employee->uid);

    expect($result['error'])->toBeFalse();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/Employee') || $request->method() !== 'PUT') {
            return false;
        }

        $item = $request->data()[0];

        // built from the already-updated employee, so it carries the new values
        return $item['transactionType'] === 'PERSONALUPDATE'
            && $item['empNo'] === 'DF079'
            && $item['employeeName'] === 'Fahrul Hidayat'
            && $item['email'] === 'fahrulhidayat@dfactory.pro'
            && $item['companyId'] === '35532' // resolved from the code "sfgo11677"
            && $item['position'] === 'POSX'
            && $item['status'] === 'PROBATION'
            && $item['bankCode'] === 'CIMB'
            && ! array_key_exists('transactionEffectiveDate', $item);
    });
});

it('rolls the local update back when greatday rejects the change', function () {
    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'employee_id' => 'DF079',
        'nickname' => 'Untouched',
    ]);

    fakeGreatdayUpdate(success: false, message: 'employee is locked');

    $result = $this->service->updateEmployeeV2(employeeV2Payload(['update_on_greatday' => 1]), $employee->uid);

    expect($result['error'])->toBeTrue();
    expect($result['message'])->toContain('employee is locked');

    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nickname' => 'Untouched']);
});

it('rolls the local update back when greatday returns a failed status', function () {
    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'employee_id' => 'DF079',
        'nickname' => 'Untouched',
    ]);

    fakeGreatdayUpdate(status: 500);

    $result = $this->service->updateEmployeeV2(employeeV2Payload(['update_on_greatday' => 1]), $employee->uid);

    expect($result['error'])->toBeTrue();
    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nickname' => 'Untouched']);
});

it('refuses the greatday push when the employee has no employee number', function () {
    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'nickname' => 'Untouched',
    ]);

    Http::fake();

    $result = $this->service->updateEmployeeV2(employeeV2Payload([
        'update_on_greatday' => 1,
        'employee_no' => '',
    ]), $employee->uid);

    expect($result['error'])->toBeTrue();
    expect($result['message'])->toContain(__('notification.greatdayUpdateMissingEmpNo'));
    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nickname' => 'Untouched']);
});

it('returns an error when the employee does not exist', function () {
    $result = $this->service->updateEmployeeV2(employeeV2Payload(), 'no-such-uid');

    expect($result['error'])->toBeTrue();
    expect($result['message'])->toBe(__('notification.employeeNotFound'));
});

it('updates the employee through the endpoint', function () {
    $employee = Employee::factory()->create(['position_id' => $this->position->id, 'nickname' => 'Old']);

    Http::fake();

    $response = $this->putJson(
        route('api.employees.updateEmployeeV2', ['employeeUid' => $employee->uid]),
        employeeV2Payload()
    );

    $response->assertSuccessful();
    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'nickname' => 'Fahrul',
        'name' => 'Fahrul Hidayat',
    ]);
    Http::assertNothingSent();
});

it('pushes to greatday through the endpoint when the flag is on', function () {
    $employee = Employee::factory()->create(['position_id' => $this->position->id, 'employee_id' => 'DF079']);

    fakeGreatdayUpdate();

    $response = $this->putJson(
        route('api.employees.updateEmployeeV2', ['employeeUid' => $employee->uid]),
        employeeV2Payload(['update_on_greatday' => 1])
    );

    $response->assertSuccessful();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/Employee') && $request->method() === 'PUT');
});

it('drops payload keys that are not part of the update contract', function () {
    $employee = Employee::factory()->create(['position_id' => $this->position->id]);

    Http::fake();

    $this->putJson(
        route('api.employees.updateEmployeeV2', ['employeeUid' => $employee->uid]),
        employeeV2Payload()
    )->assertSuccessful();

    // username lives on the users table; the two flags are create-time concerns, so none of them
    // are part of the update contract and never reach the service
    $rules = app(UpdateEmployeeV2::class)->rules();
    expect($rules)->not->toHaveKey('username');
    expect($rules)->not->toHaveKey('invite_on_erp');
    expect($rules)->not->toHaveKey('register_on_greatday');
});

it('rejects an invalid payload', function (array $payload, string $invalidField) {
    $employee = Employee::factory()->create(['position_id' => $this->position->id]);

    $response = $this->putJson(
        route('api.employees.updateEmployeeV2', ['employeeUid' => $employee->uid]),
        employeeV2Payload($payload)
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors($invalidField);
})->with([
    'gender outside the greatday codes' => [['gender' => '5'], 'gender'],
    'marital status outside the greatday codes' => [['marital_status' => '9'], 'marital_status'],
    'unknown position uid' => [['position' => 'not-a-position'], 'position'],
    'unknown supervisor uid' => [['supervisor' => 'not-an-employee'], 'supervisor'],
    'malformed email' => [['email' => 'not-an-email'], 'email'],
    'non-date join date' => [['join_date' => 'yesterday-ish'], 'join_date'],
]);

it('rejects an employee number already taken by someone else', function () {
    Employee::factory()->create(['position_id' => $this->position->id, 'employee_id' => 'DF079']);
    $employee = Employee::factory()->create(['position_id' => $this->position->id, 'employee_id' => 'DF080']);

    $response = $this->putJson(
        route('api.employees.updateEmployeeV2', ['employeeUid' => $employee->uid]),
        employeeV2Payload(['employee_no' => 'DF079'])
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('employee_no');
});

it('allows an employee to keep its own employee number', function () {
    $employee = Employee::factory()->create(['position_id' => $this->position->id, 'employee_id' => 'DF079']);

    Http::fake();

    $this->putJson(
        route('api.employees.updateEmployeeV2', ['employeeUid' => $employee->uid]),
        employeeV2Payload(['employee_no' => 'DF079'])
    )->assertSuccessful();
});

it('maps greatday marital codes onto the erp enum, leaving widow(er) alone', function (string $code, ?string $expected) {
    $employee = Employee::factory()->create([
        'position_id' => $this->position->id,
        'martial_status' => MartialStatus::Married->value,
    ]);

    $this->service->updateEmployeeV2(employeeV2Payload(['marital_status' => $code]), $employee->uid);

    $fresh = $employee->fresh();

    // the raw greatday code is always stored
    expect($fresh->greatday_marital_status)->toBe($code);
    // the erp enum only moves for codes it can represent
    expect($fresh->martial_status->value)->toBe($expected ?? MartialStatus::Married->value);
})->with([
    'single' => ['0', MartialStatus::Single->value],
    'married' => ['1', MartialStatus::Married->value],
    'widow keeps the existing erp value' => ['2', null],
    'widower keeps the existing erp value' => ['3', null],
]);
