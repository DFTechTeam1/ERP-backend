<?php

use App\Enums\Employee\Status;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;
use Modules\Hrd\Services\EmployeeService;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    Queue::fake();

    // turnOffEmployee moves the employee onto the terminal employment status and refuses to
    // run without one, so every deactivating resignation needs it seeded.
    EmploymentStatus::factory()->create(['code' => 'RESIGN', 'name' => 'Resign', 'is_terminal' => 1]);
});

/**
 * Create an employee with a resignation record on the given date.
 */
function makeResigningEmployee(string $resignDate): Employee
{
    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
        ]);

    $employee->resignData()->create([
        'reason' => 'Resignation',
        'resign_date' => $resignDate,
        'current_position_id' => $employee->position_id,
        'current_employee_status' => $employee->status,
    ]);

    return $employee;
}

it('deactivates employees whose resignation date is today', function () {
    $employee = makeResigningEmployee(now()->format('Y-m-d'));

    app(EmployeeService::class)->checkEmployeeWhoResignToday();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value,
        'end_date' => now()->format('Y-m-d'),
    ]);

    // login access is revoked (user soft deleted)
    $this->assertSoftDeleted('users', [
        'employee_id' => $employee->id,
    ]);
});

it('leaves employees with a future resignation date untouched', function () {
    $employee = makeResigningEmployee(now()->addMonth()->format('Y-m-d'));

    app(EmployeeService::class)->checkEmployeeWhoResignToday();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Permanent->value,
    ]);

    $this->assertDatabaseHas('users', [
        'employee_id' => $employee->id,
        'deleted_at' => null,
    ]);
});

it('does nothing when no resignation is due today', function () {
    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
        ]);

    app(EmployeeService::class)->checkEmployeeWhoResignToday();

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Permanent->value,
    ]);
});
