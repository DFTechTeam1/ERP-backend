<?php

use App\Actions\Hrd\ResignScheduleAction;
use App\Enums\Employee\Status;
use Modules\Hrd\Models\Employee;
use Modules\Hrd\Models\EmploymentStatus;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;

it('Empty transfer history', function () {
    $employmentStatus = EmploymentStatus::factory()
        ->create([
            'is_terminal' => 0
        ]);

    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
            'employment_status_id' => $employmentStatus->id
        ]);

    ResignScheduleAction::run();

    assertDatabaseHas('employees', [
        'email' => $employee->email,
        'status' => Status::Permanent->value,
        'employment_status_id' => $employmentStatus->id
    ]);
});

it ('Resign schedule is success', function () {
    $employmentStatus = EmploymentStatus::factory()
        ->create([
            'is_terminal' => 0
        ]);

    $terminalStatus = EmploymentStatus::factory()
        ->create([
            'is_terminal' => 1
        ]);

    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Permanent->value,
            'employment_status_id' => $employmentStatus->id
        ]);

    ResignScheduleAction::run();

    assertDatabaseMissing('employees', [
        'id' => $employee->id,
        'status' => Status::Permanent->value,
        'employment_status_id' => $employmentStatus->id
    ]);
    assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value,
        'employment_status_id' => $terminalStatus->id
    ]);
    assertDatabaseHas('employee_resigns', [
        'employee_id' => $employee->id,
        'current_position_id' => $employee->position_id
    ]);
});
