<?php

use App\Enums\Employee\Status;
use Modules\Hrd\Models\Employee;

beforeEach(function () {
    $user = initAuthenticateUser();

    $this->actingAs($user);
});

it ('Cancel project when employee already inactive', function () {
    $employee = Employee::factory()
        ->withUser()
        ->create([
            'status' => Status::Inactive->value
        ]);

    $response = $this->getJson(route('api.employees.cancelResign', ['employeeUid' => $employee->uid]));

    $response->assertStatus(400);
    $response->assertJsonStructure([
        'message',
        'data'
    ]);

    expect($response->json()['message'])->toBe(__('notification.cannotCancelResignationInactiveOrDeleted'));

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value
    ]);
});

it ('Cancel resignation return success', function () {
    $employee = Employee::factory()
        ->withUser()
        ->withResignation()
        ->create([
            'status' => Status::Permanent->value
        ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Permanent->value
    ]);

    $response = $this->getJson(route('api.employees.cancelResign', ['employeeUid' => $employee->uid]));

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data',
        'message'
    ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => Status::Inactive->value
    ]);
});
