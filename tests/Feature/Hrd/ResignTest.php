<?php

use App\Enums\Employee\Status;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    Queue::fake();
    Notification::fake();

    // turnOffEmployee moves the employee onto the terminal employment status and refuses to
    // run without one, so every deactivating resignation needs it seeded.
    EmploymentStatus::factory()->create(['code' => 'RESIGN', 'name' => 'Resign', 'is_terminal' => 1]);

    config(['app.greatday.base_url' => 'https://greatday.test/api']);

    Http::fake([
        '*' => Http::response([
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
        ], 200),
    ]);
});

it('processes an immediate resignation, deactivates the employee and pushes to Greatday', function () {
    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
            'employee_id' => 'DF999',
        ]);

    $response = $this->postJson(
        route('api.employees.resign', ['employeeUid' => $employee->uid]),
        [
            'resign_reason_code' => 'RES001',
            'resign_date' => now()->format('Y-m-d'),
            'remark' => 'Moving to another city',
            'sync_greatday' => 1,
        ]
    );

    $response->assertStatus(201);
    expect($response->json()['message'])->toBe(__('notification.successResign', ['name' => $employee->name]));

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value,
    ]);

    $this->assertDatabaseHas('employee_resigns', [
        'employee_id' => $employee->id,
        'reason' => 'Moving to another city',
        'greatday_resign_reason' => 'RES001',
        'current_position_id' => $employee->position_id,
    ]);

    // login access is revoked (user soft deleted)
    $this->assertSoftDeleted('users', [
        'employee_id' => $employee->id,
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Employee')
            && $request->method() === 'PUT'
            && $request->data()[0]['transactionType'] === 'TERMINATION'
            && $request->data()[0]['empNo'] === 'DF999';
    });
});

it('schedules a future resignation without deactivating the employee', function () {
    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
        ]);

    $response = $this->postJson(
        route('api.employees.resign', ['employeeUid' => $employee->uid]),
        [
            'resign_reason_code' => 'RES002',
            'resign_date' => now()->addMonth()->format('Y-m-d'),
            'remark' => 'End of contract',
            'sync_greatday' => 0,
        ]
    );

    $response->assertStatus(201);
    expect($response->json()['message'])->toBe(__('notification.resignHasBeenOnScheduled'));

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Permanent->value,
    ]);

    $this->assertDatabaseHas('employee_resigns', [
        'employee_id' => $employee->id,
        'greatday_resign_reason' => 'RES002',
    ]);

    // employee still has an active login
    $this->assertDatabaseHas('users', [
        'employee_id' => $employee->id,
        'deleted_at' => null,
    ]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/Employee'));
});

it('proceeds with the resignation even when the Greatday push fails', function () {
    Http::fake([
        '*Employee' => Http::response(['message' => 'error'], 500),
        '*' => Http::response([
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
        ], 200),
    ]);

    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
            'employee_id' => 'DF123',
        ]);

    $response = $this->postJson(
        route('api.employees.resign', ['employeeUid' => $employee->uid]),
        [
            'resign_reason_code' => 'RES003',
            'resign_date' => now()->format('Y-m-d'),
            'remark' => 'Test failure path',
            'sync_greatday' => 1,
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('employee_resigns', [
        'employee_id' => $employee->id,
        'greatday_resign_reason' => 'RES003',
    ]);
    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value,
    ]);
});

it('rejects a resignation when the employee already has a resignation record', function () {
    $employee = Employee::factory()
        ->withUser()
        ->withResignation()
        ->create([
            'status' => Status::Permanent->value,
        ]);

    $response = $this->postJson(
        route('api.employees.resign', ['employeeUid' => $employee->uid]),
        [
            'resign_reason_code' => 'RES004',
            'resign_date' => now()->format('Y-m-d'),
            'remark' => 'Duplicate',
            'sync_greatday' => 0,
        ]
    );

    $response->assertStatus(400);
    expect($response->json()['message'])->toBe(__('notification.employeeAlreadyHasResignationRecord'));
});

it('validates the resignation payload', function () {
    $employee = Employee::factory()->withUser()->create();

    $response = $this->postJson(
        route('api.employees.resign', ['employeeUid' => $employee->uid]),
        [
            'resign_date' => 'not-a-date',
            'sync_greatday' => 1,
        ]
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['resign_reason_code', 'resign_date']);
});
