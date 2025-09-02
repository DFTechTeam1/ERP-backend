<?php

use App\Enums\Employee\Status;
use Illuminate\Support\Facades\Bus;
use Modules\Hrd\Jobs\DeleteOfficeEmailJob;
use Modules\Hrd\Models\Employee;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it ('Employee will be resign in the next 7 days', function () {
    Bus::fake();

    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value
        ]);

    $resignDate = now()->addDays(7)->format('Y-m-d');
    $reason = 'resign aja';
    $severance = 0;

    $response = $this->postJson(route('api.employees.resign', ['employeeUid' => $employee->uid]), [
        'reason' => $reason,
        'resign_date' => $resignDate,
        'severance' => $severance
    ]);

    $response->assertStatus(201);

    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Permanent->value,
        'end_date' => null
    ]);

    $this->assertDatabaseHas('employee_resigns', [
        'employee_id' => $employee->id,
        'resign_date' => $resignDate,
        'reason' => $reason,
        'severance' => $severance
    ]);

    $this->assertDatabaseEmpty('delete_office_email_queues');

    Bus::assertNotDispatched(DeleteOfficeEmailJob::class);
});

it ("Employee will be resign today", function () {
    Bus::fake();

    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value
        ]);

    $resignDate = now()->format('Y-m-d');
    $reason = 'resign aja';
    $severance = 0;

    $response = $this->postJson(route('api.employees.resign', ['employeeUid' => $employee->uid]), [
        'reason' => $reason,
        'resign_date' => $resignDate,
        'severance' => $severance
    ]);

    $response->assertStatus(201);

    $response->assertJsonStructure([
        'message',
        'data',
    ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value,
        'end_date' => $resignDate
    ]);

    $this->assertDatabaseHas('employee_resigns', [
        'employee_id' => $employee->id,
        'resign_date' => $resignDate,
        'reason' => $reason,
        'severance' => $severance
    ]);

    $this->assertDatabaseHas('delete_office_email_queues', [
        'employee_id' => $employee->id,
        'email' => $employee->email
    ]);

    Bus::assertDispatched(DeleteOfficeEmailJob::class);
});