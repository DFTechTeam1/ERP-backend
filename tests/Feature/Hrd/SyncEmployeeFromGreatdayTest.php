<?php

use App\Enums\Employee\OutOfSyncStatus;
use App\Enums\Employee\Status;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Company\Models\DivisionBackup;
use Modules\Company\Models\PositionBackup;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Models\OutOfSyncEmployee;
use Spatie\Permission\Models\Role;

/**
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function syncEmployeePayload(array $override = []): array
{
    return array_merge([
        'email' => 'newhire@dfactory.test',
        'employee_id' => 'DF200',
        'first_name' => 'New',
        'middle_name' => '',
        'last_name' => 'Hire',
        'nickname' => 'Newbie',
        'id_number' => '3512345678901234',
        'nationality' => 'Indonesia',
        'gender' => '1', // 1 = Male
        'birth_day' => '1996-05-20',
        'birth_place' => 'Surabaya',
        'religion' => 'ISLAM', // name, any case
        'marital_status' => '0', // 0 = Single
        'timezone_id' => 7,
        'mobile_phone' => '628123456789',
        'address' => 'Jl. Test No. 1',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder_name' => 'New Hire',
        'out_of_sync_id' => 0,
        'greatday_employee_id' => 'DO260099',
        'employee_no' => 'DF200',
        'join_date' => '2026-07-01',
        'end_date' => null,
        'company_id' => '35532',
        'position' => 'position-uid', // overridden per test with the real PositionBackup uid
        'job_grade' => 'STF',
        'cost_center' => 'CC01',
        'employment_status' => 'PERM',
        'work_location' => 'WL01',
        'supervisor' => 'supervisor-uid', // overridden per test with a real employee uid
        'manager' => 'manager-uid',
        'shift_pattern' => 'SP01',
        'department' => 'Production',
        'job_status' => 'active',
        'role' => 0, // filled in by the test with a real role id
    ], $override);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Queue::fake();
    Notification::fake();

    $this->role = Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

    $division = DivisionBackup::create(['name' => 'Production', 'greatday_position_id' => 10]);
    $this->position = PositionBackup::create(['name' => '3D Artist', 'division_id' => $division->id, 'greatday_code' => 'POS-3D', 'greatday_position_id' => 100]);
    EmploymentStatus::factory()->create(['code' => 'PERM', 'is_terminal' => 0]);
});

it('creates the ERP employee + user and marks the out-of-sync row synced', function () {
    $outOfSync = OutOfSyncEmployee::create([
        'greatday_employee_id' => 'DO260099', 'employee_id' => 'DF200', 'first_name' => 'New', 'last_name' => 'Hire',
        'email' => 'newhire@dfactory.test', 'position_code' => 'PC', 'position_name' => 'IT',
        'employment_status' => 'PERM', 'employment_status_code' => 'PERM', 'company_id' => '1',
        'address' => 'x', 'phone' => '0', 'job_status' => 'active', 'work_location_code' => 'WL',
        'cost_center_code' => 'CC', 'org_unit' => 'OU', 'status' => OutOfSyncStatus::OutOfSync,
    ]);

    $payload = ['employees' => [syncEmployeePayload(['out_of_sync_id' => $outOfSync->id, 'role' => $this->role->id, 'position' => $this->position->uid])]];

    $response = $this->postJson(route('api.greatday.syncEmployees'), $payload);

    $response->assertStatus(201);
    expect($response->json('data.total_synced'))->toBe(1);

    $this->assertDatabaseHas('employees', [
        'email' => 'newhire@dfactory.test',
        'employee_id' => 'DF200',
        'name' => 'New Hire',
        'greatday_emp_id' => 'DO260099',
        'status' => Status::Permanent->value,
        'gender' => 'male',
        'martial_status' => 'single',
        'religion' => 'islam',
        'phone' => '8123456789', // normalized from mobile_phone "628123456789"
    ]);

    $employee = Employee::where('email', 'newhire@dfactory.test')->first();
    $this->assertNotNull($employee->user_id);
    $this->assertDatabaseHas('users', ['email' => 'newhire@dfactory.test', 'employee_id' => $employee->id]);

    $this->assertDatabaseHas('out_of_sync_employees', [
        'id' => $outOfSync->id,
        'status' => OutOfSyncStatus::Synced->value,
    ]);
});

it('links boss_id from the supervisor uid', function () {
    $boss = Employee::factory()->create();

    $outOfSync = OutOfSyncEmployee::create([
        'greatday_employee_id' => 'DO260099', 'employee_id' => 'DF200', 'first_name' => 'New',
        'email' => 'newhire@dfactory.test', 'position_code' => 'PC', 'position_name' => 'IT',
        'employment_status' => 'PERM', 'employment_status_code' => 'PERM', 'company_id' => '1',
        'address' => 'x', 'phone' => '0', 'job_status' => 'active', 'work_location_code' => 'WL',
        'cost_center_code' => 'CC', 'org_unit' => 'OU', 'status' => OutOfSyncStatus::OutOfSync,
    ]);

    $this->postJson(route('api.greatday.syncEmployees'), [
        'employees' => [syncEmployeePayload(['out_of_sync_id' => $outOfSync->id, 'role' => $this->role->id, 'position' => $this->position->uid, 'supervisor' => $boss->uid])],
    ])->assertStatus(201);

    $this->assertDatabaseHas('employees', ['email' => 'newhire@dfactory.test', 'boss_id' => $boss->id]);
});

it('skips an employee that already exists but still marks it synced', function () {
    Employee::factory()->create(['email' => 'newhire@dfactory.test']);

    $outOfSync = OutOfSyncEmployee::create([
        'greatday_employee_id' => 'DO260099', 'employee_id' => 'DF200', 'first_name' => 'New',
        'email' => 'newhire@dfactory.test', 'position_code' => 'PC', 'position_name' => 'IT',
        'employment_status' => 'PERM', 'employment_status_code' => 'PERM', 'company_id' => '1',
        'address' => 'x', 'phone' => '0', 'job_status' => 'active', 'work_location_code' => 'WL',
        'cost_center_code' => 'CC', 'org_unit' => 'OU', 'status' => OutOfSyncStatus::OutOfSync,
    ]);

    $response = $this->postJson(route('api.greatday.syncEmployees'), [
        'employees' => [syncEmployeePayload(['out_of_sync_id' => $outOfSync->id, 'role' => $this->role->id, 'position' => $this->position->uid])],
    ]);

    $response->assertStatus(201);
    expect($response->json('data.total_skipped'))->toBe(1);
    expect($response->json('data.total_synced'))->toBe(0);

    $this->assertDatabaseHas('out_of_sync_employees', ['id' => $outOfSync->id, 'status' => OutOfSyncStatus::Synced->value]);
});

it('rolls back the whole batch when a position code is unresolved', function () {
    $outOfSync = OutOfSyncEmployee::create([
        'greatday_employee_id' => 'DO260099', 'employee_id' => 'DF200', 'first_name' => 'New',
        'email' => 'newhire@dfactory.test', 'position_code' => 'PC', 'position_name' => 'IT',
        'employment_status' => 'PERM', 'employment_status_code' => 'PERM', 'company_id' => '1',
        'address' => 'x', 'phone' => '0', 'job_status' => 'active', 'work_location_code' => 'WL',
        'cost_center_code' => 'CC', 'org_unit' => 'OU', 'status' => OutOfSyncStatus::OutOfSync,
    ]);

    $response = $this->postJson(route('api.greatday.syncEmployees'), [
        'employees' => [syncEmployeePayload(['out_of_sync_id' => $outOfSync->id, 'role' => $this->role->id, 'position' => 'DOES-NOT-EXIST'])],
    ]);

    $response->assertStatus(400);
    $this->assertDatabaseMissing('employees', ['email' => 'newhire@dfactory.test']);
    $this->assertDatabaseHas('out_of_sync_employees', ['id' => $outOfSync->id, 'status' => OutOfSyncStatus::OutOfSync->value]);
});
