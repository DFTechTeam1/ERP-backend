<?php

use App\Actions\Project\DetailCache;
use App\Enums\Production\TaskStatus;
use Modules\Hrd\Models\Employee;
use Modules\Production\Models\Project;
use Modules\Production\Models\ProjectTask;

beforeEach(function () {
    $this->user = initAuthenticateUser(
        withEmployee: true,
        permissions: [
            'move_board',
            'edit_task_description',
            'add_task_description',
            'delete_task_description',
            'assign_modeller',
            'list_member'
        ]
    );

    $this->actingAs($this->user);

    $this->project = Project::factory()
        ->withBoards()
        ->create();
});

it ('Hold task return success', function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $employee = Employee::factory()
        ->withUser()
        ->create();

    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $employee, withWorkState: true)
        ->create([
            'status' => TaskStatus::OnProgress->value
        ]);

    $response = $this->postJson(
        route('api.production.task.hold', [
            'projectUid' => $this->project->uid,
            'taskUid' => $task->uid,
        ]),
        [
            'reason' => 'Need to wait for client feedback',
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseHas('project_tasks', [
        'id' => $task->id,
        'status' => TaskStatus::OnHold->value,
    ]);

    $workState = $task->workStates->where('employee_id', $employee->id)->last();

    $this->assertDatabaseHas('project_task_pic_holdstates', [
        'reason' => 'Need to wait for client feedback',
        'task_id' => $task->id,
        'employee_id' => $employee->id,
        'unholded_at' => null,
        'work_state_id' => $workState->id,
    ]);

    $this->assertDatabaseCount('project_task_pic_holdstates', 1);
});

it ("Hold task when task have 2 pic", function () {
    DetailCache::mock()
        ->shouldReceive('handle')
        ->withAnyArgs()
        ->andReturn($this->project->toArray());

    $employee1 = Employee::factory()
        ->withUser()
        ->create();

    $employee2 = Employee::factory()
        ->withUser()
        ->create();

    $task = ProjectTask::factory()
        ->for($this->project, 'project')
        ->for($this->project->boards->first(), 'board')
        ->withPics(employee: $employee1, withWorkState: true)
        ->withPics(employee: $employee2, withWorkState: true)
        ->create([
            'status' => TaskStatus::OnProgress->value
        ]);

    $response = $this->postJson(
        route('api.production.task.hold', [
            'projectUid' => $this->project->uid,
            'taskUid' => $task->uid,
        ]),
        [
            'reason' => 'Need to wait for client feedback',
        ]
    );

    $response->assertStatus(201);

    $this->assertDatabaseCount('project_task_pic_holdstates', 2);
});